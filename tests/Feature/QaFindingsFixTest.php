<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QaFindingsFixTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $role . '-qa@example.test',
            'password' => 'LongPassphrase!2026', 'role' => $role, 'is_active' => true,
        ]);
    }

    private function employee(): int
    {
        return DB::table('employees')->insertGetId([
            'employee_code' => 'QA-FIX-001', 'name' => 'Karyawan QA',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_validation_errors_are_translated_into_indonesian(): void
    {
        $employeeId = $this->employee();

        $response = $this->actingAs($this->user('hr'))
            ->post('/modul/payrolls', [
                'employee_id' => $employeeId,
                'payroll_period' => '2027-13',
                'basic_salary' => 1000000,
                'allowance' => '',
                'deduction' => '',
            ]);

        $response->assertSessionHasErrors('payroll_period');
        $content = $this->get('/modul/payrolls/buat')->assertOk()->getContent();

        $this->assertStringContainsString('Format Periode gaji tidak valid.', $content);
        $this->assertStringNotContainsString('validation.date_format', $content);
        $this->assertStringNotContainsString('validation.required', $content);
    }

    public function test_implausible_payroll_year_is_rejected(): void
    {
        $employeeId = $this->employee();
        $yearFrom = now()->year - 2;

        $this->actingAs($this->user('hr'))
            ->post('/modul/payrolls', [
                'employee_id' => $employeeId,
                'payroll_period' => '2006-03',
                'basic_salary' => 1000000,
                'allowance' => '',
                'deduction' => '',
            ])
            ->assertSessionHasErrors('payroll_period');

        $content = $this->get('/modul/payrolls/buat')->getContent();
        $this->assertStringContainsString("rentang tahun {$yearFrom}", $content);
    }

    public function test_payroll_period_field_uses_month_input(): void
    {
        $finance = $this->user('hr');

        $content = $this->actingAs($finance)->get('/modul/payrolls/buat')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/type="month"[^>]*name="payroll_period"|name="payroll_period"[^>]*type="month"/', $content);
    }

    public function test_return_action_uses_modal_instead_of_browser_prompt(): void
    {
        $employeeId = $this->employee();
        DB::table('payrolls')->insert([
            'employee_id' => $employeeId, 'payroll_period' => '2026-11',
            'basic_salary' => 1000000, 'allowance' => 0, 'deduction' => 0,
            'net_salary' => 1000000, 'status' => 'Disetujui',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $content = $this->actingAs($this->user('finance'))
            ->get('/modul/payrolls')->assertOk()->getContent();

        $this->assertStringContainsString('Alasan pengembalian', $content);
        $this->assertStringContainsString('x-data="{ openReturn: false', $content);
        $this->assertStringNotContainsString('prompt(', $content);
    }
}
