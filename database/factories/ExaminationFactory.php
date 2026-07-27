<?php

namespace Database\Factories;

use App\Enums\ExaminationStatus;
use App\Models\Examination;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Examination> */
class ExaminationFactory extends Factory
{
    protected $model = Examination::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'bcs_number' => $number,
            'name' => "{$number}th BCS",
            'slug' => "bcs-{$number}",
            'database_name' => "bcs_exam_{$number}",
            'status' => ExaminationStatus::Draft,
            'is_enabled' => true,
        ];
    }
}
