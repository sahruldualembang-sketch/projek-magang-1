<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class MarkAbsentEmployees extends Command
{
    protected $signature = 'attendance:mark-absent';

    protected $description = 'Mark scheduled employees without attendance as Alpha';

    public function handle(): int
    {
        $settings = DB::table('attendance_settings')->where('id', 1)->first();
        if (! $settings) {
            $this->error('Pengaturan jam kerja belum tersedia.');

            return self::FAILURE;
        }

        $now = now();

        $isHoliday = DB::table('company_holidays')->whereDate('holiday_date', $now->toDateString())->exists();
        if ($isHoliday) {
            $this->info("Hari ini ({$now->toDateString()}) adalah hari libur perusahaan. Alpha otomatis dilewati.");

            return self::SUCCESS;
        }

        $workdays = array_map('intval', json_decode($settings->workdays, true) ?? []);
        if ($now->dayOfWeekIso === 7 || ! $settings->automatic_alpha || ! in_array($now->dayOfWeekIso, $workdays, true)) {
            $this->info('Tidak ada penetapan Alpha untuk jadwal hari ini.');

            return self::SUCCESS;
        }

        $endTime = $now->dayOfWeekIso === 6
            ? $settings->saturday_end_time
            : $settings->weekday_end_time;
        if ($now->format('H:i:s') < $endTime) {
            $this->error('Alpha hanya dapat ditetapkan setelah jam kerja berakhir.');

            return self::FAILURE;
        }

        $marked = 0;
        $employeeIds = DB::table('employees')
            ->where('employment_status', '<>', 'Nonaktif')
            ->where(function ($query) use ($now): void {
                $query->whereNull('join_date')
                    ->orWhereDate('join_date', '<=', $now->toDateString());
            })
            ->whereNotExists(function ($query) use ($now): void {
                $query->selectRaw('1')
                    ->from('attendances')
                    ->whereColumn('attendances.employee_id', 'employees.id')
                    ->whereDate('attendances.attendance_date', $now->toDateString());
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($employeeIds as $employeeId) {
            try {
                DB::table('attendances')->insert([
                    'employee_id' => $employeeId,
                    'attendance_date' => $now->toDateString(),
                    'status' => 'Alpha',
                    'notes' => 'Tidak ada absensi masuk pada hari kerja terjadwal.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $marked++;
            } catch (UniqueConstraintViolationException) {
                // A check-in or approved leave already owns the employee/date pair.
            }
        }

        $this->info("Absensi Alpha ditetapkan untuk {$marked} karyawan.");

        return self::SUCCESS;
    }
}
