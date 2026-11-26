<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\EmployeeAccountService;
use Illuminate\Console\Command;

class CreateStaffAccounts extends Command
{
    protected $signature = 'sim:create-staff-accounts
                            {--dry-run : Tampilkan daftar karyawan tanpa membuat akun}
                            {--include-inactive : Ikutkan karyawan berstatus Nonaktif}';

    protected $description = 'Membuat akun login staff untuk karyawan yang belum memilikinya';

    public function handle(EmployeeAccountService $accounts): int
    {
        $query = Employee::query()
            ->whereDoesntHave('user')
            ->orderBy('name');

        if (! $this->option('include-inactive')) {
            $query->where('employment_status', '!=', 'Nonaktif');
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $this->info('Semua karyawan sudah memiliki akun login.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Kode', 'Nama', 'Status'],
                $employees->map(fn (Employee $employee) => [
                    $employee->employee_code,
                    $employee->name,
                    $employee->employment_status,
                ])->all()
            );
            $this->info($employees->count() . ' karyawan akan dibuatkan akun. Jalankan tanpa --dry-run untuk membuat.');

            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;

        foreach ($employees as $employee) {
            try {
                $credentials = $accounts->createFor($employee);
                $rows[] = [
                    $credentials['employee_code'],
                    $credentials['name'],
                    $credentials['email'],
                    $credentials['password'],
                ];
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $this->error('Gagal membuat akun: ' . $employee->employee_code . ' - ' . $employee->name);
            }
        }

        if ($rows !== []) {
            $this->table(['Kode', 'Nama', 'Email Login', 'Kata Sandi Sementara'], $rows);
            $this->warn('Catat kata sandi sekarang. Kata sandi tidak dapat ditampilkan lagi.');
        }

        $this->info(count($rows) . ' akun dibuat, ' . $failed . ' gagal.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
