<?php

namespace App\Console\Commands;

use App\Services\UserBlocking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:unblock-expired')]
#[Description('Снять блокировки пользователей с истёкшим сроком')]
class UnblockExpiredUsers extends Command
{
    public function handle(UserBlocking $blocking): int
    {
        $this->info('Снято блокировок: '.$blocking->closeExpired());

        return self::SUCCESS;
    }
}
