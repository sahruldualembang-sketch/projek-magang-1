<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->command?->warn('Seeder dilewati: environment bukan local/testing/staging.');

            return;
        }

        // Jangan menimpa password, role, atau status admin yang sudah ada.
        User::firstOrCreate(
            ['email' => 'admin@sim-lji.local'],
            [
                'name' => 'Administrator SIM-LJI',
                'password' => Hash::make('ChangeMe!2026'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $this->call(DemoDataSeeder::class);
    }
}
