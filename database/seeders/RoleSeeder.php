<?php

namespace Database\Seeders;

use App\Support\RoleCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        RoleCatalog::install();
    }
}
