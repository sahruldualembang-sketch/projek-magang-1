<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\DB; use Tests\TestCase;

class PayrollActionLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_sees_status_specific_action_labels(): void
    {
        $user = User::create([
            'name' => 'HR', 'email' => 'hr-label@example.test',
            'password' => 'LongPassphrase!2026', 'role' => 'hr', 'is_active' => true,
        ]);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_code' => 'TEST-KRY-002', 'name' => 'Karyawan Label',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['Draft', 'Disetujui', 'Dibayar'] as $index => $status) {
            DB::table('payrolls')->insert([
                'employee_id' => $employeeId,
                'payroll_period' => sprintf('2026-%02d', $index + 1),
                'basic_salary' => 3000000, 'allowance' => 0, 'deduction' => 0,
                'net_salary' => 3000000, 'status' => $status,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $content = $this->actingAs($user)->get('/modul/payrolls')->assertOk()->getContent();

        $this->assertStringContainsString('Menunggu Pembayaran', $content);
        $this->assertStringContainsString('Sudah Dibayar', $content);
        $this->assertStringNotContainsString('Menunggu Finance', $content);
    }

    public function test_operations_still_sees_waiting_label_for_submitted_expense(): void
    {
        $user = User::create([
            'name' => 'Ops', 'email' => 'ops-label@example.test',
            'password' => 'LongPassphrase!2026', 'role' => 'operations', 'is_active' => true,
        ]);
        DB::table('expenses')->insert([
            'expense_number' => 'TEST-EXP-001', 'expense_date' => '2026-11-01',
            'category' => 'Perlengkapan', 'description' => 'Biaya uji',
            'amount' => 100000, 'status' => 'Menunggu Persetujuan',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $content = $this->actingAs($user)->get('/modul/expenses')->assertOk()->getContent();

        $this->assertStringContainsString('Menunggu Finance', $content);
    }
}
