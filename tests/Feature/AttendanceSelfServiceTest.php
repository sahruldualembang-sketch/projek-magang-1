<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_attendance_api_accepts_short_and_long_time_formats(): void
    {
        $employeeId = $this->createEmployee('API-STAFF', 'API Staff');

        config(['services.attendance.api_key' => 'api-test-key']);

        $this->postJson('/api/v1/attendance/record', [
            'employee_code' => 'API-STAFF',
            'type' => 'in',
            'attendance_date' => '2026-11-23',
            'time' => '08:15',
        ], ['X-API-KEY' => 'api-test-key'])
            ->assertOk()
            ->assertJsonPath('data.check_in', '08:15:00');

        $this->postJson('/api/v1/attendance/record', [
            'employee_code' => 'API-STAFF',
            'type' => 'out',
            'attendance_date' => '2026-11-23',
            'time' => '16:30:00',
        ], ['X-API-KEY' => 'api-test-key'])
            ->assertOk()
            ->assertJsonPath('data.check_out', '16:30:00');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-23',
            'check_in' => '08:15:00',
            'check_out' => '16:30:00',
        ]);
    }

    public function test_clock_out_without_clock_in_is_recorded_as_alpha(): void
    {
        $employeeId = $this->createEmployee('API-OUT-ONLY', 'Karyawan Pulang Saja');

        config(['services.attendance.api_key' => 'api-test-key']);

        $this->postJson('/api/v1/attendance/record', [
            'employee_code' => 'API-OUT-ONLY',
            'type' => 'out',
            'attendance_date' => '2026-11-23',
            'time' => '17:00',
        ], ['X-API-KEY' => 'api-test-key'])
            ->assertOk()
            ->assertJsonPath('data.check_in', null)
            ->assertJsonPath('data.check_out', '17:00:00')
            ->assertJsonPath('data.status', 'Alpha')
            ->assertJsonPath('message', 'Absen pulang tercatat tanpa absen masuk; status ditetapkan Alpha otomatis.');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-23',
            'check_in' => null,
            'check_out' => '17:00:00',
            'status' => 'Alpha',
        ]);
    }

    public function test_clock_in_then_clock_out_keeps_hadir_status(): void
    {
        $employeeId = $this->createEmployee('API-IN-OUT', 'Karyawan Lengkap');

        config(['services.attendance.api_key' => 'api-test-key']);

        $this->postJson('/api/v1/attendance/record', [
            'employee_code' => 'API-IN-OUT',
            'type' => 'in',
            'attendance_date' => '2026-11-23',
            'time' => '08:00',
        ], ['X-API-KEY' => 'api-test-key'])->assertOk();

        $this->postJson('/api/v1/attendance/record', [
            'employee_code' => 'API-IN-OUT',
            'type' => 'out',
            'attendance_date' => '2026-11-23',
            'time' => '17:00',
        ], ['X-API-KEY' => 'api-test-key'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Hadir');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-23',
            'check_in' => '08:00:00',
            'check_out' => '17:00:00',
            'status' => 'Hadir',
        ]);
    }

    public function test_staff_can_clock_in_once_clock_out_and_clock_in_on_another_day(): void
    {
        $employeeId = $this->createEmployee('CLOCK-STAFF', 'Staff Clock');
        $otherEmployeeId = $this->createEmployee('CLOCK-OTHER', 'Other Clock');
        $staff = $this->createUser('staff', $employeeId);

        $this->travelTo(Carbon::parse('2026-11-16 08:45:00', 'Asia/Makassar'));
        $this->actingAs($staff)
            ->post(route('attendance.clock-in'), [
                'employee_id' => $otherEmployeeId,
                'attendance_date' => '2020-01-01',
                'check_in' => '00:01',
                'status' => 'Alpha',
            ])
            ->assertRedirect(route('modules.index', 'attendances'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-16',
            'check_in' => '08:45:00',
            'status' => 'Hadir',
        ]);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $otherEmployeeId]);

        $this->post(route('attendance.clock-in'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('attendances', 1);

        $this->travelTo(Carbon::parse('2026-11-16 17:05:00', 'Asia/Makassar'));
        $this->post(route('attendance.clock-out'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-16',
            'check_out' => '17:05:00',
        ]);

        $this->post(route('attendance.clock-out'))
            ->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 1);

        $this->travelTo(Carbon::parse('2026-11-17 08:30:00', 'Asia/Makassar'));
        $this->post(route('attendance.clock-in'))
            ->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_clock_out_requires_a_clock_in_and_late_status_uses_configured_tolerance(): void
    {
        $employeeId = $this->createEmployee('CLOCK-LOGIC', 'Staff Logic');
        $staff = $this->createUser('staff', $employeeId);

        $this->travelTo(Carbon::parse('2026-11-16 08:00:00', 'Asia/Makassar'));
        $this->actingAs($staff)
            ->post(route('attendance.clock-out'))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('attendances', 0);

        $this->travelTo(Carbon::parse('2026-11-16 09:01:00', 'Asia/Makassar'));
        $this->post(route('attendance.clock-in'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-16',
            'check_in' => '09:01:00',
            'status' => 'Terlambat',
        ]);
    }

    public function test_sunday_is_always_a_holiday_even_if_legacy_settings_include_it(): void
    {
        $employeeId = $this->createEmployee('SUNDAY-STAFF', 'Sunday Staff');
        $staff = $this->createUser('staff', $employeeId);
        DB::table('attendance_settings')->where('id', 1)->update([
            'workdays' => json_encode([1, 2, 3, 4, 5, 6, 7]),
        ]);
        $this->travelTo(Carbon::parse('2026-11-22 17:00:00', 'Asia/Makassar'));

        $this->actingAs($staff)
            ->post(route('attendance.clock-in'))
            ->assertSessionHas('error');
        $this->post(route('attendance.leave-request'), [
            'attendance_date' => '2026-11-22',
            'request_type' => 'Izin',
            'reason' => 'Keperluan keluarga',
        ])->assertSessionHas('error');

        $this->artisan('attendance:mark-absent')->assertExitCode(0);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-22',
        ]);
    }

    public function test_staff_only_sees_own_attendance_and_unlinked_account_gets_clear_error(): void
    {
        $employeeId = $this->createEmployee('PRIVATE-STAFF', 'Private Staff');
        $otherEmployeeId = $this->createEmployee('PRIVATE-OTHER', 'Private Other');
        $staff = $this->createUser('staff', $employeeId);
        $this->createAttendance($employeeId, '2026-11-16', 'Hadir', 'PRIVATE-OWN-RECORD');
        $otherAttendanceId = $this->createAttendance($otherEmployeeId, '2026-11-16', 'Hadir', 'PRIVATE-OTHER-RECORD');

        $this->actingAs($staff)
            ->get('/modul/attendances?'.http_build_query([
                'employee_id' => $otherEmployeeId,
                'tanggal_mulai' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status' => 'Hadir',
            ]))
            ->assertOk()
            ->assertSee('PRIVATE-OWN-RECORD')
            ->assertDontSee('PRIVATE-OTHER-RECORD')
            ->assertDontSee('Nama Karyawan')
            ->assertSee('Tanggal Mulai')
            ->assertSee('Status Kehadiran');

        $this->get(route('modules.edit', ['attendances', $otherAttendanceId]))
            ->assertForbidden();

        $unlinkedStaff = $this->createUser('staff');
        $this->actingAs($unlinkedStaff)
            ->get('/modul/attendances')
            ->assertForbidden()
            ->assertSee('Akun belum terhubung ke data karyawan.');
    }

    public function test_hr_can_filter_all_attendance_and_record_reasoned_corrections(): void
    {
        $firstEmployee = $this->createEmployee('HR-STAFF-001', 'Employee One');
        $secondEmployee = $this->createEmployee('HR-STAFF-002', 'Employee Two');
        $attendanceId = $this->createAttendance($firstEmployee, '2026-11-16', 'Terlambat', 'Original note', '09:10:00');
        $this->createAttendance($secondEmployee, '2026-11-16', 'Hadir', 'FILTERED-OTHER', '08:00:00');
        $hr = $this->createUser('hr');

        $this->actingAs($hr)
            ->get('/modul/attendances?'.http_build_query([
                'nama' => 'Employee Two',
                'tanggal_mulai' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status' => 'Hadir',
            ]))
            ->assertOk()
            ->assertSee('Employee Two')
            ->assertSee('FILTERED-OTHER')
            ->assertDontSee('Original note')
            ->assertSee('Nama Karyawan');

        $editUrl = route('attendance.correction.edit', [
            'id' => $attendanceId,
            'nama' => 'Employee One',
            'attendance_page' => 2,
        ]);
        $this->get($editUrl)
            ->assertOk()
            ->assertSee('Employee One')
            ->assertSee('HR-STAFF-001')
            ->assertSee('09:10')
            ->assertSee('Terlambat')
            ->assertSee('Alasan Koreksi');

        $updateUrl = route('attendance.correction.update', [
            'id' => $attendanceId,
            'nama' => 'Employee One',
            'attendance_page' => 2,
        ]);
        $this->put($updateUrl, [
            'check_in' => '08:00',
            'check_out' => '16:30',
            'status' => 'Hadir',
            'notes' => 'Keterangan diperbarui',
            'reason' => '',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('attendances', ['id' => $attendanceId, 'status' => 'Terlambat']);

        $this->put($updateUrl, [
            'check_in' => '08:00',
            'check_out' => '07:30',
            'status' => 'Hadir',
            'notes' => 'Keterangan diperbarui',
            'reason' => 'Koreksi berdasarkan dokumen HR-123',
        ])->assertSessionHasErrors('check_out');

        $this->put($updateUrl, [
            'check_in' => '08:00',
            'check_out' => '16:30',
            'status' => 'Hadir',
            'notes' => 'Keterangan diperbarui',
            'reason' => 'Koreksi berdasarkan dokumen HR-123',
        ])->assertRedirect(route('modules.index', [
            'module' => 'attendances',
            'nama' => 'Employee One',
            'attendance_page' => 2,
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendanceId,
            'check_in' => '08:00:00',
            'check_out' => '16:30:00',
            'status' => 'Hadir',
            'notes' => 'Keterangan diperbarui',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'attendances',
            'action' => 'corrected',
            'record_id' => $attendanceId,
        ]);
        $afterData = DB::table('audit_logs')
            ->where('module', 'attendances')
            ->where('record_id', $attendanceId)
            ->value('after_data');
        $this->assertStringContainsString('Koreksi berdasarkan dokumen HR-123', $afterData);

        $this->actingAs($hr)
            ->get('/modul/attendances?nama=Employee%20One')
            ->assertOk()
            ->assertSee('Koreksi berdasarkan dokumen HR-123');
    }

    public function test_correction_page_returns_404_for_missing_attendance_and_staff_cannot_open_it(): void
    {
        $employeeId = $this->createEmployee('CORRECTION-ACCESS', 'Correction Access');
        $attendanceId = $this->createAttendance($employeeId, '2026-11-16', 'Hadir', 'Access test', '08:00:00');
        $hr = $this->createUser('hr');

        $this->actingAs($hr)
            ->get(route('attendance.correction.edit', 999999))
            ->assertNotFound();

        $staff = $this->createUser('staff', $employeeId);
        $this->actingAs($staff)
            ->get(route('attendance.correction.edit', $attendanceId))
            ->assertForbidden();

        $this->put(route('attendance.correction.update', $attendanceId), [
            'check_in' => '08:00',
            'check_out' => '16:00',
            'status' => 'Hadir',
            'reason' => 'Tidak berwenang',
        ])->assertForbidden();
    }

    public function test_approved_sick_request_creates_personal_attendance_record(): void
    {
        $employeeId = $this->createEmployee('LEAVE-STAFF', 'Leave Staff');
        $otherEmployeeId = $this->createEmployee('LEAVE-OTHER', 'Leave Other');
        $staff = $this->createUser('staff', $employeeId);
        $hr = $this->createUser('hr');

        $this->travelTo(Carbon::parse('2026-11-16 08:00:00', 'Asia/Makassar'));
        $this->actingAs($staff)
            ->post(route('attendance.leave-request'), [
                'employee_id' => $otherEmployeeId,
                'attendance_date' => '2026-11-17',
                'request_type' => 'Sakit',
                'reason' => 'Surat keterangan dokter',
            ])
            ->assertSessionHas('success');

        $requestId = DB::table('attendance_requests')->value('id');
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-17',
        ]);

        $this->actingAs($hr)
            ->post(route('attendance.leave-approve', $requestId), ['review_note' => 'Disetujui'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('attendance_requests', [
            'id' => $requestId,
            'employee_id' => $employeeId,
            'status' => 'Approved',
        ]);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-17',
            'status' => 'Sakit',
        ]);
    }

    public function test_attendance_filters_are_preserved_by_pagination(): void
    {
        $employeeId = $this->createEmployee('PAGE-STAFF', 'Page Staff');
        $staff = $this->createUser('staff', $employeeId);
        for ($day = 1; $day <= 13; $day++) {
            $date = sprintf('2026-11-%02d', $day);
            $this->createAttendance($employeeId, $date, 'Hadir', 'Pagination record '.$day);
        }

        $this->actingAs($staff)
            ->get('/modul/attendances?'.http_build_query([
                'tanggal_mulai' => '2026-11-01',
                'tanggal_akhir' => '2026-11-30',
                'status' => 'Hadir',
            ]))
            ->assertOk()
            ->assertSee('attendance_page=2')
            ->assertSee('tanggal_mulai=2026-11-01')
            ->assertSee('tanggal_akhir=2026-11-30')
            ->assertSee('status=Hadir');
    }

    public function test_super_admin_can_update_confirmed_work_settings_and_scheduler_marks_alpha(): void
    {
        $employeeId = $this->createEmployee('ALPHA-STAFF', 'Alpha Staff');
        $inactiveEmployee = $this->createEmployee('ALPHA-INACTIVE', 'Inactive Staff', 'Nonaktif');
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('settings.attendance'))
            ->assertOk()
            ->assertSee('16:30')
            ->assertSee('15:00');

        $this->put(route('settings.attendance.update'), [
            'start_time' => '08:00',
            'weekday_end_time' => '16:30',
            'saturday_end_time' => '15:00',
            'late_grace_minutes' => 60,
            'workdays' => [1, 2, 3, 4, 5, 6],
            'automatic_alpha' => 1,
        ])->assertSessionHas('success');

        $this->travelTo(Carbon::parse('2026-11-16 17:00:00', 'Asia/Makassar'));
        $this->artisan('attendance:mark-absent')->assertExitCode(0);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employeeId,
            'attendance_date' => '2026-11-16',
            'status' => 'Alpha',
        ]);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $inactiveEmployee,
            'attendance_date' => '2026-11-16',
        ]);
    }

    private function createEmployee(string $code, string $name, string $status = 'Tetap'): int
    {
        return DB::table('employees')->insertGetId([
            'employee_code' => $code,
            'name' => $name,
            'employment_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAttendance(
        int $employeeId,
        string $date,
        string $status,
        string $notes,
        ?string $checkIn = null
    ): int {
        return DB::table('attendances')->insertGetId([
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'check_in' => $checkIn,
            'status' => $status,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $role, ?int $employeeId = null): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-'.uniqid().'-attendance@example.test',
            'password' => 'LongPassphrase!2026',
            'role' => $role,
            'employee_id' => $employeeId,
            'is_active' => true,
        ]);
    }
}
