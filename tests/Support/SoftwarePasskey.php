<?php

namespace Tests\Support;

/**
 * Программный WebAuthn-аутентификатор (ES256, attestation "none") для тестов:
 * формирует credential так же, как браузер после navigator.credentials.create/get.
 */
final class SoftwarePasskey
{
    private \OpenSSLAsymmetricKey $key;

    private string $credentialId;

    private string $userHandle = '';

    private int $signCount = 0;

    public function __construct(
        private readonly string $rpId = 'localhost',
        private readonly string $origin = 'http://localhost:8081',
    ) {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(32);
    }

    /** Ответ на navigator.credentials.create() для опций регистрации с сервера. */
    public function register(array $options): array
    {
        $this->userHandle = self::b64decode($options['user']['id']);

        $authData = hash('sha256', $this->rpId, true)
            ."\x45" // UP | UV | AT
            .pack('N', $this->signCount)
            .str_repeat("\0", 16) // aaguid
            .pack('n', strlen($this->credentialId))
            .$this->credentialId
            .$this->coseKey();

        return [
            'id' => self::b64($this->credentialId),
            'rawId' => self::b64($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64($this->clientData('webauthn.create', $options['challenge'])),
                'attestationObject' => self::b64(self::cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => new CborBytes($authData)])),
                'transports' => ['internal'],
            ],
        ];
    }

    /** Ответ на navigator.credentials.get() для опций входа с сервера. */
    public function login(array $options): array
    {
        $clientData = $this->clientData('webauthn.get', $options['challenge']);
        $authData = hash('sha256', $this->rpId, true)."\x05".pack('N', ++$this->signCount); // UP | UV

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => self::b64($this->credentialId),
            'rawId' => self::b64($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64($clientData),
                'authenticatorData' => self::b64($authData),
                'signature' => self::b64($signature),
                'userHandle' => self::b64($this->userHandle),
            ],
        ];
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $this->origin, 'crossOrigin' => false]);
    }

    private function coseKey(): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];

        return self::cbor([
            1 => 2,   // kty: EC2
            3 => -7,  // alg: ES256
            -1 => 1,  // crv: P-256
            -2 => new CborBytes(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)),
            -3 => new CborBytes(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
        ]);
    }

    private static function cbor(mixed $value): string
    {
        return match (true) {
            $value instanceof CborBytes => self::cborHead(2, strlen($value->bytes)).$value->bytes,
            is_int($value) && $value >= 0 => self::cborHead(0, $value),
            is_int($value) => self::cborHead(1, -1 - $value),
            is_string($value) => self::cborHead(3, strlen($value)).$value,
            is_array($value) => self::cborHead(5, count($value)).implode('', array_map(
                fn ($k, $v) => self::cbor($k).self::cbor($v),
                array_keys($value),
                $value,
            )),
        };
    }

    private static function cborHead(int $major, int $length): string
    {
        return match (true) {
            $length < 24 => chr(($major << 5) | $length),
            $length < 0x100 => chr(($major << 5) | 24).chr($length),
            $length < 0x10000 => chr(($major << 5) | 25).pack('n', $length),
            default => chr(($major << 5) | 26).pack('N', $length),
        };
    }

    private static function b64(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private static function b64decode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}

/** @internal */
final class CborBytes
{
    public function __construct(public readonly string $bytes) {}
}
