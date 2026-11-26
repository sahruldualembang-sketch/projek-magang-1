<?php

namespace App\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole(config('simlji.attendances.roles', [])), 403);

        $isStaff = $user->role === 'staff';
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'nama' => ['nullable', 'string', 'max:150'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'])],
            'print' => ['sometimes', 'boolean'],
            'export' => ['nullable', Rule::in(['csv', 'excel'])],
        ]);
        $filters = array_merge([
            'q' => '',
            'nama' => '',
            'tanggal_mulai' => '',
            'tanggal_akhir' => '',
            'status' => '',
            'print' => false,
            'export' => '',
        ], $filters);

        if ($filters['tanggal_mulai'] && $filters['tanggal_akhir']
            && $filters['tanggal_akhir'] < $filters['tanggal_mulai']) {
            return redirect()->route('modules.index', 'attendances')
                ->withErrors([
                    'tanggal_akhir' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
                ])
                ->withInput($request->query());
        }

        $query = DB::table('attendances')
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->select('attendances.*', 'employees.name as employee_name', 'employees.employee_code');

        if ($isStaff) {
            $query->where('attendances.employee_id', $this->staffEmployeeId($user));
        } elseif ($filters['nama'] !== '') {
            $query->where('employees.name', 'like', '%'.$filters['nama'].'%');
        }

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('employees.name', 'like', '%'.$search.'%')
                    ->orWhere('attendances.notes', 'like', '%'.$search.'%')
                    ->orWhere('attendances.status', 'like', '%'.$search.'%');
            });
        }

        if ($filters['tanggal_mulai'] !== '') {
            $query->whereDate('attendances.attendance_date', '>=', $filters['tanggal_mulai']);
        }
        if ($filters['tanggal_akhir'] !== '') {
            $query->whereDate('attendances.attendance_date', '<=', $filters['tanggal_akhir']);
        }
        if ($filters['status'] !== '') {
            $query->where('attendances.status', $filters['status']);
        }

        // 1. Ekspor CSV / Excel
        if (in_array($filters['export'], ['csv', 'excel'], true)) {
            $exportRows = (clone $query)->orderByDesc('attendances.attendance_date')
                ->orderByDesc('attendances.id')
                ->get();

            $fileName = 'rekap-absensi-' . now()->format('Ymd-His') . '.csv';

            return response()->streamDownload(function () use ($exportRows): void {
                $output = fopen('php://output', 'w');
                fputs($output, "\xEF\xBB\xBF");
                fputcsv($output, [
                    'No',
                    'Kode Karyawan',
                    'Nama Karyawan',
                    'Tanggal',
                    'Jam Masuk',
                    'Jam Pulang',
                    'Status',
                    'Keterangan',
                ]);

                foreach ($exportRows as $index => $row) {
                    fputcsv($output, [
                        $index + 1,
                        $row->employee_code ?? '—',
                        $row->employee_name,
                        \Illuminate\Support\Carbon::parse($row->attendance_date)->format('d/m/Y'),
                        $row->check_in ? substr($row->check_in, 0, 5) : '—',
                        $row->check_out ? substr($row->check_out, 0, 5) : '—',
                        $row->status,
                        $row->notes ?? '—',
                    ]);
                }

                fclose($output);
            }, $fileName, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        // 2. Tampilan Cetak / Paginated Biasa
        $isPrint = (bool) $filters['print'];
        $rows = $isPrint
            ? (clone $query)->orderByDesc('attendances.attendance_date')->orderByDesc('attendances.id')->get()
            : $query->orderByDesc('attendances.attendance_date')
                ->orderByDesc('attendances.id')
                ->paginate(12, ['attendances.*', 'employees.name as employee_name', 'employees.employee_code'], 'attendance_page')
                ->withQueryString();

        $today = now();
        $todayAttendance = null;
        $myEmployeeId = $user->employee_id;
        if ($myEmployeeId && $user->employee()->exists()) {
            $todayAttendance = DB::table('attendances')
                ->where('employee_id', $myEmployeeId)
                ->whereDate('attendance_date', $today->toDateString())
                ->first();
        }

        $settings = $this->attendanceSettings();
        $leaveRequests = null;
        $myLeaveRequests = null;
        $correctionHistory = null;
        if ($isStaff) {
            $myLeaveRequests = DB::table('attendance_requests')
                ->where('employee_id', $this->staffEmployeeId($user))
                ->orderByDesc('attendance_date')
                ->paginate(10, ['*'], 'request_page')
                ->withQueryString();
        } elseif (in_array($user->role, ['hr', 'super_admin'], true)) {
            $leaveRequests = DB::table('attendance_requests')
                ->join('employees', 'employees.id', '=', 'attendance_requests.employee_id')
                ->select('attendance_requests.*', 'employees.name as employee_name')
                ->where('attendance_requests.status', 'Pending')
                ->orderBy('attendance_requests.attendance_date')
                ->paginate(10, ['attendance_requests.*', 'employees.name as employee_name'], 'request_page')
                ->withQueryString();

            $correctionHistory = DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->leftJoin('attendances', 'attendances.id', '=', 'audit_logs.record_id')
                ->leftJoin('employees', 'employees.id', '=', 'attendances.employee_id')
                ->where('audit_logs.module', 'attendances')
                ->where('audit_logs.action', 'corrected')
                ->orderByDesc('audit_logs.id')
                ->select(
                    'audit_logs.*',
                    'users.name as reviewer_name',
                    'employees.name as employee_name',
                    'attendances.attendance_date'
                )
                ->paginate(10, [
                    'audit_logs.*',
                    'users.name as reviewer_name',
                    'employees.name as employee_name',
                    'attendances.attendance_date',
                ], 'history_page')
                ->withQueryString();
        }

        return view('modules.attendances.index', [
            'd' => config('simlji.attendances'),
            'filters' => $filters,
            'rows' => $rows,
            'today' => $today,
            'todayAttendance' => $todayAttendance,
            'settings' => $settings,
            'isStaff' => $isStaff,
            'isPrint' => $isPrint,
            'canCorrect' => in_array($user->role, ['hr', 'super_admin'], true),
            'leaveRequests' => $leaveRequests,
            'myLeaveRequests' => $myLeaveRequests,
            'correctionHistory' => $correctionHistory,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $employeeId = $this->staffEmployeeId($request->user());
        $now = now();
        $settings = $this->attendanceSettings();
        $workdays = $this->workdays($settings);

        if (! in_array($now->dayOfWeekIso, $workdays, true)) {
            return $this->attendanceRedirect('error', 'Hari ini bukan jadwal kerja. Hubungi HR jika ada jadwal khusus.');
        }

        $lateAfter = Carbon::parse($now->toDateString().' '.$settings->start_time, config('app.timezone'))
            ->addMinutes((int) $settings->late_grace_minutes);
        $status = $now->greaterThan($lateAfter) ? 'Terlambat' : 'Hadir';

        try {
            $created = DB::transaction(function () use ($employeeId, $now, $status): bool {
                $existing = DB::table('attendances')
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', $now->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return false;
                }

                DB::table('attendances')->insert([
                    'employee_id' => $employeeId,
                    'attendance_date' => $now->toDateString(),
                    'check_in' => $now->format('H:i:s'),
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $created = false;
        }

        return $this->attendanceRedirect(
            $created ? 'success' : 'error',
            $created
                ? 'Absen masuk berhasil dicatat pada '.$now->format('H:i').'.'
                : 'Absensi hari ini sudah tercatat. Hubungi HR jika perlu koreksi.'
        );
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $employeeId = $this->staffEmployeeId($request->user());
        $now = now();

        $result = DB::transaction(function () use ($employeeId, $now): string {
            $attendance = DB::table('attendances')
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $attendance || ! $attendance->check_in) {
                return 'missing';
            }
            if ($attendance->check_out) {
                return 'complete';
            }

            DB::table('attendances')->where('id', $attendance->id)->update([
                'check_out' => $now->format('H:i:s'),
                'updated_at' => $now,
            ]);

            return 'recorded';
        });

        return match ($result) {
            'missing' => $this->attendanceRedirect('error', 'Absen masuk belum tercatat. Silakan absen masuk terlebih dahulu.'),
            'complete' => $this->attendanceRedirect('success', 'Absensi pulang hari ini sudah selesai tercatat.'),
            default => $this->attendanceRedirect('success', 'Absen pulang berhasil dicatat pada '.$now->format('H:i').'.'),
        };
    }

    public function editCorrection(Request $request, int $id): View
    {
        abort_unless($request->user()->hasRole(['hr', 'super_admin']), 403);

        $attendance = DB::table('attendances')
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->select('attendances.*', 'employees.name as employee_name', 'employees.employee_code')
            ->where('attendances.id', $id)
            ->first();
        abort_unless($attendance, 404);

        return view('modules.attendance-correction', [
            'attendance' => $attendance,
            'backUrl' => route('modules.index', ['module' => 'attendances'] + $request->query()),
        ]);
    }

    public function submitLeaveRequest(Request $request): RedirectResponse
    {
        $employeeId = $this->staffEmployeeId($request->user());
        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'request_type' => ['required', Rule::in(['Izin', 'Sakit'])],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['attendance_date'], config('app.timezone'));
        if (! in_array($date->dayOfWeekIso, $this->workdays($this->attendanceSettings()), true)) {
            return $this->attendanceRedirect('error', 'Tanggal pengajuan bukan hari kerja terjadwal.');
        }
        $existingAttendance = DB::table('attendances')
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();
        if ($existingAttendance && ($existingAttendance->status !== 'Alpha' || $existingAttendance->check_in)) {
            return $this->attendanceRedirect('error', 'Sudah ada absensi aktif pada tanggal tersebut.');
        }

        try {
            DB::transaction(function () use ($data, $employeeId): void {
                DB::table('attendance_requests')->insert([
                    'employee_id' => $employeeId,
                    'attendance_date' => $data['attendance_date'],
                    'request_type' => $data['request_type'],
                    'reason' => $data['reason'],
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->attendanceRedirect('error', 'Pengajuan untuk tanggal tersebut sudah pernah dibuat.');
        }

        return $this->attendanceRedirect('success', 'Pengajuan izin/sakit berhasil dikirim dan menunggu persetujuan HR.');
    }

    public function approveLeaveRequest(Request $request, int $id): RedirectResponse
    {
        return $this->reviewLeaveRequest($request, $id, true);
    }

    public function rejectLeaveRequest(Request $request, int $id): RedirectResponse
    {
        return $this->reviewLeaveRequest($request, $id, false);
    }

    public function correct(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['hr', 'super_admin']), 403);

        $data = array_merge([
            'check_in' => null,
            'check_out' => null,
            'notes' => null,
        ], $request->validate([
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:2000'],
        ]));

        if ($data['check_in'] && $data['check_out'] && $data['check_out'] < $data['check_in']) {
            throw ValidationException::withMessages([
                'check_out' => 'Jam pulang tidak boleh sebelum jam masuk.',
            ]);
        }
        if (! $data['check_in'] && $data['check_out']) {
            throw ValidationException::withMessages([
                'check_out' => 'Jam pulang memerlukan jam masuk.',
            ]);
        }
        if (in_array($data['status'], ['Izin', 'Sakit', 'Alpha'], true)) {
            $data['check_in'] = null;
            $data['check_out'] = null;
        } elseif (! $data['check_in']) {
            throw ValidationException::withMessages([
                'check_in' => 'Jam masuk wajib diisi untuk status Hadir atau Terlambat.',
            ]);
        }

        DB::transaction(function () use ($data, $id, $request): void {
            $before = DB::table('attendances')->where('id', $id)->lockForUpdate()->first();
            abort_unless($before, 404);

            $after = [
                'check_in' => $data['check_in'] ? $data['check_in'].':00' : null,
                'check_out' => $data['check_out'] ? $data['check_out'].':00' : null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?: null,
                'reason' => $data['reason'],
            ];
            DB::table('attendances')->where('id', $id)->update([
                'check_in' => $after['check_in'],
                'check_out' => $after['check_out'],
                'status' => $after['status'],
                'notes' => $after['notes'],
                'updated_at' => now(),
            ]);

            $this->writeAudit('attendances', 'corrected', $id, (array) $before, $after, $request);
        });

        return redirect()->route('modules.index', ['module' => 'attendances'] + $request->query())
            ->with('success', 'Koreksi absensi berhasil disimpan dan tercatat pada riwayat perubahan.');
    }

    public function editSettings(): View
    {
        abort_unless(auth()->user()->hasRole(['super_admin']), 403);
        $settings = $this->attendanceSettings();
        $holidays = DB::table('company_holidays')->orderBy('holiday_date')->get();

        return view('settings.attendance', [
            'settings' => $settings,
            'workdays' => $this->workdays($settings),
            'holidays' => $holidays,
        ]);
    }

    public function storeHoliday(Request $r): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole(['super_admin']), 403);
        $data = $r->validate([
            'holiday_date' => ['required', 'date_format:Y-m-d', 'unique:company_holidays,holiday_date'],
            'description' => ['required', 'string', 'max:150'],
        ]);

        $id = DB::table('company_holidays')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->writeAudit('company_holidays', 'created', $id, null, $data, $r);

        return redirect()->route('settings.attendance')->with('success', 'Hari libur berhasil ditambahkan.');
    }

    public function destroyHoliday(Request $r, int $id): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole(['super_admin']), 403);
        $holiday = DB::table('company_holidays')->where('id', $id)->first();
        if ($holiday) {
            DB::table('company_holidays')->where('id', $id)->delete();
            $this->writeAudit('company_holidays', 'deleted', $id, (array) $holiday, null, $r);
        }

        return redirect()->route('settings.attendance')->with('success', 'Hari libur berhasil dihapus.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['super_admin']), 403);
        $data = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'weekday_end_time' => ['required', 'date_format:H:i'],
            'saturday_end_time' => ['required', 'date_format:H:i'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['required', 'integer', 'between:1,6'],
            'automatic_alpha' => ['required', 'boolean'],
        ]);
        if ($data['weekday_end_time'] <= $data['start_time']
            || $data['saturday_end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'weekday_end_time' => 'Jam selesai harus setelah jam mulai.',
                'saturday_end_time' => 'Jam selesai harus setelah jam mulai.',
            ]);
        }

        $workdays = array_values(array_unique(array_map('intval', $data['workdays'])));
        sort($workdays);
        $before = (array) $this->attendanceSettings();
        $after = [
            'start_time' => $data['start_time'].':00',
            'weekday_end_time' => $data['weekday_end_time'].':00',
            'saturday_end_time' => $data['saturday_end_time'].':00',
            'late_grace_minutes' => (int) $data['late_grace_minutes'],
            'workdays' => $workdays,
            'automatic_alpha' => (bool) $data['automatic_alpha'],
        ];

        DB::transaction(function () use ($after, $before, $request): void {
            DB::table('attendance_settings')->where('id', 1)->update([
                'start_time' => $after['start_time'],
                'weekday_end_time' => $after['weekday_end_time'],
                'saturday_end_time' => $after['saturday_end_time'],
                'late_grace_minutes' => $after['late_grace_minutes'],
                'workdays' => json_encode($after['workdays']),
                'automatic_alpha' => $after['automatic_alpha'],
                'updated_at' => now(),
            ]);
            $this->writeAudit('attendance_settings', 'updated', 1, $before, $after, $request);
        });

        return redirect()->route('settings.attendance')
            ->with('success', 'Pengaturan jam kerja berhasil diperbarui.');
    }

    private function reviewLeaveRequest(Request $request, int $id, bool $approved): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['hr', 'super_admin']), 403);
        $data = array_merge(['review_note' => null], $request->validate([
            'review_note' => [$approved ? 'nullable' : 'required', 'string', 'max:2000'],
        ]));

        DB::transaction(function () use ($approved, $data, $id, $request): void {
            $leaveRequest = DB::table('attendance_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($leaveRequest, 404);
            abort_unless($leaveRequest->status === 'Pending', 409, 'Pengajuan ini sudah diproses.');

            if ($approved) {
                $attendance = DB::table('attendances')
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->whereDate('attendance_date', $leaveRequest->attendance_date)
                    ->lockForUpdate()
                    ->first();
                $notes = 'Pengajuan '.$leaveRequest->request_type.' disetujui: '.$leaveRequest->reason;

                if ($attendance && ($attendance->status !== 'Alpha' || $attendance->check_in)) {
                    throw ValidationException::withMessages([
                        'attendance_date' => 'Sudah ada absensi aktif pada tanggal ini; koreksi absensi terlebih dahulu.',
                    ]);
                }

                if ($attendance) {
                    DB::table('attendances')->where('id', $attendance->id)->update([
                        'status' => $leaveRequest->request_type,
                        'notes' => $notes,
                        'updated_at' => now(),
                    ]);
                    $this->writeAudit('attendances', 'corrected', $attendance->id, (array) $attendance, [
                        'status' => $leaveRequest->request_type,
                        'notes' => $notes,
                        'reason' => 'Pengajuan '.$leaveRequest->request_type.' disetujui oleh HR.',
                    ], $request);
                } else {
                    $attendanceId = DB::table('attendances')->insertGetId([
                        'employee_id' => $leaveRequest->employee_id,
                        'attendance_date' => $leaveRequest->attendance_date,
                        'status' => $leaveRequest->request_type,
                        'notes' => $notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->writeAudit('attendances', 'created', $attendanceId, null, [
                        'employee_id' => $leaveRequest->employee_id,
                        'attendance_date' => $leaveRequest->attendance_date,
                        'status' => $leaveRequest->request_type,
                        'notes' => $notes,
                        'reason' => 'Pengajuan '.$leaveRequest->request_type.' disetujui oleh HR.',
                    ], $request);
                }
            }

            $after = [
                'status' => $approved ? 'Approved' : 'Rejected',
                'reviewed_by' => $request->user()->id,
                'review_note' => $data['review_note'] ?: null,
                'reviewed_at' => now()->toDateTimeString(),
            ];
            DB::table('attendance_requests')->where('id', $id)->update($after + ['updated_at' => now()]);
            $this->writeAudit('attendance_requests', $approved ? 'approved' : 'rejected', $id, (array) $leaveRequest, $after, $request);
        });

        return $this->attendanceRedirect(
            'success',
            $approved ? 'Pengajuan disetujui dan absensi diperbarui.' : 'Pengajuan ditolak.'
        );
    }

    private function staffEmployeeId($user): int
    {
        abort_unless($user && $user->role === 'staff', 403);
        abort_unless(
            $user->employee_id && $user->employee()->exists(),
            403,
            'Akun belum terhubung ke data karyawan. Hubungi HR atau Super Admin.'
        );

        return (int) $user->employee_id;
    }

    private function attendanceSettings(): object
    {
        $settings = DB::table('attendance_settings')->where('id', 1)->first();
        abort_unless($settings, 503, 'Pengaturan jam kerja belum tersedia. Hubungi Super Admin.');

        return $settings;
    }

    private function workdays(object $settings): array
    {
        return array_values(array_filter(
            array_map('intval', json_decode($settings->workdays, true) ?? []),
            fn (int $day): bool => $day >= 1 && $day <= 6
        ));
    }

    private function attendanceRedirect(string $level, string $message): RedirectResponse
    {
        return redirect()->route('modules.index', 'attendances')->with($level, $message);
    }

    private function writeAudit(
        string $module,
        string $action,
        int $recordId,
        ?array $before,
        ?array $after,
        Request $request
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id,
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'before_data' => $before ? json_encode($before) : null,
            'after_data' => $after ? json_encode($after) : null,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
