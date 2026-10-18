<?php

namespace Database\Seeders;

use App\Enums\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SponsorSeeder::class,
            KitSeeder::class,
            NetworkSeeder::class,
            BumpOfferSeeder::class,
        ]);

        if (app()->environment('local')) {
            User::factory()->admin()->owning(...Product::cases())->create([
                'name' => 'Demo Admin',
                'email' => 'admin@newspub.test',
            ]);
        }
    }
}
