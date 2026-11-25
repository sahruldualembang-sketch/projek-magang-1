<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $isStaff = $user->role === 'staff';
        $stats = [];
        $summaryCards = [];
        $monthlyTrend = [];
        $maxMonthlyValue = 0;
        $recentOrders = collect();

        $hasOrders = $user->hasRole(config('simlji.orders.roles', []));
        $hasExpenses = $user->hasRole(config('simlji.expenses.roles', []));
        $hasInvoices = $user->hasRole(config('simlji.invoices.roles', []));
        $hasContracts = $user->hasRole(config('simlji.contracts.roles', []));

        if ($isStaff) {
            abort_unless($user->employee_id, 403, 'Akun karyawan belum ditautkan ke data karyawan. Hubungi HRD.');

            $stats['my_attendance'] = DB::table('attendances')
                ->where('employee_id', $user->employee_id)
                ->whereMonth('attendance_date', now()->month)
                ->whereYear('attendance_date', now()->year)
                ->count();

            $stats['my_activities'] = DB::table('activities')
                ->where('employee_id', $user->employee_id)
                ->where('status', '!=', 'Selesai')
                ->count();
        } else {
            // Ringkasan hanya dihitung untuk modul yang memang boleh diakses role aktif.
            if ($user->hasRole(config('simlji.employees.roles', []))) {
                $stats['employees'] = DB::table('employees')->count();
                $summaryCards[] = [
                    'label' => 'Total Karyawan',
                    'value' => $stats['employees'],
                    'caption' => 'Data karyawan terdaftar',
                    'icon' => '♙',
                    'variant' => '',
                ];
            }

            if ($user->hasRole(config('simlji.attendances.roles', []))) {
                $attendanceToday = DB::table('attendances')
                    ->whereDate('attendance_date', now()->toDateString())
                    ->whereIn('status', ['Hadir', 'Terlambat'])
                    ->count();

                $summaryCards[] = [
                    'label' => 'Kehadiran Hari Ini',
                    'value' => $attendanceToday,
                    'caption' => 'Karyawan hadir atau terlambat',
                    'icon' => '◷',
                    'variant' => 'green',
                ];
            }

            if ($user->hasRole(config('simlji.activities.roles', []))) {
                $openActivities = DB::table('activities')
                    ->where('status', '!=', 'Selesai')
                    ->count();

                $summaryCards[] = [
                    'label' => 'Aktivitas Berjalan',
                    'value' => $openActivities,
                    'caption' => 'Belum selesai atau perlu tindak lanjut',
                    'icon' => '☷',
                    'variant' => 'amber',
                ];
            }

            if ($user->hasRole(config('simlji.clients.roles', []))) {
                $stats['clients'] = DB::table('clients')->count();
                $summaryCards[] = [
                    'label' => 'Total Klien',
                    'value' => $stats['clients'],
                    'caption' => 'Data klien perusahaan',
                    'icon' => '♧',
                    'variant' => 'green',
                ];
            }

            if ($hasOrders) {
                $stats['orders'] = DB::table('orders')->count();
                $summaryCards[] = [
                    'label' => 'Total Pesanan',
                    'value' => $stats['orders'],
                    'caption' => 'Seluruh status pesanan',
                    'icon' => '▤',
                    'variant' => 'amber',
                ];

                $stats['monthly_orders'] = DB::table('orders')
                    ->whereMonth('order_date', now()->month)
                    ->whereYear('order_date', now()->year)
                    ->count();

                $stats['monthly_order_value'] = (float) DB::table('orders')
                    ->whereMonth('order_date', now()->month)
                    ->whereYear('order_date', now()->year)
                    ->sum('total_amount');

                $recentOrders = DB::table('orders')
                    ->join('clients', 'clients.id', '=', 'orders.client_id')
                    ->select(
                        'orders.order_number',
                        'clients.company_name',
                        'orders.order_date',
                        'orders.total_amount',
                        'orders.status'
                    )
                    ->latest('orders.created_at')
                    ->limit(6)
                    ->get();

                $firstMonth = now()->copy()->startOfMonth()->subMonths(5);

                for ($offset = 0; $offset < 6; $offset++) {
                    $month = $firstMonth->copy()->addMonths($offset);
                    $start = $month->copy()->startOfMonth()->toDateString();
                    $end = $month->copy()->endOfMonth()->toDateString();

                    $value = (float) DB::table('orders')
                        ->whereBetween('order_date', [$start, $end])
                        ->sum('total_amount');

                    $monthlyTrend[] = [
                        'label' => $month->translatedFormat('M'),
                        'value' => $value,
                    ];

                    $maxMonthlyValue = max($maxMonthlyValue, $value);
                }
            }

            if ($hasExpenses) {
                $stats['pending_expenses'] = DB::table('expenses')
                    ->whereIn('status', ['Draft', 'Menunggu Persetujuan'])
                    ->count();

                $summaryCards[] = [
                    'label' => 'Pengeluaran Perlu Ditinjau',
                    'value' => $stats['pending_expenses'],
                    'caption' => 'Menunggu pemeriksaan atau persetujuan',
                    'icon' => '↗',
                    'variant' => 'red',
                ];
            }

            if ($hasInvoices) {
                $stats['pending_invoices'] = DB::table('invoices')
                    ->whereIn('status', ['Terkirim', 'Jatuh Tempo'])
                    ->count();
            }

            if ($hasContracts) {
                $stats['expiring_contracts'] = DB::table('contracts')
                    ->where('status', 'Aktif')
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [
                        now()->toDateString(),
                        now()->copy()->addDays(30)->toDateString(),
                    ])
                    ->count();
            }
        }

        return view('dashboard.index', compact(
            'stats',
            'summaryCards',
            'isStaff',
            'hasOrders',
            'recentOrders',
            'monthlyTrend',
            'maxMonthlyValue'
        ));
    }
}
