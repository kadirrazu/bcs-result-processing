<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            [
                'name' => 'System Manager',
                'slug' => 'system-manager',
                'sort_order' => 1,
            ],
            [
                'name' => 'Senior System Analyst',
                'slug' => 'senior-system-analyst',
                'sort_order' => 2,
            ],
            [
                'name' => 'System Analyst',
                'slug' => 'system-analyst',
                'sort_order' => 3,
            ],
            [
                'name' => 'Senior Programmer',
                'slug' => 'senior-programmer',
                'sort_order' => 4,
            ],
            [
                'name' => 'Programmer',
                'slug' => 'programmer',
                'sort_order' => 5,
            ],
            [
                'name' => 'Assistant Programmer',
                'slug' => 'assistant-programmer',
                'sort_order' => 6,
            ],
            [
                'name' => 'Computer Operator',
                'slug' => 'computer-operator',
                'sort_order' => 7,
            ],
        ];

        foreach ($designations as $designation) {
            Designation::updateOrCreate(
                ['slug' => $designation['slug']],
                [
                    'name' => $designation['name'],
                    'sort_order' => $designation['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}