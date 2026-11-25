<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\DB; use Tests\TestCase;
class ModuleAccessTest extends TestCase
{
 use RefreshDatabase;
 public function test_employee_name_search_finds_attendance_payroll_and_activities(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'search-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  $employee=DB::table('employees')->insertGetId(['employee_code'=>'DEMO-KRY-008','name'=>'Rina Puspita','created_at'=>now(),'updated_at'=>now()]);
  DB::table('attendances')->insert([
   ['employee_id'=>$employee,'attendance_date'=>'2026-11-03','status'=>'Terlambat','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'attendance_date'=>'2026-11-08','status'=>'Hadir','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'attendance_date'=>'2026-11-12','status'=>'Terlambat','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('activities')->insert(['employee_id'=>$employee,'title'=>'Laporan stok Rina','activity_date'=>'2026-11-16','progress'=>100,'status'=>'Selesai','created_at'=>now(),'updated_at'=>now()]);
  DB::table('payrolls')->insert([
   ['employee_id'=>$employee,'payroll_period'=>'2026-01','basic_salary'=>1000000,'net_salary'=>1000000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'payroll_period'=>'2026-06','basic_salary'=>1000000,'net_salary'=>1000000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'payroll_period'=>'2026-07','basic_salary'=>1000000,'net_salary'=>1000000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'payroll_period'=>'2026-11','basic_salary'=>1000000,'net_salary'=>1000000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/modul/attendances?'.http_build_query([
    'nama'=>'Rina Puspita',
    'tanggal_mulai'=>'2026-11-01',
    'tanggal_akhir'=>'2026-11-10',
    'status'=>'Terlambat',
   ]))
   ->assertOk()
   ->assertSee('03/11/2026')
   ->assertDontSee('08/11/2026')
   ->assertDontSee('12/11/2026');

  $this->get('/modul/activities?q='.urlencode('Rina Puspita'))
   ->assertOk()
   ->assertSee('Laporan stok Rina');

  $this->get('/modul/payrolls?q='.urlencode('Rina Puspita'))
   ->assertOk()
   ->assertSee('2026-11');

  $this->get('/modul/payrolls?'.http_build_query([
   'nama'=>'Rina Puspita',
   'periode_dari'=>'2026-01',
   'periode_sampai'=>'2026-06',
  ]))
   ->assertOk()
   ->assertSee('2026-01')
   ->assertSee('2026-06')
   ->assertDontSee('2026-07');

  $this->get('/modul/attendances?tanggal_mulai=2026-11-10&tanggal_akhir=2026-11-01')
   ->assertRedirect(route('modules.index', 'attendances'))
   ->assertSessionHasErrors('tanggal_akhir');
 }
 public function test_employee_and_dynamic_asset_filters_are_applied(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'filters-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  DB::table('employees')->insert([
   ['employee_code'=>'EMP-FILTER-1','name'=>'Rina Filter','department'=>'Operasional','employment_status'=>'Kontrak','join_date'=>'2020-05-10','created_at'=>now(),'updated_at'=>now()],
   ['employee_code'=>'EMP-FILTER-2','name'=>'Budi Filter','department'=>'Keuangan','employment_status'=>'Tetap','join_date'=>'2021-05-10','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('assets')->insert([
   ['asset_code'=>'AST-FILTER-1','name'=>'Laptop Uji','category'=>'Komputer','asset_condition'=>'Baik','status'=>'Tersedia','created_at'=>now(),'updated_at'=>now()],
   ['asset_code'=>'AST-FILTER-2','name'=>'Printer Uji','category'=>'Periferal','asset_condition'=>'Baik','status'=>'Tersedia','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/modul/employees?'.http_build_query([
    'q'=>'Rina Filter',
    'department'=>'Operasional',
    'employment_status'=>'Kontrak',
    'join_date_from'=>'2020-01-01',
    'join_date_to'=>'2020-12-31',
   ]))
   ->assertOk()
   ->assertSee('EMP-FILTER-1')
   ->assertDontSee('EMP-FILTER-2');

  $this->get('/modul/assets?'.http_build_query([
   'category'=>'Komputer',
   'asset_condition'=>'Baik',
   'status'=>'Tersedia',
  ]))
   ->assertOk()
   ->assertSee('AST-FILTER-1')
   ->assertDontSee('AST-FILTER-2');
 }
 public function test_employee_date_filters_accept_indonesian_format(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'date-filter-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  DB::table('employees')->insert([
   ['employee_code'=>'EMP-DATE-1','name'=>'Rina Tanggal','department'=>'Operasional','employment_status'=>'Tetap','join_date'=>'2020-05-10','created_at'=>now(),'updated_at'=>now()],
   ['employee_code'=>'EMP-DATE-2','name'=>'Budi Tanggal','department'=>'Operasional','employment_status'=>'Tetap','join_date'=>'2021-05-10','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/modul/employees?'.http_build_query([
    'department'=>'Operasional',
    'join_date_from'=>'10/05/2020',
    'join_date_to'=>'31/05/2020',
   ]))
   ->assertOk()
   ->assertSee('EMP-DATE-1')
   ->assertDontSee('EMP-DATE-2');
 }
 public function test_client_status_filter_works_without_date_ranges(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'client-filter-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  DB::table('clients')->insert([
   ['client_code'=>'CL-FILTER-ACTIVE','company_name'=>'Klien Aktif Uji','status'=>'Aktif','created_at'=>now(),'updated_at'=>now()],
   ['client_code'=>'CL-FILTER-PROSPECT','company_name'=>'Klien Prospek Uji','status'=>'Prospek','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/modul/clients?status=Aktif')
   ->assertOk()
   ->assertSee('CL-FILTER-ACTIVE')
   ->assertDontSee('CL-FILTER-PROSPECT');
 }
 public function test_profit_loss_report_reconciles_details_and_excludes_invoice_and_payroll_duplicates(): void
 {
  $user=User::create(['name'=>'Finance Admin','email'=>'profit-loss-finance@example.test','password'=>'LongPassphrase!2026','role'=>'finance','is_active'=>true]);
  $client=DB::table('clients')->insertGetId([
   'client_code'=>'CL-REPORT-1',
   'company_name'=>'Klien Laporan Uji',
   'status'=>'Aktif',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  $employee=DB::table('employees')->insertGetId([
   'employee_code'=>'EMP-REPORT-1',
   'name'=>'Karyawan Laporan Uji',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  $draftEmployee=DB::table('employees')->insertGetId([
   'employee_code'=>'EMP-REPORT-DRAFT',
   'name'=>'Karyawan Draft Uji',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  DB::table('orders')->insert([
   ['order_number'=>'ORD-REPORT-1','client_id'=>$client,'order_date'=>'2026-01-15','description'=>'Pendapatan Januari','quantity'=>1,'unit_price'=>1000000,'total_amount'=>1000000,'status'=>'Selesai','created_at'=>now(),'updated_at'=>now()],
   ['order_number'=>'ORD-REPORT-2','client_id'=>$client,'order_date'=>'2026-02-14','description'=>'Pendapatan Februari','quantity'=>1,'unit_price'=>500000,'total_amount'=>500000,'status'=>'Selesai','created_at'=>now(),'updated_at'=>now()],
   ['order_number'=>'ORD-REPORT-OUT','client_id'=>$client,'order_date'=>'2026-02-15','description'=>'Di luar periode','quantity'=>1,'unit_price'=>900000,'total_amount'=>900000,'status'=>'Selesai','created_at'=>now(),'updated_at'=>now()],
   ['order_number'=>'ORD-REPORT-PENDING','client_id'=>$client,'order_date'=>'2026-01-20','description'=>'Belum selesai','quantity'=>1,'unit_price'=>800000,'total_amount'=>800000,'status'=>'Menunggu','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('invoices')->insert([
   ['invoice_number'=>'INV-REPORT-PAID','client_id'=>$client,'order_id'=>DB::table('orders')->where('order_number','ORD-REPORT-1')->value('id'),'issue_date'=>'2026-01-16','subtotal'=>1000000,'tax_amount'=>110000,'total_amount'=>1110000,'status'=>'Terbayar','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('expenses')->insert([
   ['expense_number'=>'EXP-REPORT-OPS-1','expense_date'=>'2026-01-15','category'=>'Operasional','description'=>'Beban Januari','amount'=>100000,'payment_method'=>'Transfer','status'=>'Disetujui','created_at'=>now(),'updated_at'=>now()],
   ['expense_number'=>'EXP-REPORT-OPS-2','expense_date'=>'2026-02-14','category'=>'Transportasi','description'=>'Beban Februari','amount'=>50000,'payment_method'=>'Tunai','status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['expense_number'=>'EXP-REPORT-OUT','expense_date'=>'2026-02-15','category'=>'Operasional','description'=>'Di luar periode','amount'=>200000,'payment_method'=>'Transfer','status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['expense_number'=>'EXP-REPORT-DRAFT','expense_date'=>'2026-01-20','category'=>'Operasional','description'=>'Belum disetujui','amount'=>300000,'payment_method'=>'Transfer','status'=>'Draft','created_at'=>now(),'updated_at'=>now()],
   ['expense_number'=>'EXP-REPORT-PAYROLL','expense_date'=>'2026-01-20','category'=>'Gaji Karyawan','description'=>'Potensi duplikasi payroll','amount'=>600000,'payment_method'=>'Transfer','status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('payrolls')->insert([
   ['employee_id'=>$employee,'payroll_period'=>'2026-01','basic_salary'=>600000,'allowance'=>0,'deduction'=>0,'net_salary'=>600000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'payroll_period'=>'2026-02','basic_salary'=>700000,'allowance'=>0,'deduction'=>0,'net_salary'=>700000,'status'=>'Disetujui','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$employee,'payroll_period'=>'2026-03','basic_salary'=>800000,'allowance'=>0,'deduction'=>0,'net_salary'=>800000,'status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['employee_id'=>$draftEmployee,'payroll_period'=>'2026-01','basic_salary'=>900000,'allowance'=>0,'deduction'=>0,'net_salary'=>900000,'status'=>'Draft','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/laporan-laba-rugi?start=2026-01-15&end=2026-02-14')
   ->assertOk()
   ->assertSee('Rp 1.500.000')
   ->assertSee('Rp 150.000')
   ->assertSee('Rp 1.300.000')
   ->assertSee('Rp 50.000')
   ->assertSee('ORD-REPORT-1')
   ->assertSee('ORD-REPORT-2')
   ->assertDontSee('ORD-REPORT-OUT')
   ->assertDontSee('ORD-REPORT-PENDING')
   ->assertDontSee('INV-REPORT-PAID')
   ->assertSee('EXP-REPORT-OPS-1')
   ->assertSee('EXP-REPORT-OPS-2')
   ->assertDontSee('EXP-REPORT-OUT')
   ->assertDontSee('EXP-REPORT-DRAFT')
   ->assertSee('EXP-REPORT-PAYROLL')
   ->assertSee('Karyawan Laporan Uji')
   ->assertDontSee('2026-03')
   ->assertSee('2026-01 – 2026-02');
 }
 public function test_profit_loss_detail_tables_are_paginated_with_period_filters_preserved(): void
 {
  $user=User::create(['name'=>'Finance Admin','email'=>'profit-loss-pages@example.test','password'=>'LongPassphrase!2026','role'=>'finance','is_active'=>true]);
  $client=DB::table('clients')->insertGetId([
   'client_code'=>'CL-REPORT-PAGES',
   'company_name'=>'Klien Paginasi',
   'status'=>'Aktif',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  $employee=DB::table('employees')->insertGetId([
   'employee_code'=>'EMP-REPORT-PAGES',
   'name'=>'Karyawan Paginasi',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);

  for ($month = 1; $month <= 11; $month++) {
   $period = sprintf('2026-%02d', $month);
   $date = $period . '-15';
   DB::table('orders')->insert([
    'order_number'=>'ORD-REPORT-PAGE-'.$month,
    'client_id'=>$client,
    'order_date'=>$date,
    'description'=>'Pesanan paginasi',
    'quantity'=>1,
    'unit_price'=>1000,
    'total_amount'=>1000,
    'status'=>'Selesai',
    'created_at'=>now(),
    'updated_at'=>now(),
   ]);
   DB::table('expenses')->insert([
    'expense_number'=>'EXP-REPORT-PAGE-'.$month,
    'expense_date'=>$date,
    'category'=>'Operasional',
    'description'=>'Pengeluaran paginasi',
    'amount'=>100,
    'payment_method'=>'Transfer',
    'status'=>'Dibayar',
    'created_at'=>now(),
    'updated_at'=>now(),
   ]);
   DB::table('payrolls')->insert([
    'employee_id'=>$employee,
    'payroll_period'=>$period,
    'basic_salary'=>1000,
    'allowance'=>0,
    'deduction'=>0,
    'net_salary'=>1000,
    'status'=>'Dibayar',
    'created_at'=>now(),
    'updated_at'=>now(),
   ]);
  }

  $this->actingAs($user)
   ->get('/laporan-laba-rugi?start=2026-01-01&end=2026-11-30')
   ->assertOk()
   ->assertSee('income_page=2')
   ->assertSee('expense_page=2')
   ->assertSee('payroll_page=2')
   ->assertSee('2026-01-01')
   ->assertSee('2026-11-30');

  $this->get('/laporan-laba-rugi?start=2026-01-01&end=2026-11-30&print=1')
   ->assertOk()
   ->assertSee('ORD-REPORT-PAGE-1')
   ->assertSee('EXP-REPORT-PAGE-1')
   ->assertSee('2026-01')
   ->assertDontSee('income_page=2')
   ->assertDontSee('expense_page=2')
   ->assertDontSee('payroll_page=2');
 }
 public function test_invoice_and_expense_filters_combine_relations_status_and_dates(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'finance-filters@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  $client=DB::table('clients')->insertGetId([
   'client_code'=>'CL-FILTER-1',
   'company_name'=>'Klien Filter',
   'status'=>'Aktif',
   'created_at'=>now(),
   'updated_at'=>now(),
  ]);
  DB::table('invoices')->insert([
   ['invoice_number'=>'INV-FILTER-DRAFT','client_id'=>$client,'issue_date'=>'2026-11-05','subtotal'=>100000,'tax_amount'=>0,'total_amount'=>100000,'status'=>'Draft','created_at'=>now(),'updated_at'=>now()],
   ['invoice_number'=>'INV-FILTER-SENT','client_id'=>$client,'issue_date'=>'2026-11-06','subtotal'=>100000,'tax_amount'=>0,'total_amount'=>100000,'status'=>'Terkirim','created_at'=>now(),'updated_at'=>now()],
  ]);
  DB::table('expenses')->insert([
   ['expense_number'=>'EXP-FILTER-IN','expense_date'=>'2026-11-05','category'=>'Operasional','description'=>'Dalam rentang','amount'=>50000,'payment_method'=>'Transfer','status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
   ['expense_number'=>'EXP-FILTER-OUT','expense_date'=>'2026-11-15','category'=>'Operasional','description'=>'Di luar rentang','amount'=>50000,'payment_method'=>'Transfer','status'=>'Dibayar','created_at'=>now(),'updated_at'=>now()],
  ]);

  $this->actingAs($user)
   ->get('/modul/invoices?'.http_build_query([
    'q'=>'Klien Filter',
    'nama_klien'=>'Klien Filter',
    'tanggal_mulai'=>'2026-11-01',
    'tanggal_akhir'=>'2026-11-10',
    'status'=>'Draft',
   ]))
   ->assertOk()
   ->assertSee('INV-FILTER-DRAFT')
   ->assertDontSee('INV-FILTER-SENT');

  $this->get('/modul/expenses?'.http_build_query([
   'tanggal_mulai'=>'2026-11-01',
   'tanggal_akhir'=>'2026-11-10',
   'category'=>'Operasional',
   'payment_method'=>'Transfer',
   'status'=>'Dibayar',
  ]))
   ->assertOk()
   ->assertSee('EXP-FILTER-IN')
   ->assertDontSee('EXP-FILTER-OUT');
 }
 public function test_user_filters_search_employee_role_and_account_status(): void
 {
  $admin=User::create(['name'=>'Admin','email'=>'user-filters-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  $employee=DB::table('employees')->insertGetId(['employee_code'=>'EMP-USER-1','name'=>'Rina Pengguna','created_at'=>now(),'updated_at'=>now()]);
  User::create(['name'=>'Akun Rina','email'=>'rina-user@example.test','password'=>'LongPassphrase!2026','role'=>'staff','employee_id'=>$employee,'is_active'=>false]);
  User::create(['name'=>'Staff Aktif','email'=>'active-user@example.test','password'=>'LongPassphrase!2026','role'=>'staff','is_active'=>true]);

  $this->actingAs($admin)
   ->get('/pengguna?'.http_build_query([
    'q'=>'Rina Pengguna',
    'role'=>'staff',
    'is_active'=>'0',
   ]))
   ->assertOk()
   ->assertSee('rina-user@example.test')
   ->assertDontSee('active-user@example.test');
 }
 public function test_staff_cannot_open_client_records(): void { $user=User::create(['name'=>'Staff','email'=>'staff@example.test','password'=>'LongPassphrase!2026','role'=>'staff','is_active'=>true]); $this->actingAs($user)->get('/modul/clients')->assertForbidden(); }
 public function test_super_admin_can_open_dashboard(): void { $user=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]); $this->actingAs($user)->get('/')->assertOk(); }
 public function test_super_admin_can_open_each_module_list_and_create_form(): void
 {
  $user=User::create(['name'=>'Admin','email'=>'module-smoke-admin@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]);
  $this->actingAs($user);

  foreach (array_keys(config('simlji')) as $module) {
   if ($module === 'profit-loss') {
    continue;
   }

   $this->get(route('modules.index', $module))->assertOk();
   $this->get(route('modules.create', $module))->assertOk();
  }

  $this->get(route('profit-loss'))->assertOk();
 }
 public function test_order_total_is_calculated_on_the_server(): void { $user=User::create(['name'=>'Admin','email'=>'sales@example.test','password'=>'LongPassphrase!2026','role'=>'super_admin','is_active'=>true]); $client=DB::table('clients')->insertGetId(['client_code'=>'CL-001','company_name'=>'Klien Uji','status'=>'Aktif','created_at'=>now(),'updated_at'=>now()]); $this->actingAs($user)->post('/modul/orders',['order_number'=>'ORD-001','client_id'=>$client,'order_date'=>now()->toDateString(),'description'=>'Paket layanan uji','quantity'=>3,'unit_price'=>125000,'status'=>'Menunggu'])->assertRedirect('/modul/orders'); $this->assertDatabaseHas('orders',['order_number'=>'ORD-001','total_amount'=>'375000.00']); }

 public function test_staff_can_edit_own_activity_progress(): void
 {
  // Setup: buat employee + user staff yang terhubung
  $employee = DB::table('employees')->insertGetId([
   'employee_code' => 'EMP-STAFF-ACT-' . uniqid(),
   'name' => 'Staff Test Aktivitas',
   'created_at' => now(),
   'updated_at' => now(),
  ]);
  $staff = \App\Models\User::create([
   'name' => 'Staff Aktivitas',
   'email' => 'staff-activity-' . uniqid() . '@example.test',
   'password' => 'LongPassphrase!2026',
   'role' => 'staff',
   'employee_id' => $employee,
   'is_active' => true,
  ]);

  // Buat aktivitas milik staff ini
  $activityId = DB::table('activities')->insertGetId([
   'employee_id' => $employee,
   'title' => 'Tugas Pengembangan Fitur',
   'description' => 'Deskripsi tugas.',
   'activity_date' => '2026-11-20',
   'progress' => 30,
   'status' => 'Proses',
   'created_at' => now(),
   'updated_at' => now(),
  ]);

  // Staff harus bisa membuka halaman edit aktivitas miliknya
  $this->actingAs($staff)
   ->get(route('modules.edit', ['module' => 'activities', 'id' => $activityId]))
   ->assertOk();

  // Staff harus bisa menyimpan perubahan progress & status
  $this->put(route('modules.update', ['module' => 'activities', 'id' => $activityId]), [
   'employee_id' => $employee,
   'title' => 'Tugas Pengembangan Fitur',
   'description' => 'Deskripsi tugas.',
   'activity_date' => '2026-11-20',
   'progress' => 80,
   'status' => 'Selesai',
  ])->assertRedirect(route('modules.index', 'activities'));

  // Verifikasi data tersimpan di database
  $this->assertDatabaseHas('activities', [
   'id' => $activityId,
   'progress' => 80,
   'status' => 'Selesai',
  ]);
 }

 public function test_staff_cannot_edit_other_modules(): void
 {
  // Setup: staff tidak boleh edit karyawan, pengeluaran, dsb
  $employee = DB::table('employees')->insertGetId([
   'employee_code' => 'EMP-STAFF-BLK-' . uniqid(),
   'name' => 'Staff Blokir Test',
   'created_at' => now(),
   'updated_at' => now(),
  ]);
  $staff = \App\Models\User::create([
   'name' => 'Staff Blokir',
   'email' => 'staff-block-' . uniqid() . '@example.test',
   'password' => 'LongPassphrase!2026',
   'role' => 'staff',
   'employee_id' => $employee,
   'is_active' => true,
  ]);

  // Buat data employee lain untuk dicoba diedit staff
  $otherEmployee = DB::table('employees')->insertGetId([
   'employee_code' => 'EMP-OTHER-' . uniqid(),
   'name' => 'Karyawan Lain',
   'created_at' => now(),
   'updated_at' => now(),
  ]);

  $this->actingAs($staff)
   ->get(route('modules.edit', ['module' => 'employees', 'id' => $otherEmployee]))
   ->assertForbidden();
 }
}
