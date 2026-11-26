<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeAutoAccountTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Uji',
            'email' => $role . '-' . uniqid() . '@example.test',
            'password' => 'LongPassphrase!2026',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createEmployee(User $actor, array $overrides = [])
    {
        return $this->actingAs($actor)->post('/modul/employees', array_merge([
            'employee_code' => 'LJI-AUTO-' . strtoupper(uniqid()),
            'name' => 'Karyawan Baru',
            'employment_status' => 'Tetap',
        ], $overrides));
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'employee_code' => 'LJI-EDIT-' . strtoupper(uniqid()),
            'name' => 'Karyawan Uji',
            'employment_status' => 'Tetap',
        ], $overrides));
    }

    public function test_hr_creating_employee_automatically_creates_staff_account(): void
    {
        $hr = $this->actingUser('hr');

        $response = $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-001',
            'name' => 'Karyawan Otomatis',
            'email' => 'karyawan.otomatis@example.test',
            'department' => 'Operasional',
        ]);

        $response->assertRedirect('/modul/employees');
        $response->assertSessionHas('generated_credentials');

        $employee = Employee::where('employee_code', 'LJI-AUTO-001')->firstOrFail();
        $account = User::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('staff', $account->role);
        $this->assertSame('karyawan.otomatis@example.test', $account->email);
        $this->assertTrue($account->is_active);

        $credentials = session('generated_credentials');
        $this->assertCount(1, $credentials);
        $this->assertSame($account->email, $credentials[0]['email']);
        $this->assertTrue(Hash::check($credentials[0]['password'], $account->password));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'users',
            'action' => 'created',
            'record_id' => $account->id,
        ]);
    }

    public function test_nonactive_employee_does_not_get_an_account(): void
    {
        $hr = $this->actingUser('hr');

        $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-002',
            'name' => 'Karyawan Nonaktif',
            'employment_status' => 'Nonaktif',
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-002')->firstOrFail();

        $this->assertFalse(User::where('employee_id', $employee->id)->exists());
    }

    public function test_duplicate_email_falls_back_to_internal_employee_email(): void
    {
        $hr = $this->actingUser('hr');

        $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-003',
            'name' => 'Karyawan Email Bentrok',
            'email' => $hr->email,
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-003')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'employee_id' => $employee->id,
            'email' => 'lji-auto-003@sim-lji.local',
        ]);
    }

    public function test_super_admin_also_gets_automatic_account(): void
    {
        $admin = $this->actingUser('super_admin');

        $this->createEmployee($admin, [
            'employee_code' => 'LJI-AUTO-004',
            'name' => 'Karyawan oleh Admin',
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-004')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'employee_id' => $employee->id,
            'role' => 'staff',
        ]);
    }

    public function test_director_creating_employee_does_not_create_account(): void
    {
        $director = $this->actingUser('director');

        $this->createEmployee($director, [
            'employee_code' => 'LJI-AUTO-005',
            'name' => 'Karyawan oleh Direktur',
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-005')->firstOrFail();

        $this->assertFalse(User::where('employee_id', $employee->id)->exists());
    }

    public function test_employee_page_has_no_account_management_buttons(): void
    {
        $hr = $this->actingUser('hr');

        $this->actingAs($hr)
            ->get('/modul/employees')
            ->assertOk()
            ->assertDontSee('Buatkan Akun')
            ->assertDontSee('Akun Login')
            ->assertSee('Tambah Karyawan');
    }

    public function test_new_employee_can_change_the_temporary_password(): void
    {
        $hr = $this->actingUser('hr');

        $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-006',
            'name' => 'Karyawan Ganti Sandi',
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-006')->firstOrFail();
        $account = User::where('employee_id', $employee->id)->firstOrFail();
        $temporaryPassword = session('generated_credentials')[0]['password'];

        $this->actingAs($account)
            ->put(route('profile.password.update'), [
                'current_password' => $temporaryPassword,
                'password' => 'SandiBaruKaryawan!2026',
                'password_confirmation' => 'SandiBaruKaryawan!2026',
            ])
            ->assertRedirect(route('profile.password.edit'))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue(Hash::check('SandiBaruKaryawan!2026', $account->password));
    }

    public function test_hr_can_set_the_initial_password_when_creating_employee(): void
    {
        $hr = $this->actingUser('hr');

        $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-007',
            'name' => 'Karyawan Sandi Pilihan',
            'account_password' => 'SandiPilihanHR!2026',
        ])->assertRedirect('/modul/employees');

        $employee = Employee::where('employee_code', 'LJI-AUTO-007')->firstOrFail();
        $account = User::where('employee_id', $employee->id)->firstOrFail();

        $this->assertTrue(Hash::check('SandiPilihanHR!2026', $account->password));

        $credentials = session('generated_credentials');
        $this->assertSame('SandiPilihanHR!2026', $credentials[0]['password']);
    }

    public function test_too_short_initial_password_prevents_employee_creation(): void
    {
        $hr = $this->actingUser('hr');

        $this->createEmployee($hr, [
            'employee_code' => 'LJI-AUTO-008',
            'name' => 'Karyawan Sandi Pendek',
            'account_password' => 'pendek',
        ])->assertSessionHasErrors('account_password');

        $this->assertDatabaseMissing('employees', ['employee_code' => 'LJI-AUTO-008']);
    }

    public function test_password_field_only_appears_on_employee_create_form_for_hr(): void
    {
        $hr = $this->actingUser('hr');

        $this->actingAs($hr)
            ->get('/modul/employees/buat')
            ->assertOk()
            ->assertSee('Kata Sandi Akun Karyawan');

        $director = $this->actingUser('director');

        $this->actingAs($director)
            ->get('/modul/employees/buat')
            ->assertOk()
            ->assertDontSee('Kata Sandi Akun Karyawan');

        $employee = $this->employee(['name' => 'Karyawan Edit']);
        $this->actingAs($hr)
            ->get('/modul/employees/' . $employee->id . '/ubah')
            ->assertOk()
            ->assertDontSee('Kata Sandi Akun Karyawan');
    }
}
