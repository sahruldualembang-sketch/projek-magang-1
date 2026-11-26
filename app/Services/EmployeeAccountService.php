<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuatan akun login untuk karyawan.
 *
 * Peran selalu dikunci ke "staff" agar HRD tidak dapat menaikkan hak akses.
 * Akun ditautkan ke data karyawan melalui users.employee_id.
 */
class EmployeeAccountService
{
    /**
     * Buat akun staff baru untuk karyawan.
     *
     * @param  string|null  $password  Kata sandi pilihan HR; null = dibuat acak.
     * @return array{name:string, employee_code:string, email:string, password:string}
     */
    public function createFor(Employee $employee, ?string $password = null): array
    {
        if ($employee->user()->exists()) {
            throw new \RuntimeException('Karyawan ini sudah memiliki akun login.');
        }

        $password = $password !== null && $password !== ''
            ? $password
            : $this->generatePassword();

        $user = DB::transaction(function () use ($employee, $password): User {
            $user = User::create([
                'name' => $employee->name,
                'email' => $this->resolveEmail($employee),
                'password' => $password,
                'role' => 'staff',
                'employee_id' => $employee->id,
                'is_active' => true,
            ]);

            $this->audit('created', $user);

            return $user;
        });

        return $this->credentials($employee, $user, $password);
    }

    /**
     * Pakai email karyawan bila valid dan belum dipakai. Jika tidak, buat
     * email internal dari kode karyawan agar akun tetap bisa dibuat.
     */
    public function resolveEmail(Employee $employee): string
    {
        $email = trim((string) $employee->email);

        if ($email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! User::where('email', $email)->exists()) {
            return $email;
        }

        $base = Str::slug((string) $employee->employee_code) ?: 'karyawan-' . $employee->id;
        $candidate = $base . '@sim-lji.local';
        $suffix = 1;

        while (User::where('email', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix . '@sim-lji.local';
            $suffix++;
        }

        return $candidate;
    }

    private function generatePassword(): string
    {
        return Str::password(12, symbols: false);
    }

    private function credentials(Employee $employee, User $user, string $password): array
    {
        return [
            'name' => $employee->name,
            'employee_code' => $employee->employee_code,
            'email' => $user->email,
            'password' => $password,
        ];
    }

    private function audit(string $action, User $user): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'module' => 'users',
            'action' => $action,
            'record_id' => $user->id,
            'after_data' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'employee_id' => $user->employee_id,
                'is_active' => $user->is_active,
            ]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
