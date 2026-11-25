<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_only_sees_own_activity_attendance_and_payroll(): void
    {
        [$staffEmployee, $otherEmployee] = $this->createEmployees();
        $this->createEmployeeRecords($staffEmployee, $otherEmployee);
        $staff = $this->createUser('staff', $staffEmployee);

        $this->actingAs($staff)
            ->get('/modul/activities?'.http_build_query([
                'q' => 'Aktivitas',
                'nama' => 'Karyawan Lain',
                'employee_id' => $otherEmployee,
                'tanggal_mulai' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status' => 'Proses',
            ]))
            ->assertOk()
            ->assertSee('Aktivitas Saya')
            ->assertSee('Tanggal Aktivitas Dari')
            ->assertSee('Status Aktivitas')
            ->assertDontSee('Nama Karyawan')
            ->assertSee('Aktivitas Milik Staff')
            ->assertDontSee('Aktivitas Milik Karyawan Lain');

        $this->get('/modul/attendances?'.http_build_query([
            'nama' => 'Karyawan Lain',
            'employee_id' => $otherEmployee,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_akhir' => '2026-11-30',
            'status' => 'Hadir',
        ]))
            ->assertOk()
            ->assertSee('Tanggal Mulai')
            ->assertSee('Status Kehadiran')
            ->assertDontSee('Nama Karyawan')
            ->assertSee('Karyawan Staff Uji')
            ->assertDontSee('Karyawan Lain Uji');

        $this->get('/modul/payrolls?'.http_build_query([
            'nama' => 'Karyawan Lain',
            'employee_id' => $otherEmployee,
            'periode_dari' => '2026-11',
            'periode_sampai' => '2026-11',
            'status' => 'Dibayar',
        ]))
            ->assertOk()
            ->assertSee('Periode Gaji Dari')
            ->assertSee('Status Pembayaran')
            ->assertDontSee('Nama Karyawan')
            ->assertSee('2026-11')
            ->assertDontSee('Karyawan Lain Uji');
    }

    public function test_authorized_roles_can_filter_records_by_employee_name(): void
    {
        [$staffEmployee, $otherEmployee] = $this->createEmployees();
        $this->createEmployeeRecords($staffEmployee, $otherEmployee);

        $roleModules = [
            'hr' => ['activities', 'attendances', 'payrolls'],
            'super_admin' => ['activities', 'attendances', 'payrolls'],
            'director' => ['activities', 'attendances', 'payrolls'],
            'finance' => ['payrolls'],
            'operations' => ['activities'],
        ];

        foreach ($roleModules as $role => $modules) {
            $user = $this->createUser($role);

            foreach ($modules as $module) {
                $this->actingAs($user)
                    ->get('/modul/'.$module.'?nama='.urlencode('Karyawan Lain'))
                    ->assertOk()
                    ->assertSee('Nama Karyawan')
                    ->assertSee('Karyawan Lain Uji');
            }
        }
    }

    public function test_staff_pagination_preserves_filter_parameters(): void
    {
        [$staffEmployee] = $this->createEmployees();
        $staff = $this->createUser('staff', $staffEmployee);
        $now = now();

        for ($index = 1; $index <= 13; $index++) {
            DB::table('activities')->insert([
                'employee_id' => $staffEmployee,
                'title' => 'Aktivitas Paginasi '.$index,
                'description' => 'Pekerjaan uji',
                'activity_date' => '2026-11-10',
                'progress' => 50,
                'status' => 'Proses',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->actingAs($staff)
            ->get('/modul/activities?'.http_build_query([
                'tanggal_mulai' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status' => 'Proses',
            ]))
            ->assertOk()
            ->assertSee('page=2')
            ->assertSee('tanggal_mulai=2026-11-01')
            ->assertSee('tanggal_akhir=2026-11-30')
            ->assertSee('status=Proses');
    }

    public function test_unlinked_staff_cannot_view_employee_records(): void
    {
        $staff = $this->createUser('staff');

        $this->actingAs($staff)
            ->get('/modul/activities')
            ->assertForbidden()
            ->assertSee('Akun karyawan belum ditautkan ke data karyawan.');
    }

    private function createEmployees(): array
    {
        $now = now();
        $staffEmployee = DB::table('employees')->insertGetId([
            'employee_code' => 'STAFF-FILTER-001',
            'name' => 'Karyawan Staff Uji',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherEmployee = DB::table('employees')->insertGetId([
            'employee_code' => 'STAFF-FILTER-002',
            'name' => 'Karyawan Lain Uji',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$staffEmployee, $otherEmployee];
    }

    private function createEmployeeRecords(int $staffEmployee, int $otherEmployee): void
    {
        $now = now();
        foreach ([
            [$staffEmployee, 'Aktivitas Milik Staff'],
            [$otherEmployee, 'Aktivitas Milik Karyawan Lain'],
        ] as [$employeeId, $title]) {
            DB::table('activities')->insert([
                'employee_id' => $employeeId,
                'title' => $title,
                'description' => 'Pekerjaan uji',
                'activity_date' => '2026-11-10',
                'progress' => 50,
                'status' => 'Proses',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([$staffEmployee, $otherEmployee] as $employeeId) {
            DB::table('attendances')->insert([
                'employee_id' => $employeeId,
                'attendance_date' => '2026-11-10',
                'status' => 'Hadir',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('payrolls')->insert([
                'employee_id' => $employeeId,
                'payroll_period' => '2026-11',
                'basic_salary' => 1000000,
                'allowance' => 0,
                'deduction' => 0,
                'net_salary' => 1000000,
                'status' => 'Dibayar',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function createUser(string $role, ?int $employeeId = null): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'-filters@example.test',
            'password' => 'LongPassphrase!2026',
            'role' => $role,
            'employee_id' => $employeeId,
            'is_active' => true,
        ]);
    }
}
