<?php

namespace Database\Seeders;

use App\Models\Gender;
use Illuminate\Database\Seeder;

/**
 * Seed only universal gender codes.
 *
 * Divisions, districts and universities must be loaded from approved official
 * master files so this seeder does not introduce unofficial reference data.
 */
final class RegistrationReferenceMasterSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 1, 'name' => 'Male', 'name_bn' => 'পুরুষ'],
            ['code' => 2, 'name' => 'Female', 'name_bn' => 'নারী'],
            ['code' => 3, 'name' => 'Third Gender', 'name_bn' => 'তৃতীয় লিঙ্গ'],
        ];

        foreach ($rows as $row) {
            Gender::query()->updateOrCreate(
                ['code' => $row['code']],
                [...$row, 'is_active' => true],
            );
        }
    }
}
