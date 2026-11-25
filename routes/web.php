<?php
use App\Http\Controllers\Auth\AuthenticatedSessionController; use App\Http\Controllers\DashboardController; use App\Http\Controllers\ModuleController; use App\Http\Controllers\ProfitLossController; use App\Http\Controllers\UserManagementController; use Illuminate\Support\Facades\Route;
Route::get('/login',[AuthenticatedSessionController::class,'create'])->name('login'); Route::post('/login',[AuthenticatedSessionController::class,'store'])->middleware('throttle:5,1');
Route::middleware('auth')->group(function(){Route::post('/logout',[AuthenticatedSessionController::class,'destroy'])->name('logout');Route::get('/',DashboardController::class)->name('dashboard');Route::get('/laporan-laba-rugi',[ProfitLossController::class,'index'])->name('profit-loss');Route::middleware('role:super_admin')->prefix('pengguna')->name('users.')->group(function(){Route::get('/',[UserManagementController::class,'index'])->name('index');Route::get('/buat',[UserManagementController::class,'create'])->name('create');Route::post('/',[UserManagementController::class,'store'])->name('store');Route::get('/{user}/ubah',[UserManagementController::class,'edit'])->name('edit');Route::put('/{user}',[UserManagementController::class,'update'])->name('update');});Route::get('/modul/{module}/{id}/file',[ModuleController::class,'download'])->name('modules.file');Route::get('/modul/{module}',[ModuleController::class,'index'])->name('modules.index');Route::get('/modul/{module}/buat',[ModuleController::class,'create'])->name('modules.create');Route::post('/modul/{module}',[ModuleController::class,'store'])->name('modules.store');Route::get('/modul/{module}/{id}/ubah',[ModuleController::class,'edit'])->name('modules.edit');Route::put('/modul/{module}/{id}',[ModuleController::class,'update'])->name('modules.update');Route::delete('/modul/{module}/{id}',[ModuleController::class,'destroy'])->name('modules.destroy');});

Route::middleware('auth')->group(function (): void {
    Route::post('/modul/{module}/{id}/ajukan', [ModuleController::class, 'submit'])
        ->whereNumber('id')
        ->name('modules.submit');
    Route::post('/modul/{module}/{id}/setujui', [ModuleController::class, 'approve'])
        ->whereNumber('id')
        ->name('modules.approve');
    Route::post('/modul/{module}/{id}/bayar', [ModuleController::class, 'pay'])
        ->whereNumber('id')
        ->name('modules.pay');
    Route::post('/modul/{module}/{id}/kembalikan', [ModuleController::class, 'returnToDraft'])
        ->whereNumber('id')
        ->name('modules.return');
    Route::get('/modul/activities/{id}/progres', [ModuleController::class, 'editProgress'])
        ->whereNumber('id')
        ->name('activities.progress.edit');
    Route::put('/modul/activities/{id}/progres', [ModuleController::class, 'updateProgress'])
        ->whereNumber('id')
        ->name('activities.progress.update');
});

Route::middleware(['auth', 'role:staff'])->group(function (): void {
    Route::post('/absensi/masuk', [\App\Http\Controllers\AttendanceController::class, 'clockIn'])
        ->middleware('throttle:10,1')
        ->name('attendance.clock-in');
    Route::post('/absensi/pulang', [\App\Http\Controllers\AttendanceController::class, 'clockOut'])
        ->middleware('throttle:10,1')
        ->name('attendance.clock-out');
    Route::post('/absensi/pengajuan', [\App\Http\Controllers\AttendanceController::class, 'submitLeaveRequest'])
        ->name('attendance.leave-request');
});

Route::middleware(['auth', 'role:hr,super_admin'])->group(function (): void {
    Route::get('/modul/attendances/{id}/correction', [\App\Http\Controllers\AttendanceController::class, 'editCorrection'])
        ->whereNumber('id')
        ->name('attendance.correction.edit');
    Route::put('/modul/attendances/{id}/correction', [\App\Http\Controllers\AttendanceController::class, 'correct'])
        ->whereNumber('id')
        ->name('attendance.correction.update');
    Route::post('/absensi/pengajuan/{id}/setujui', [\App\Http\Controllers\AttendanceController::class, 'approveLeaveRequest'])
        ->whereNumber('id')
        ->name('attendance.leave-approve');
    Route::post('/absensi/pengajuan/{id}/tolak', [\App\Http\Controllers\AttendanceController::class, 'rejectLeaveRequest'])
        ->whereNumber('id')
        ->name('attendance.leave-reject');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('pengaturan')->name('settings.')->group(function (): void {
    Route::get('/absensi', [\App\Http\Controllers\AttendanceController::class, 'editSettings'])
        ->name('attendance');
    Route::put('/absensi', [\App\Http\Controllers\AttendanceController::class, 'updateSettings'])
        ->name('attendance.update');
});
