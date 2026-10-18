<?php

namespace Database\Seeders;

use App\Models\Kit;
use Illuminate\Database\Seeder;

/**
 * Seeds the done-for-you kits (OTO 5) from database/seeders/data/kits.php.
 */
class KitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/data/kits.php' as $kit) {
            Kit::updateOrCreate(['key' => $kit['key']], $kit);
        }
    }
}
