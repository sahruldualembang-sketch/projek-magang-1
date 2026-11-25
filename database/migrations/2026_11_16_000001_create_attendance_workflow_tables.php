<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table): void {
            $table->id();
            $table->time('start_time');
            $table->time('weekday_end_time');
            $table->time('saturday_end_time');
            $table->unsignedSmallInteger('late_grace_minutes');
            $table->json('workdays');
            $table->boolean('automatic_alpha')->default(true);
            $table->timestamps();
        });

        DB::table('attendance_settings')->insert([
            'id' => 1,
            'start_time' => '08:00:00',
            'weekday_end_time' => '16:30:00',
            'saturday_end_time' => '15:00:00',
            'late_grace_minutes' => 60,
            'workdays' => json_encode([1, 2, 3, 4, 5, 6]),
            'automatic_alpha' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('attendance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('request_type', 10);
            $table->text('reason');
            $table->string('status', 10)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['status', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('attendance_settings');
    }
};
