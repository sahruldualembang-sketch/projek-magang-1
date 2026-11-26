<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceApiController extends Controller
{
    /**
     * Endpoint untuk memeriksa status kesiapan API Absensi.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'SIM-LJI Attendance Real-Time Sync API',
            'version' => '1.0',
            'status' => 'ready',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Endpoint sinkronisasi presensi real-time dari aplikasi absensi eksternal.
     */
    public function record(Request $request): JsonResponse
    {
        // 1. Verifikasi API Key
        $configuredKey = config('services.attendance.api_key') ?: env('ATTENDANCE_API_KEY');
        $incomingKey = $request->header('X-API-KEY') ?: $request->bearerToken();

        if (empty($configuredKey)) {
            // Jika belum diatur di .env, izinkan di environment non-production untuk kemudahan pengujian awal
            if (app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan API belum dikonfigurasi. Hubungi Administrator.',
                ], 500);
            }
        } elseif (! hash_equals($configuredKey, (string) $incomingKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan pada header X-API-KEY.',
            ], 401);
        }

        // 2. Validasi Payload
        $validator = Validator::make($request->all(), [
            'employee_code' => ['required_without:employee_id', 'nullable', 'string', 'max:30'],
            'employee_id' => ['required_without:employee_code', 'nullable', 'integer'],
            'type' => ['required', Rule::in(['in', 'out', 'masuk', 'pulang'])],
            'attendance_date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'timestamp' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 3. Identifikasi Karyawan
        $employeeQuery = DB::table('employees');
        if (! empty($validated['employee_id'])) {
            $employeeQuery->where('id', $validated['employee_id']);
        } else {
            $employeeQuery->where('employee_code', $validated['employee_code']);
        }

        $employee = $employeeQuery->first();
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan tidak ditemukan di database SIM-LJI.',
            ], 404);
        }

        if ($employee->employment_status === 'Nonaktif') {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan berstatus Nonaktif.',
            ], 422);
        }

        // 4. Hitung Waktu dan Tanggal Presensi
        $now = now();
        if (! empty($validated['timestamp'])) {
            $parsed = Carbon::parse($validated['timestamp'], config('app.timezone'));
            $date = $parsed->toDateString();
            $time = $parsed->format('H:i:s');
        } else {
            $date = $validated['attendance_date'] ?? $now->toDateString();
            $timeRaw = $validated['time'] ?? $now->format('H:i:s');
            $time = strlen($timeRaw) === 5 ? $timeRaw . ':00' : $timeRaw;
        }

        $type = in_array($validated['type'], ['in', 'masuk'], true) ? 'in' : 'out';
        $notes = $validated['notes'] ?? 'Sinkronisasi real-time dari aplikasi presensi';

        // 5. Pengaturan Jam Kerja
        $settings = DB::table('attendance_settings')->where('id', 1)->first();
        $startTime = $settings ? $settings->start_time : '08:00:00';
        $graceMinutes = $settings ? (int) $settings->late_grace_minutes : 60;
        $workdays = $settings ? (array_map('intval', json_decode($settings->workdays, true) ?? [1, 2, 3, 4, 5, 6])) : [1, 2, 3, 4, 5, 6];

        $dayOfWeek = Carbon::parse($date, config('app.timezone'))->dayOfWeekIso;
        if (! in_array($dayOfWeek, $workdays, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hari tersebut (' . $date . ') bukan merupakan hari kerja terjadwal.',
            ], 422);
        }

        $lateThreshold = Carbon::parse($date . ' ' . $startTime, config('app.timezone'))
            ->addMinutes($graceMinutes)
            ->format('H:i:s');

        $status = ($time > $lateThreshold) ? 'Terlambat' : 'Hadir';

        // 6. Simpan / Perbarui Presensi Secara Transaksional
        try {
            $result = DB::transaction(function () use ($date, $employee, $notes, $now, $status, $time, $type): array {
                $existing = DB::table('attendances')
                    ->where('employee_id', $employee->id)
                    ->whereDate('attendance_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($type === 'in') {
                    if ($existing) {
                        if (in_array($existing->status, ['Izin', 'Sakit'], true)) {
                            return ['action' => 'leave_conflict', 'record_id' => $existing->id, 'status' => $existing->status, 'check_in' => $existing->check_in, 'check_out' => $existing->check_out];
                        }

                        // Jika sudah ada tapi Alpha (otomatis), perbarui dengan jam masuk riil
                        if ($existing->status === 'Alpha' || empty($existing->check_in)) {
                            DB::table('attendances')->where('id', $existing->id)->update([
                                'check_in' => $time,
                                'status' => $status,
                                'notes' => $notes,
                                'updated_at' => $now,
                            ]);

                            return ['action' => 'updated', 'record_id' => $existing->id, 'status' => $status, 'check_in' => $time, 'check_out' => $existing->check_out];
                        }

                        // Sudah pernah absen masuk hari ini
                        return ['action' => 'already_checked_in', 'record_id' => $existing->id, 'status' => $existing->status, 'check_in' => $existing->check_in, 'check_out' => $existing->check_out];
                    }

                    // Rekam baru absen masuk
                    $id = DB::table('attendances')->insertGetId([
                        'employee_id' => $employee->id,
                        'attendance_date' => $date,
                        'check_in' => $time,
                        'check_out' => null,
                        'status' => $status,
                        'notes' => $notes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return ['action' => 'created', 'record_id' => $id, 'status' => $status, 'check_in' => $time, 'check_out' => null];
                }

                // Type === 'out' (Absen Pulang)
                if ($existing) {
                    if (in_array($existing->status, ['Izin', 'Sakit'], true)) {
                        return ['action' => 'leave_conflict', 'record_id' => $existing->id, 'status' => $existing->status, 'check_in' => $existing->check_in, 'check_out' => $existing->check_out];
                    }

                    DB::table('attendances')->where('id', $existing->id)->update([
                        'check_out' => $time,
                        'updated_at' => $now,
                    ]);

                    return ['action' => 'checked_out', 'record_id' => $existing->id, 'status' => $existing->status, 'check_in' => $existing->check_in, 'check_out' => $time];
                }

                // Absen pulang tanpa absen masuk tidak dapat dihitung sebagai
                // kehadiran penuh. Catat sebagai Alpha agar tidak masuk dalam
                // rekap kehadiran; HR dapat mengoreksi lewat fitur Koreksi
                // Absensi dengan alasan yang tersimpan di audit_logs.
                $id = DB::table('attendances')->insertGetId([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'check_in' => null,
                    'check_out' => $time,
                    'status' => 'Alpha',
                    'notes' => $notes . ' (Absen pulang tanpa jam masuk — ditetapkan Alpha otomatis)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return ['action' => 'created_out_only', 'record_id' => $id, 'status' => 'Alpha', 'check_in' => null, 'check_out' => $time];
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => 'Konflik data presensi: data tanggal ini sedang diproses.',
            ], 409);
        }

        if ($result['action'] === 'leave_conflict') {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan tercatat memiliki izin/sakit pada tanggal ini (' . $date . '). Presensi tidak dapat dicatat.',
                'data' => [
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'attendance_date' => $date,
                    'status' => $result['status'],
                ],
            ], 422);
        }

        // 7. Audit Log
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'module' => 'attendances',
            'action' => 'api_sync_' . $type,
            'record_id' => $result['record_id'],
            'after_data' => json_encode([
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'attendance_date' => $date,
                'type' => $type,
                'time' => $time,
                'status' => $result['status'],
                'ip' => $request->ip(),
            ]),
            'ip_address' => $request->ip(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $message = match ($result['action']) {
            'created' => 'Absen masuk berhasil dicatat secara real-time.',
            'updated' => 'Absen masuk berhasil diperbarui dari rekaman sebelumnya.',
            'already_checked_in' => 'Karyawan sudah tercatat absen masuk hari ini.',
            'checked_out' => 'Absen pulang berhasil dicatat secara real-time.',
            'created_out_only' => 'Absen pulang tercatat tanpa absen masuk; status ditetapkan Alpha otomatis.',
            default => 'Presensi berhasil dicatat.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'attendance_date' => $date,
                'check_in' => $result['check_in'],
                'check_out' => $result['check_out'],
                'status' => $result['status'],
                'time_recorded' => $time,
            ],
        ], 200);
    }
}
