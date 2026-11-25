<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'sim:create-admin
                            {--name= : Nama lengkap administrator}
                            {--email= : Alamat email login}
                            {--password= : Kata sandi administrator}';

    protected $description = 'Membuat akun Super Admin perdana untuk lingkungan produksi/staging';

    public function handle(): int
    {
        $this->info('=== Inisialisasi Akun Super Admin SIM-LJI ===');

        $name = $this->option('name') ?: $this->ask('Masukkan Nama Lengkap Administrator', 'Super Admin LJI');
        $email = $this->option('email') ?: $this->ask('Masukkan Email Login');
        $password = $this->option('password') ?: $this->secret('Masukkan Password Baru (minimal 12 karakter)');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error('Error: ' . $error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->info("✓ Akun Super Admin berhasil dibuat!");
        $this->table(['ID', 'Nama', 'Email', 'Role', 'Status'], [
            [$user->id, $user->name, $user->email, $user->role, 'Aktif'],
        ]);

        return self::SUCCESS;
    }
}
