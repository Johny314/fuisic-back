<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// laragear/webauthn заменён на laravel/passkeys (таблица passkeys). Ключи старого
// формата не переносятся — пользователи регистрируют passkey заново.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }

    public function down(): void
    {
        // необратимо: старая схема принадлежала удалённому пакету laragear/webauthn
    }
};
