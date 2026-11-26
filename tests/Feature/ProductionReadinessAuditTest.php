<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionReadinessAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_offboarding_deactivates_user_and_prevents_login(): void
    {
        $employee = Employee::create([
            'employee_code' => 'KRY-OFFBOARD-01',
            'name' => 'Karyawan Keluar',
            'email' => 'keluar@example.com',
            'employment_status' => 'Tetap',
            'base_salary' => 5000000,
        ]);

        $user = User::create([
            'name' => 'Karyawan Keluar',
            'email' => 'keluar@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'staff',
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $hr = User::create([
            'name' => 'HR Officer',
            'email' => 'hr-officer@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'hr',
            'is_active' => true,
        ]);

        // HR updates status to Nonaktif
        $this->actingAs($hr)->put(route('modules.update', ['module' => 'employees', 'id' => $employee->id]), [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'email' => $employee->email,
            'employment_status' => 'Nonaktif',
            'base_salary' => 5000000,
        ])->assertRedirect(route('modules.index', 'employees'));

        $user->refresh();
        $this->assertFalse($user->is_active);

        // Deactivated user attempts to login
        auth()->logout();
        $response = $this->post(route('login'), [
            'email' => 'keluar@example.com',
            'password' => 'KataSandiKuat123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_updating_employee_name_and_email_syncs_to_user_account(): void
    {
        $employee = Employee::create([
            'employee_code' => 'KRY-SYNC-01',
            'name' => 'Nama Awal',
            'email' => 'awal@example.com',
            'employment_status' => 'Tetap',
            'base_salary' => 5000000,
        ]);

        $user = User::create([
            'name' => 'Nama Awal',
            'email' => 'awal@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'staff',
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $hr = User::create([
            'name' => 'HR Officer',
            'email' => 'hr-sync@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'hr',
            'is_active' => true,
        ]);

        $this->actingAs($hr)->put(route('modules.update', ['module' => 'employees', 'id' => $employee->id]), [
            'employee_code' => $employee->employee_code,
            'name' => 'Nama Baru Disinkron',
            'email' => 'baru@example.com',
            'employment_status' => 'Tetap',
            'base_salary' => 5000000,
        ]);

        $user->refresh();
        $this->assertSame('Nama Baru Disinkron', $user->name);
        $this->assertSame('baru@example.com', $user->email);
    }

    public function test_api_attendance_does_not_overwrite_approved_leave_or_sick(): void
    {
        $employee = Employee::create([
            'employee_code' => 'KRY-API-LEAVE',
            'name' => 'Karyawan Sakit',
            'email' => 'sakit@example.com',
            'employment_status' => 'Tetap',
        ]);

        $today = now()->toDateString();
        DB::table('attendances')->insert([
            'employee_id' => $employee->id,
            'attendance_date' => $today,
            'status' => 'Sakit',
            'notes' => 'Surat Keterangan Dokter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // API clock-in attempt
        $response = $this->postJson(route('api.attendance.record'), [
            'employee_code' => $employee->employee_code,
            'type' => 'in',
            'time' => '08:15:00',
            'attendance_date' => $today,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $attendance = DB::table('attendances')->where('employee_id', $employee->id)->whereDate('attendance_date', $today)->first();
        $this->assertSame('Sakit', $attendance->status);
        $this->assertNull($attendance->check_in);
    }

    public function test_scheduler_skips_marking_alpha_on_company_holidays(): void
    {
        $employee = Employee::create([
            'employee_code' => 'KRY-HOLIDAY',
            'name' => 'Karyawan Libur',
            'employment_status' => 'Tetap',
        ]);

        $now = now();
        DB::table('company_holidays')->insert([
            'holiday_date' => $now->toDateString(),
            'description' => 'Hari Libur Nasional Contoh',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->artisan('attendance:mark-absent')
            ->expectsOutputToContain('adalah hari libur perusahaan. Alpha otomatis dilewati.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employee->id,
            'attendance_date' => $now->toDateString(),
        ]);
    }

    public function test_super_admin_can_manage_company_holidays(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin-holiday@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('settings.attendance.holidays.store'), [
            'holiday_date' => '2026-12-25',
            'description' => 'Hari Raya Natal',
        ]);

        $response->assertRedirect(route('settings.attendance'));
        $this->assertDatabaseHas('company_holidays', [
            'holiday_date' => '2026-12-25',
            'description' => 'Hari Raya Natal',
        ]);

        $holiday = DB::table('company_holidays')->where('holiday_date', '2026-12-25')->first();
        $deleteResponse = $this->actingAs($admin)->delete(route('settings.attendance.holidays.destroy', $holiday->id));
        $deleteResponse->assertRedirect(route('settings.attendance'));
        $this->assertDatabaseMissing('company_holidays', ['id' => $holiday->id]);
    }

    public function test_payslip_view_and_access_permissions(): void
    {
        $employeeA = Employee::create([
            'employee_code' => 'KRY-SLIP-A',
            'name' => 'Karyawan A',
            'employment_status' => 'Tetap',
            'base_salary' => 6000000,
        ]);
        $employeeB = Employee::create([
            'employee_code' => 'KRY-SLIP-B',
            'name' => 'Karyawan B',
            'employment_status' => 'Tetap',
            'base_salary' => 7000000,
        ]);

        $userA = User::create([
            'name' => 'Karyawan A',
            'email' => 'user-a@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'staff',
            'employee_id' => $employeeA->id,
            'is_active' => true,
        ]);
        $userB = User::create([
            'name' => 'Karyawan B',
            'email' => 'user-b@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'staff',
            'employee_id' => $employeeB->id,
            'is_active' => true,
        ]);

        $payrollAId = DB::table('payrolls')->insertGetId([
            'employee_id' => $employeeA->id,
            'payroll_period' => '2026-11',
            'basic_salary' => 6000000,
            'allowance' => 500000,
            'deduction' => 200000,
            'net_salary' => 6300000,
            'status' => 'Dibayar',
            'paid_at' => '2026-11-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff A can view own payslip
        $this->actingAs($userA)->get(route('payrolls.slip', $payrollAId))
            ->assertStatus(200)
            ->assertSee('SLIP GAJI RESMI')
            ->assertSee('Karyawan A')
            ->assertSee('6.300.000');

        // Staff B cannot view Staff A's payslip
        $this->actingAs($userB)->get(route('payrolls.slip', $payrollAId))
            ->assertStatus(403);
    }

    public function test_batch_payroll_generator_creates_drafts_for_active_employees(): void
    {
        $employee1 = Employee::create([
            'employee_code' => 'KRY-BATCH-01',
            'name' => 'Staf Batch Satu',
            'employment_status' => 'Tetap',
            'base_salary' => 4500000,
        ]);
        $employee2 = Employee::create([
            'employee_code' => 'KRY-BATCH-02',
            'name' => 'Staf Batch Dua',
            'employment_status' => 'Kontrak',
            'base_salary' => 5500000,
        ]);
        $inactiveEmployee = Employee::create([
            'employee_code' => 'KRY-BATCH-03',
            'name' => 'Staf Nonaktif',
            'employment_status' => 'Nonaktif',
            'base_salary' => 4000000,
        ]);

        $hr = User::create([
            'name' => 'HR Officer',
            'email' => 'hr-batch@example.com',
            'password' => 'KataSandiKuat123!',
            'role' => 'hr',
            'is_active' => true,
        ]);

        $response = $this->actingAs($hr)->post(route('payrolls.generate-batch'), [
            'period' => '2026-12',
        ]);

        $response->assertRedirect(route('modules.index', 'payrolls'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee1->id,
            'payroll_period' => '2026-12',
            'basic_salary' => 4500000,
            'status' => 'Draft',
        ]);
        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employee2->id,
            'payroll_period' => '2026-12',
            'basic_salary' => 5500000,
            'status' => 'Draft',
        ]);
        $this->assertDatabaseMissing('payrolls', [
            'employee_id' => $inactiveEmployee->id,
            'payroll_period' => '2026-12',
        ]);
    }
}
