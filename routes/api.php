<?php

use App\Http\Controllers\Api\AttendanceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SIM-LJI
|--------------------------------------------------------------------------
| Rute API untuk integrasi mesin-ke-mesin / aplikasi eksternal.
| Dilindungi oleh pembatasan laju (rate limiting) dan verifikasi API Key.
*/

Route::prefix('v1')->group(function (): void {
    Route::post('/attendance/record', [AttendanceApiController::class, 'record'])
        ->middleware('throttle:120,1')
        ->name('api.attendance.record');

    Route::get('/attendance/status', [AttendanceApiController::class, 'status'])
        ->name('api.attendance.status');
});
