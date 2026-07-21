<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $designation = Designation::where('slug', 'system-manager')->first();

        if (! $designation) {
            $this->command?->error(
                'System Manager designation not found. Please run DesignationSeeder first.'
            );

            return;
        }

        User::updateOrCreate(
            [
                'email' => 'testuser@gmail.com',
            ],
            [
                'name'             => 'Mr. Test User',
                'designation_id'   => $designation->id,
                'role'             => UserRole::Admin,
                'password'         => Hash::make('12345678'),
                'email_verified_at'=> now(),
                'is_active'        => true,
                'last_login_at'    => null,
            ]
        );

        $this->command?->info('Default admin user created successfully.');
    }
}