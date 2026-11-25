<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\DB; use Tests\TestCase;

class PayrollOptionalNumericTest extends TestCase
{
    use RefreshDatabase;

    private function hrUser(): User
    {
        return User::create([
            'name' => 'HR', 'email' => 'hr-test@example.test',
            'password' => 'LongPassphrase!2026', 'role' => 'hr', 'is_active' => true,
        ]);
    }

    private function employee(): int
    {
        return DB::table('employees')->insertGetId([
            'employee_code' => 'TEST-KRY-001', 'name' => 'Karyawan Uji',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payload(int $employeeId, string $period = '2026-11'): array
    {
        return [
            'employee_id' => $employeeId,
            'payroll_period' => $period,
            'basic_salary' => 3000000,
            'allowance' => '',
            'deduction' => '',
        ];
    }

    public function test_blank_allowance_and_deduction_are_saved_as_zero(): void
    {
        $user = $this->hrUser();
        $employeeId = $this->employee();

        $this->actingAs($user)->post('/modul/payrolls', $this->payload($employeeId))
            ->assertRedirect(route('modules.index', 'payrolls'));

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $employeeId,
            'payroll_period' => '2026-11',
            'allowance' => 0,
            'deduction' => 0,
            'net_salary' => 3000000,
            'status' => 'Draft',
        ]);
    }

    public function test_duplicate_employee_and_period_shows_validation_error(): void
    {
        $user = $this->hrUser();
        $employeeId = $this->employee();

        $this->actingAs($user)->post('/modul/payrolls', $this->payload($employeeId))->assertRedirect();
        $this->actingAs($user)->post('/modul/payrolls', $this->payload($employeeId))
            ->assertRedirect()
            ->assertSessionHasErrors('payroll_period');

        $this->assertSame(1, DB::table('payrolls')->where('employee_id', $employeeId)->count());
    }

    public function test_editing_same_payroll_with_unchanged_period_is_allowed(): void
    {
        $user = $this->hrUser();
        $employeeId = $this->employee();

        $this->actingAs($user)->post('/modul/payrolls', $this->payload($employeeId))->assertRedirect();
        $id = DB::table('payrolls')->where('employee_id', $employeeId)->value('id');

        $this->actingAs($user)->put('/modul/payrolls/' . $id, $this->payload($employeeId))
            ->assertRedirect(route('modules.index', 'payrolls'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('payrolls')->where('employee_id', $employeeId)->count());
    }
}
