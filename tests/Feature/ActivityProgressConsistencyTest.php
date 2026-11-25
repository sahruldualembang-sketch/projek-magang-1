<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityProgressConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private int $activityId;

    protected function setUp(): void
    {
        parent::setUp();

        $employeeId = DB::table('employees')->insertGetId([
            'employee_code' => 'QA-ACT-001', 'name' => 'Karyawan Aktivitas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->activityId = DB::table('activities')->insertGetId([
            'employee_id' => $employeeId, 'title' => 'Aktivitas QA',
            'activity_date' => '2026-11-24', 'progress' => 0, 'status' => 'Belum Dimulai',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::create([
            'name' => 'HR', 'email' => 'hr-activity@example.test',
            'password' => 'LongPassphrase!2026', 'role' => 'hr', 'is_active' => true,
        ]);
        $this->actingAs($user);
    }

    private function update(int $progress, string $status)
    {
        return $this->put('/modul/activities/' . $this->activityId . '/progres', [
            'progress' => $progress,
            'status' => $status,
        ]);
    }

    private function assertStored(int $progress, string $status): void
    {
        $this->assertDatabaseHas('activities', [
            'id' => $this->activityId,
            'progress' => $progress,
            'status' => $status,
        ]);
    }

    public function test_nonzero_progress_with_not_started_status_is_reset_to_zero(): void
    {
        $this->update(80, 'Belum Dimulai')->assertRedirect();

        $this->assertStored(0, 'Belum Dimulai');
    }

    public function test_full_progress_with_in_progress_status_becomes_selesai(): void
    {
        $this->update(100, 'Proses')->assertRedirect();

        $this->assertStored(100, 'Selesai');
    }

    public function test_selesai_status_fills_progress_to_hundred(): void
    {
        $this->update(50, 'Selesai')->assertRedirect();

        $this->assertStored(100, 'Selesai');
    }

    public function test_zero_progress_with_in_progress_status_becomes_belum_dimulai(): void
    {
        $this->update(0, 'Proses')->assertRedirect();

        $this->assertStored(0, 'Belum Dimulai');
    }

    public function test_valid_in_progress_combination_is_kept(): void
    {
        $this->update(50, 'Proses')->assertRedirect();

        $this->assertStored(50, 'Proses');
    }

    public function test_tertunda_with_zero_progress_is_allowed(): void
    {
        $this->update(0, 'Tertunda')->assertRedirect();

        $this->assertStored(0, 'Tertunda');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->update(50, 'Status Palsu')->assertSessionHasErrors('status');

        $this->assertStored(0, 'Belum Dimulai');
    }

    public function test_full_edit_form_also_normalizes_progress_and_status(): void
    {
        $employeeId = DB::table('activities')->where('id', $this->activityId)->value('employee_id');

        $this->put('/modul/activities/' . $this->activityId, [
            'employee_id' => $employeeId,
            'title' => 'Aktivitas QA',
            'activity_date' => '2026-11-24',
            'progress' => 80,
            'status' => 'Belum Dimulai',
        ])->assertRedirect();

        $this->assertStored(0, 'Belum Dimulai');
    }

    public function test_full_edit_form_marks_full_progress_as_selesai(): void
    {
        $employeeId = DB::table('activities')->where('id', $this->activityId)->value('employee_id');

        $this->put('/modul/activities/' . $this->activityId, [
            'employee_id' => $employeeId,
            'title' => 'Aktivitas QA',
            'activity_date' => '2026-11-24',
            'progress' => 100,
            'status' => 'Proses',
        ])->assertRedirect();

        $this->assertStored(100, 'Selesai');
    }
}
