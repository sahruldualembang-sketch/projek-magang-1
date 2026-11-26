<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateStaffAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'LJI-CMD-' . strtoupper(uniqid()),
            'name' => 'Karyawan Perintah',
            'employment_status' => 'Tetap',
        ], $overrides));
    }

    public function test_command_creates_accounts_for_active_employees_without_accounts(): void
    {
        $active = $this->employee(['name' => 'Karyawan Aktif']);
        $nonaktif = $this->employee(['name' => 'Karyawan Nonaktif', 'employment_status' => 'Nonaktif']);

        $sudahPunya = $this->employee(['name' => 'Sudah Punya Akun']);
        User::create([
            'name' => 'Akun Lama',
            'email' => 'akun-lama-command@example.test',
            'password' => 'LongPassphrase!2026',
            'role' => 'staff',
            'employee_id' => $sudahPunya->id,
            'is_active' => true,
        ]);

        $this->artisan('sim:create-staff-accounts')->assertSuccessful();

        $this->assertDatabaseHas('users', ['employee_id' => $active->id, 'role' => 'staff']);
        $this->assertDatabaseMissing('users', ['employee_id' => $nonaktif->id]);
        $this->assertSame(1, User::where('employee_id', $sudahPunya->id)->count());
    }

    public function test_dry_run_does_not_create_any_account(): void
    {
        $employee = $this->employee();

        $this->artisan('sim:create-staff-accounts', ['--dry-run' => true])->assertSuccessful();

        $this->assertFalse(User::where('employee_id', $employee->id)->exists());
    }

    public function test_include_inactive_option_also_creates_for_nonactive_employees(): void
    {
        $employee = $this->employee(['employment_status' => 'Nonaktif']);

        $this->artisan('sim:create-staff-accounts', ['--include-inactive' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['employee_id' => $employee->id, 'role' => 'staff']);
    }

    public function test_command_reports_when_everyone_already_has_an_account(): void
    {
        $employee = $this->employee();
        User::create([
            'name' => 'Akun Lengkap',
            'email' => 'akun-lengkap-command@example.test',
            'password' => 'LongPassphrase!2026',
            'role' => 'staff',
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $this->artisan('sim:create-staff-accounts')
            ->expectsOutput('Semua karyawan sudah memiliki akun login.')
            ->assertSuccessful();
    }
}
