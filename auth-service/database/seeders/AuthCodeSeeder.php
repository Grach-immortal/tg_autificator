<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Models\AuthCode;
use Illuminate\Database\Seeder;

class AuthCodeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['id_ad' => 'ivanov.i', 'code' => '123456', 'status_employee' => EmployeeStatus::Active],
            ['id_ad' => 'petrov.p', 'code' => '234567', 'status_employee' => EmployeeStatus::Active],
            ['id_ad' => 'sidorova.a', 'code' => '345678', 'status_employee' => EmployeeStatus::Active],
            ['id_ad' => 'kozlov.d', 'code' => '456789', 'status_employee' => EmployeeStatus::Fired],
            ['id_ad' => 'smirnova.e', 'code' => '567890', 'status_employee' => EmployeeStatus::Active],
        ];

        foreach ($rows as $row) {
            AuthCode::query()->updateOrCreate(
                ['id_ad' => $row['id_ad']],
                [
                    'code' => $row['code'],
                    'status_employee' => $row['status_employee'],
                ],
            );
        }
    }
}
