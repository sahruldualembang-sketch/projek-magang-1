<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfitLossController extends Controller
{
    private const PAYROLL_CATEGORY_PATTERNS = [
        '%gaji%',
        '%penggajian%',
        '%payroll%',
        '%salar%',
        '%upah%',
        '%honor%',
        '%wage%',
    ];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasRole(['super_admin', 'director', 'finance']), 403);

        $filters = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d'],
            'print' => ['sometimes', 'boolean'],
        ]);
        $isPrint = $request->boolean('print');

        $start = $filters['start'] ?? now()->startOfMonth()->toDateString();
        $end = $filters['end'] ?? now()->endOfMonth()->toDateString();

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
            ]);
        }

        $payrollPeriodStart = substr($start, 0, 7);
        $payrollPeriodEnd = substr($end, 0, 7);

        $revenue = (float) DB::table('orders')
            ->where('status', 'Selesai')
            ->whereBetween('order_date', [$start, $end])
            ->sum('total_amount');

        $incomeQuery = DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.status', 'Selesai')
            ->whereBetween('orders.order_date', [$start, $end])
            ->select([
                'orders.order_number',
                'orders.order_date',
                'clients.company_name as client_name',
                'orders.description',
                'orders.total_amount',
                'orders.status',
            ]);
        $incomeQuery->orderByDesc('orders.order_date')->orderByDesc('orders.id');
        $incomeRows = $isPrint
            ? $incomeQuery->get()
            : $incomeQuery->paginate(10, ['*'], 'income_page')->withQueryString();

        $expenses = (float) $this->operatingExpensesQuery($start, $end)->sum('amount');

        $expenseRowsQuery = $this->operatingExpensesQuery($start, $end)
            ->select([
                'expense_number',
                'expense_date',
                'category',
                'description',
                'amount',
                'payment_method',
                'status',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');
        $expenseRows = $isPrint
            ? $expenseRowsQuery->get()
            : $expenseRowsQuery->paginate(10, ['*'], 'expense_page')->withQueryString();

        $expenseBreakdown = $this->operatingExpensesQuery($start, $end)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        $potentialPayrollExpenseQuery = $this->potentialPayrollExpensesQuery($start, $end);
        $potentialPayrollExpenseTotal = (float) (clone $potentialPayrollExpenseQuery)->sum('amount');
        $potentialPayrollExpenseCount = (clone $potentialPayrollExpenseQuery)->count();
        $potentialPayrollExpenseRowsQuery = $potentialPayrollExpenseQuery
            ->select([
                'expense_number',
                'expense_date',
                'category',
                'description',
                'amount',
                'payment_method',
                'status',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');
        $potentialPayrollExpenseRows = $isPrint
            ? $potentialPayrollExpenseRowsQuery->get()
            : $potentialPayrollExpenseRowsQuery->paginate(10, ['*'], 'payroll_expense_page')->withQueryString();

        $payroll = (float) DB::table('payrolls')
            ->whereIn('status', ['Disetujui', 'Dibayar'])
            ->whereBetween('payroll_period', [$payrollPeriodStart, $payrollPeriodEnd])
            ->sum('net_salary');

        $payrollRowsQuery = DB::table('payrolls')
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->whereIn('payrolls.status', ['Disetujui', 'Dibayar'])
            ->whereBetween('payrolls.payroll_period', [$payrollPeriodStart, $payrollPeriodEnd])
            ->select([
                'payrolls.payroll_period',
                'employees.name as employee_name',
                'payrolls.net_salary',
                'payrolls.status',
            ])
            ->orderByDesc('payrolls.payroll_period')
            ->orderBy('employees.name');
        $payrollRows = $isPrint
            ? $payrollRowsQuery->get()
            : $payrollRowsQuery->paginate(10, ['*'], 'payroll_page')->withQueryString();

        $surplus = $revenue - $expenses - $payroll;

        return view('modules.profit-loss', compact(
            'start',
            'end',
            'payrollPeriodStart',
            'payrollPeriodEnd',
            'isPrint',
            'revenue',
            'expenses',
            'payroll',
            'surplus',
            'incomeRows',
            'expenseRows',
            'expenseBreakdown',
            'potentialPayrollExpenseTotal',
            'potentialPayrollExpenseCount',
            'potentialPayrollExpenseRows',
            'payrollRows'
        ));
    }

    private function operatingExpensesQuery(string $start, string $end): Builder
    {
        return $this->eligibleExpensesQuery($start, $end)
            ->where(function (Builder $query): void {
                foreach (self::PAYROLL_CATEGORY_PATTERNS as $pattern) {
                    $query->whereRaw('LOWER(category) NOT LIKE ?', [$pattern]);
                }
            });
    }

    private function potentialPayrollExpensesQuery(string $start, string $end): Builder
    {
        return $this->eligibleExpensesQuery($start, $end)
            ->where(function (Builder $query): void {
                foreach (self::PAYROLL_CATEGORY_PATTERNS as $pattern) {
                    $query->orWhereRaw('LOWER(category) LIKE ?', [$pattern]);
                }
            });
    }

    private function eligibleExpensesQuery(string $start, string $end): Builder
    {
        return DB::table('expenses')
            ->whereIn('status', ['Disetujui', 'Dibayar'])
            ->whereBetween('expense_date', [$start, $end]);
    }
}
