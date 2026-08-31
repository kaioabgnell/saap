<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            VbmappAreaSeeder::class,
            VbmappItemSeeder::class,
        ]);

        // O catálogo mudou: o cache precisa cair junto.
        Artisan::call('vbmapp:cache-clear');

    }
}
