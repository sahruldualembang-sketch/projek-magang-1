<?php
namespace App\Http\Controllers;
use App\Models\Employee; use App\Models\User; use App\Services\EmployeeAccountService;
use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Validator; use Illuminate\Support\Facades\Storage; use Illuminate\Validation\Rule;
class ModuleController extends Controller
{
 private function normalizeFilterValue(string $type, mixed $value): mixed
 {
     if (!is_string($value)) {
         return $value;
     }

     $value = trim($value);
     if ($value === '') {
         return '';
     }

     if ($type === 'date') {
         foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
             $date = \DateTime::createFromFormat($format, $value);
             if ($date && $date->format($format) === $value) {
                 return $date->format('Y-m-d');
             }
         }

         return $value;
     }

     if ($type === 'month') {
         foreach (['Y-m', 'm/Y', 'm-Y'] as $format) {
             $date = \DateTime::createFromFormat($format, $value);
             if ($date && $date->format($format) === $value) {
                 return $date->format('Y-m');
             }
         }

         return $value;
     }

     return $value;
 }
 private function definition(string $module): array { $d=config("simlji.$module"); abort_unless($d && $module!=='profit-loss',404); abort_unless(auth()->user()->hasRole($d['roles']),403); return $d; }
 private function scopedQuery(string $module,array $d){$q=DB::table($d['table']);$u=auth()->user(); if($u->role==='staff'){abort_unless($u->employee_id && $u->employee()->exists(),403,'Akun karyawan belum ditautkan ke data karyawan. Hubungi HRD.'); if($module==='employees')$q->where('id',$u->employee_id); elseif(in_array($module,['attendances','activities','payrolls']))$q->where('employee_id',$u->employee_id); else abort(403);} return $q;}
 private function relationOptions(array $d): array {$out=[];foreach($d['fields'] as $f){if(($f['type']??'')==='relation'){$table=$f['relation'];$label=match($table){'employees'=>'name','clients'=>'company_name','orders'=>'order_number',default=>'id'};$out[$f['name']]=DB::table($table)->orderBy($label)->pluck($label,'id');}}return $out;}
 private function listingConfiguration(string $module): array
 {
     $employeeName = [
         'table' => 'employees',
         'local_key' => 'employee_id',
         'remote_key' => 'id',
         'column' => 'name',
     ];
     $clientName = [
         'table' => 'clients',
         'local_key' => 'client_id',
         'remote_key' => 'id',
         'column' => 'company_name',
     ];

     $configurations = [
         'employees' => [
             'filters' => [
                 'department' => ['label' => 'Departemen', 'type' => 'select', 'column' => 'department', 'options_source' => ['table' => 'employees', 'column' => 'department'], 'empty_label' => 'Semua Departemen'],
                 'employment_status' => ['label' => 'Status Kepegawaian', 'type' => 'select', 'column' => 'employment_status', 'options' => ['Tetap', 'Kontrak', 'Magang', 'Nonaktif'], 'empty_label' => 'Semua Status'],
                 'join_date_from' => ['label' => 'Tanggal Bergabung Dari', 'type' => 'date', 'column' => 'join_date', 'operator' => '>='],
                 'join_date_to' => ['label' => 'Tanggal Bergabung Sampai', 'type' => 'date', 'column' => 'join_date', 'operator' => '<='],
             ],
             'ranges' => [['start' => 'join_date_from', 'end' => 'join_date_to']],
             'search_columns' => ['employee_code', 'name', 'email', 'phone', 'position'],
         ],
         'attendances' => [
             'filters' => [
                 'nama' => ['label' => 'Nama Karyawan', 'type' => 'relation_text', 'relation' => $employeeName],
                 'tanggal_mulai' => ['label' => 'Tanggal Mulai', 'type' => 'date', 'column' => 'attendance_date', 'operator' => '>='],
                 'tanggal_akhir' => ['label' => 'Tanggal Akhir', 'type' => 'date', 'column' => 'attendance_date', 'operator' => '<='],
                 'status' => ['label' => 'Status Kehadiran', 'type' => 'select', 'column' => 'status', 'options' => ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_mulai', 'end' => 'tanggal_akhir']],
             'search_columns' => ['attendance_date', 'check_in', 'check_out', 'status', 'notes'],
             'search_relations' => [$employeeName],
         ],
         'activities' => [
             'filters' => [
                 'nama' => ['label' => 'Nama Karyawan', 'type' => 'relation_text', 'relation' => $employeeName],
                 'tanggal_mulai' => ['label' => 'Tanggal Aktivitas Dari', 'type' => 'date', 'column' => 'activity_date', 'operator' => '>='],
                 'tanggal_akhir' => ['label' => 'Tanggal Aktivitas Sampai', 'type' => 'date', 'column' => 'activity_date', 'operator' => '<='],
                 'status' => ['label' => 'Status Aktivitas', 'type' => 'select', 'column' => 'status', 'options' => ['Belum Dimulai', 'Proses', 'Selesai', 'Tertunda'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_mulai', 'end' => 'tanggal_akhir']],
             'search_columns' => ['title', 'description'],
             'search_relations' => [$employeeName],
         ],
         'payrolls' => [
             'filters' => [
                 'nama' => ['label' => 'Nama Karyawan', 'type' => 'relation_text', 'relation' => $employeeName],
                 'periode_dari' => ['label' => 'Periode Gaji Dari', 'type' => 'month', 'column' => 'payroll_period', 'operator' => '>='],
                 'periode_sampai' => ['label' => 'Periode Gaji Sampai', 'type' => 'month', 'column' => 'payroll_period', 'operator' => '<='],
                 'status' => ['label' => 'Status Pembayaran', 'type' => 'select', 'column' => 'status', 'options' => ['Draft', 'Disetujui', 'Dibayar'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'periode_dari', 'end' => 'periode_sampai']],
             'search_columns' => ['payroll_period', 'status'],
             'search_relations' => [$employeeName],
         ],
         'clients' => [
             'filters' => [
                 'status' => ['label' => 'Status Klien', 'type' => 'select', 'column' => 'status', 'options' => ['Aktif', 'Nonaktif', 'Prospek'], 'empty_label' => 'Semua Status'],
             ],
             'search_columns' => ['client_code', 'company_name', 'contact_name', 'email', 'phone'],
         ],
         'orders' => [
             'filters' => [
                 'nama_klien' => ['label' => 'Nama Klien', 'type' => 'relation_text', 'relation' => $clientName],
                 'tanggal_mulai' => ['label' => 'Tanggal Pesanan Dari', 'type' => 'date', 'column' => 'order_date', 'operator' => '>='],
                 'tanggal_akhir' => ['label' => 'Tanggal Pesanan Sampai', 'type' => 'date', 'column' => 'order_date', 'operator' => '<='],
                 'status' => ['label' => 'Status Pesanan', 'type' => 'select', 'column' => 'status', 'options' => ['Penawaran', 'Menunggu', 'Disetujui', 'Diproses', 'Selesai', 'Dibatalkan'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_mulai', 'end' => 'tanggal_akhir']],
             'search_columns' => ['order_number', 'description'],
             'search_relations' => [$clientName],
         ],
         'contracts' => [
             'filters' => [
                 'nama_klien' => ['label' => 'Nama Klien', 'type' => 'relation_text', 'relation' => $clientName],
                 'tanggal_akhir_dari' => ['label' => 'Kontrak Berakhir Dari', 'type' => 'date', 'column' => 'end_date', 'operator' => '>='],
                 'tanggal_akhir_sampai' => ['label' => 'Kontrak Berakhir Sampai', 'type' => 'date', 'column' => 'end_date', 'operator' => '<='],
                 'status' => ['label' => 'Status Kontrak', 'type' => 'select', 'column' => 'status', 'options' => ['Draft', 'Aktif', 'Berakhir', 'Dibatalkan'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_akhir_dari', 'end' => 'tanggal_akhir_sampai']],
             'search_columns' => ['contract_number', 'title'],
             'search_relations' => [$clientName],
         ],
         'assets' => [
             'filters' => [
                 'category' => ['label' => 'Kategori Aset', 'type' => 'select', 'column' => 'category', 'options_source' => ['table' => 'assets', 'column' => 'category'], 'empty_label' => 'Semua Kategori'],
                 'acquisition_date_from' => ['label' => 'Tanggal Perolehan Dari', 'type' => 'date', 'column' => 'acquisition_date', 'operator' => '>='],
                 'acquisition_date_to' => ['label' => 'Tanggal Perolehan Sampai', 'type' => 'date', 'column' => 'acquisition_date', 'operator' => '<='],
                 'asset_condition' => ['label' => 'Kondisi Aset', 'type' => 'select', 'column' => 'asset_condition', 'options' => ['Baik', 'Perlu Perbaikan', 'Rusak'], 'empty_label' => 'Semua Kondisi'],
                 'status' => ['label' => 'Status Aset', 'type' => 'select', 'column' => 'status', 'options' => ['Tersedia', 'Dipakai', 'Dihapuskan'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'acquisition_date_from', 'end' => 'acquisition_date_to']],
             'search_columns' => ['asset_code', 'name', 'category', 'assigned_to'],
         ],
         'legal_documents' => [
             'filters' => [
                 'expires_from' => ['label' => 'Kedaluwarsa Dari', 'type' => 'date', 'column' => 'expires_at', 'operator' => '>='],
                 'expires_to' => ['label' => 'Kedaluwarsa Sampai', 'type' => 'date', 'column' => 'expires_at', 'operator' => '<='],
                 'status' => ['label' => 'Status Dokumen', 'type' => 'select', 'column' => 'status', 'options' => ['Berlaku', 'Segera Berakhir', 'Kedaluwarsa'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'expires_from', 'end' => 'expires_to']],
             'search_columns' => ['document_name', 'document_number', 'issuer'],
         ],
         'invoices' => [
             'filters' => [
                 'nama_klien' => ['label' => 'Nama Klien', 'type' => 'relation_text', 'relation' => $clientName],
                 'tanggal_mulai' => ['label' => 'Tanggal Terbit Dari', 'type' => 'date', 'column' => 'issue_date', 'operator' => '>='],
                 'tanggal_akhir' => ['label' => 'Tanggal Terbit Sampai', 'type' => 'date', 'column' => 'issue_date', 'operator' => '<='],
                 'status' => ['label' => 'Status Invoice', 'type' => 'select', 'column' => 'status', 'options' => ['Draft', 'Terkirim', 'Terbayar', 'Jatuh Tempo', 'Dibatalkan'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_mulai', 'end' => 'tanggal_akhir']],
             'search_columns' => ['invoice_number'],
             'search_relations' => [
                 $clientName,
                 ['table' => 'orders', 'local_key' => 'order_id', 'remote_key' => 'id', 'column' => 'order_number'],
             ],
         ],
         'expenses' => [
             'filters' => [
                 'tanggal_mulai' => ['label' => 'Tanggal Pengeluaran Dari', 'type' => 'date', 'column' => 'expense_date', 'operator' => '>='],
                 'tanggal_akhir' => ['label' => 'Tanggal Pengeluaran Sampai', 'type' => 'date', 'column' => 'expense_date', 'operator' => '<='],
                 'category' => ['label' => 'Kategori Pengeluaran', 'type' => 'select', 'column' => 'category', 'options_source' => ['table' => 'expenses', 'column' => 'category'], 'empty_label' => 'Semua Kategori'],
                 'payment_method' => ['label' => 'Metode Pembayaran', 'type' => 'select', 'column' => 'payment_method', 'options' => ['Tunai', 'Transfer', 'Kartu', 'Lainnya'], 'empty_label' => 'Semua Metode'],
                 'status' => ['label' => 'Status Pengeluaran', 'type' => 'select', 'column' => 'status', 'options' => ['Draft', 'Menunggu Persetujuan', 'Disetujui', 'Dibayar', 'Dibatalkan'], 'empty_label' => 'Semua Status'],
             ],
             'ranges' => [['start' => 'tanggal_mulai', 'end' => 'tanggal_akhir']],
             'search_columns' => ['expense_number', 'description', 'category'],
         ],
     ];

     return $configurations[$module] ?? ['filters' => [], 'ranges' => [], 'search_columns' => [], 'search_relations' => []];
 }
 public function index(Request $r, string $module)
 {
     $d = $this->definition($module);
     if ($module === 'attendances') {
         return app(AttendanceController::class)->index($r);
     }

     if (auth()->user()->role === 'staff' && $module === 'employees') {
         $d['fields'] = array_values(array_filter(
             $d['fields'],
             fn ($f) => $f['name'] !== 'base_salary'
         ));
     }

     $table = $d['table'];
     $q = $this->scopedQuery($module, $d);
     $configuration = $this->listingConfiguration($module);
     $filterFields = $configuration['filters'];
     $isStaff = auth()->user()->role === 'staff';

     if ($isStaff && in_array($module, ['activities', 'attendances', 'payrolls'], true)) {
         unset($filterFields['nama']);
     }

     if ($isStaff && $module === 'activities') {
         $d['title'] = 'Aktivitas Saya';
         $configuration['search_relations'] = [];
     }

     foreach ($filterFields as $key => $field) {
         if (isset($field['options_source'])) {
             $source = $field['options_source'];
             $optionsQuery = DB::table($source['table']);
             if ($module === 'employees' && auth()->user()->role === 'staff') {
                 $optionsQuery->where('id', auth()->user()->employee_id);
             }

             $filterFields[$key]['options'] = $optionsQuery
                 ->whereNotNull($source['column'])
                 ->where($source['column'], '<>', '')
                 ->distinct()
                 ->orderBy($source['column'])
                 ->pluck($source['column'])
                 ->all();
         }
     }

     $rules = ['q' => ['nullable', 'string', 'max:150']];
     foreach ($filterFields as $key => $field) {
         $rules[$key] = match ($field['type']) {
             'date' => ['nullable', 'date_format:Y-m-d'],
             'month' => ['nullable', 'date_format:Y-m'],
             'select' => ['nullable', Rule::in($field['options'] ?? [])],
             'relation_text' => ['nullable', 'string', 'max:150'],
         };
     }

      $queryData = $r->query();
      foreach ($filterFields as $key => $field) {
          if (!array_key_exists($key, $queryData) || !in_array($field['type'], ['date', 'month'], true)) {
              continue;
          }

          $queryData[$key] = $this->normalizeFilterValue($field['type'], $queryData[$key]);
      }

     $filterKeys = array_merge(['q'], array_keys($filterFields));
     $hasFilters = count(array_intersect($filterKeys, array_keys($r->query()))) > 0;
      $validator = Validator::make($queryData, $rules);

     if ($validator->fails()) {
         return redirect()->route('modules.index', $module)
             ->withErrors($validator)
             ->withInput($r->query());
     }

     $validated = $hasFilters ? $validator->validated() : [];
     $searchValue = $hasFilters ? ($validated['q'] ?? '') : $r->old('q', $r->query('q', ''));
     $search = is_string($searchValue) ? trim($searchValue) : '';

     $filters = [];
     foreach ($filterFields as $key => $field) {
         $value = $hasFilters
             ? ($validated[$key] ?? '')
             : $r->old($key, $r->query($key, ''));

         if (in_array($field['type'], ['date', 'month'], true)) {
             $value = $this->normalizeFilterValue($field['type'], $value);
         }

         $filters[$key] = is_string($value) ? $value : '';
     }

     if ($search !== '') {
         $searchColumns = $configuration['search_columns'];
         $searchRelations = $configuration['search_relations'] ?? [];

         $q->where(function ($searchQuery) use ($searchColumns, $searchRelations, $search, $table) {
             foreach ($searchColumns as $column) {
                 $searchQuery->orWhere($table . '.' . $column, 'like', '%' . $search . '%');
             }

             foreach ($searchRelations as $relation) {
                 $searchQuery->orWhereExists(function ($relatedQuery) use ($relation, $search, $table) {
                     $relatedQuery->selectRaw('1')
                         ->from($relation['table'])
                         ->whereColumn(
                             $relation['table'] . '.' . $relation['remote_key'],
                             $table . '.' . $relation['local_key']
                         )
                         ->where($relation['table'] . '.' . $relation['column'], 'like', '%' . $search . '%');
                 });
             }
         });
     }

     if ($hasFilters) {
         foreach ($configuration['ranges'] ?? [] as $range) {
             $start = $this->normalizeFilterValue($filterFields[$range['start']]['type'] ?? 'date', $filters[$range['start']] ?? '');
             $end = $this->normalizeFilterValue($filterFields[$range['end']]['type'] ?? 'date', $filters[$range['end']] ?? '');

             if ($start !== '' && $end !== '' && $end < $start) {
                 return redirect()->route('modules.index', $module)
                     ->withErrors([
                         $range['end'] => 'Nilai akhir tidak boleh sebelum nilai mulai.',
                     ])
                     ->withInput($r->query());
             }
         }

         foreach ($filterFields as $key => $field) {
             $value = $filters[$key] ?? '';
             if ($value === '') {
                 continue;
             }

             if ($field['type'] === 'relation_text') {
                 $relation = $field['relation'];
                 $q->whereExists(function ($relatedQuery) use ($relation, $table, $value) {
                     $relatedQuery->selectRaw('1')
                         ->from($relation['table'])
                         ->whereColumn(
                             $relation['table'] . '.' . $relation['remote_key'],
                             $table . '.' . $relation['local_key']
                         )
                         ->where($relation['table'] . '.' . $relation['column'], 'like', '%' . $value . '%');
                 });
             } elseif (in_array($field['type'], ['date', 'month'], true)) {
                 $column = $table . '.' . $field['column'];
                 if ($field['type'] === 'date') {
                     $q->whereDate($column, $field['operator'], $value);
                 } else {
                     $q->where($column, $field['operator'], $value);
                 }
             } else {
                 $q->where($table . '.' . $field['column'], $value);
             }
         }
     }

     $rows = $q->orderByDesc($table . '.id')
         ->paginate(12)
         ->withQueryString();

     return view('modules.index', compact(
         'module',
         'd',
         'rows',
         'search',
         'filterFields',
         'filters'
     ));
 }
 public function create(string $module)
 {
     $d = $this->definition($module);
     abort_if(auth()->user()->role === 'staff', 403);
     $this->ensureMaker($d);

     $row = null;
     $relations = $this->relationOptions($d);
     // Auto-generate nomor untuk modul yang mendukung penomoran otomatis
     $defaults = [];
     $autoNumber = $this->generateNumber($module);
     if ($autoNumber) {
         $defaults[$this->numberField($module)] = $autoNumber;
     }

     return view('modules.form', compact('module', 'd', 'row', 'relations', 'defaults'));
 }

 public function store(Request $r, string $module)
 {
     $d = $this->definition($module);
     abort_if(auth()->user()->role === 'staff', 403);
     $this->ensureMaker($d);

     $accountPassword = null;
     if ($module === 'employees' && auth()->user()->hasRole(['hr', 'super_admin'])) {
         $accountPassword = $r->validate([
             'account_password' => ['nullable', 'string', 'min:12'],
         ])['account_password'] ?? null;
     }

     $data = $this->prepareData($module, $this->validated($r, $d));
     $data = $this->applyWorkflowDefaults($d, $data);
     // Jika nomor belum diisi, generate otomatis sebelum simpan (failsafe)
     $numField = $this->numberField($module);
     if ($numField && empty($data[$numField])) {
         $data[$numField] = $this->generateNumber($module);
     }
     $id = DB::table($d['table'])->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
     $this->audit($module, 'created', $id, null, $data);

     $credentials = null;
     $accountFailed = false;

     if ($module === 'employees' && auth()->user()->hasRole(['hr', 'super_admin'])) {
         $employee = Employee::find($id);

         if ($employee && $employee->employment_status !== 'Nonaktif' && ! $employee->user()->exists()) {
             try {
                 $credentials = app(EmployeeAccountService::class)->createFor($employee, $accountPassword);
             } catch (\Throwable $e) {
                 report($e);
                 $accountFailed = true;
             }
         }
     }

     $message = $d['singular'] . ' berhasil ditambahkan.';
     if ($credentials) {
         $message .= ' Akun login karyawan juga dibuat; catat kredensial yang ditampilkan.';
     } elseif ($accountFailed) {
         $message .= ' Namun akun login gagal dibuat; hubungi Super Admin.';
     }

     $redirect = redirect()->route('modules.index', $module)->with('success', $message);

     if ($credentials) {
         $redirect->with('generated_credentials', [$credentials]);
     }

     return $redirect;
 }

 public function edit(string $module, int $id)
 {
     $d = $this->definition($module);
     abort_if($module === 'attendances', 403, 'Gunakan halaman absensi untuk tindakan yang tercatat.');
     // Staff hanya boleh membuka form edit untuk aktivitas miliknya sendiri.
     abort_if(auth()->user()->role === 'staff' && $module !== 'activities', 403);
     $this->ensureMaker($d);

     $row = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($row, 404);
     $this->ensureEditable($d, $row);

     $relations = $this->relationOptions($d);

     return view('modules.form', compact('module', 'd', 'row', 'relations'));
 }

 public function update(Request $r, string $module, int $id)
 {
     $d = $this->definition($module);
     abort_if($module === 'attendances', 403, 'Gunakan halaman absensi untuk tindakan yang tercatat.');
     // Staff hanya boleh memperbarui aktivitas miliknya sendiri.
     abort_if(auth()->user()->role === 'staff' && $module !== 'activities', 403);
     $this->ensureMaker($d);

     $before = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($before, 404);
     $this->ensureEditable($d, $before);

     if (auth()->user()->role === 'staff') {
         // Staff hanya boleh mengubah progres & status; field lain diabaikan.
         $data = $this->activityProgressData($r, $d);
     } else {
         $data = $this->prepareData($module, $this->validated($r, $d, $id));
         $data = $this->applyWorkflowDefaults($d, $data, $before);
     }

     if (isset($data['file_path']) && $before->file_path) {
         $data['_old_file'] = $before->file_path;
     }
     $oldFile = $data['_old_file'] ?? null;
     unset($data['_old_file']);

     DB::table($d['table'])->where('id', $id)->update($data + ['updated_at' => now()]);
     $this->audit($module, 'updated', $id, (array) $before, $data);

     if ($module === 'employees') {
         $user = User::where('employee_id', $id)->first();
         if ($user) {
             $userUpdates = [];
             if (($data['employment_status'] ?? null) === 'Nonaktif' && $user->is_active) {
                 $userUpdates['is_active'] = false;
                 DB::table('sessions')->where('user_id', $user->id)->delete();
             }
             if (! empty($data['name']) && $data['name'] !== $user->name) {
                 $userUpdates['name'] = $data['name'];
             }
             if (! empty($data['email']) && $data['email'] !== $user->email && ! User::where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                 $userUpdates['email'] = $data['email'];
             }
             if ($userUpdates !== []) {
                 $user->update($userUpdates);
                 $this->audit('users', 'employee_sync', $user->id, (array) $user->getOriginal(), $userUpdates);
             }
         }
     }

     if ($oldFile) {
         Storage::disk('local')->delete($oldFile);
     }

     return redirect()->route('modules.index', $module)->with('success', $d['singular'] . ' berhasil diperbarui.');
 }

 public function editProgress(int $id)
 {
     $d = $this->definition('activities');
     $row = $this->scopedQuery('activities', $d)->where('id', $id)->first();
     abort_unless($row, 404);

     $statusField = collect($d['fields'])->firstWhere('name', 'status');
     $statusOptions = $statusField['options'] ?? [];

     return view('modules.progress', compact('d', 'row', 'statusOptions'));
 }

 public function updateProgress(Request $r, int $id)
 {
     $d = $this->definition('activities');
     $before = $this->scopedQuery('activities', $d)->where('id', $id)->first();
     abort_unless($before, 404);

     $validated = $this->activityProgressData($r, $d);

     DB::table('activities')->where('id', $id)->update([
         'progress' => $validated['progress'],
         'status' => $validated['status'],
         'updated_at' => now(),
     ]);

     $this->audit('activities', 'progress_updated', $id, [
         'progress' => $before->progress,
         'status' => $before->status,
     ], [
         'progress' => $validated['progress'],
         'status' => $validated['status'],
     ]);

     return redirect()->route('modules.index', 'activities')
         ->with('success', 'Progres aktivitas berhasil diperbarui.');
 }

 public function submit(Request $r, string $module, int $id)
 {
     $d = $this->definition($module);
     $w = $this->workflow($d);
     abort_unless($w && $w['pending_status'], 403, 'Modul ini tidak menggunakan tahap pengajuan.');
     abort_unless(auth()->user()->hasRole($w['maker_roles']), 403, 'Anda tidak berwenang mengajukan data ini.');

     $row = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($row, 404);
     abort_unless($row->status === $w['draft_status'], 409, 'Hanya data berstatus ' . $w['draft_status'] . ' yang dapat diajukan.');

     DB::table($d['table'])->where('id', $id)->update(['status' => $w['pending_status'], 'updated_at' => now()]);
     $this->audit($module, 'submitted', $id, (array) $row, ['status' => $w['pending_status']]);

     return back()->with('success', $d['singular'] . ' berhasil diajukan untuk persetujuan.');
 }

 public function approve(Request $r, string $module, int $id)
 {
     $d = $this->definition($module);
     $w = $this->workflow($d);
     abort_unless($w, 403);
     abort_unless(auth()->user()->hasRole($w['checker_roles']), 403, 'Anda tidak berwenang menyetujui data ini.');

     $row = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($row, 404);

     $from = $w['pending_status'] ? [$w['pending_status']] : [$w['draft_status']];
     abort_unless(in_array($row->status, $from, true), 409, 'Data tidak berada pada tahap yang dapat disetujui.');

     DB::table($d['table'])->where('id', $id)->update(['status' => $w['approved_status'], 'updated_at' => now()]);
     $this->audit($module, 'approved', $id, (array) $row, ['status' => $w['approved_status']]);

     return back()->with('success', $d['singular'] . ' berhasil disetujui.');
 }

 public function pay(Request $r, string $module, int $id)
 {
     $d = $this->definition($module);
     $w = $this->workflow($d);
     abort_unless($w, 403);
     abort_unless(auth()->user()->hasRole($w['checker_roles']), 403, 'Anda tidak berwenang menandai pembayaran.');

     $row = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($row, 404);
     abort_unless($row->status === $w['approved_status'], 409, 'Data harus berstatus ' . $w['approved_status'] . ' sebelum dibayar.');

     $data = ['status' => $w['paid_status'], 'updated_at' => now()];
     if ($w['set_paid_at'] ?? false) {
         $data['paid_at'] = now()->toDateString();
     }
     DB::table($d['table'])->where('id', $id)->update($data);

     $audit = ['status' => $w['paid_status']];
     if (isset($data['paid_at'])) {
         $audit['paid_at'] = $data['paid_at'];
     }
     $this->audit($module, 'paid', $id, (array) $row, $audit);

     return back()->with('success', $d['singular'] . ' ditandai sebagai ' . $w['paid_status'] . '.');
 }

 public function returnToDraft(Request $r, string $module, int $id)
 {
     $d = $this->definition($module);
     $w = $this->workflow($d);
     abort_unless($w, 403);
     abort_unless(auth()->user()->hasRole($w['checker_roles']), 403, 'Anda tidak berwenang mengembalikan data ini.');

     $validated = $r->validate(['reason' => ['required', 'string', 'max:1000']]);

     $row = $this->scopedQuery($module, $d)->where('id', $id)->first();
     abort_unless($row, 404);
     $returnable = array_values(array_filter([$w['pending_status'], $w['approved_status']]));
     abort_unless(in_array($row->status, $returnable, true), 409, 'Data belum dapat dikembalikan ke Draft.');

     DB::table($d['table'])->where('id', $id)->update(['status' => $w['draft_status'], 'updated_at' => now()]);
     $this->audit($module, 'returned', $id, (array) $row, ['status' => $w['draft_status'], 'reason' => $validated['reason']]);

     return back()->with('success', $d['singular'] . ' dikembalikan ke Draft untuk diperbaiki.');
 }

 public function destroy(string $module,int $id){$d=$this->definition($module);abort_if($module==='attendances',403,'Gunakan halaman absensi untuk tindakan yang tercatat.');abort_if(auth()->user()->role==='staff',403);abort_if(in_array($module,['payrolls','invoices','expenses']),403,'Catatan keuangan tidak dihapus; lakukan koreksi melalui status atau prosedur pembatalan.');$before=$this->scopedQuery($module,$d)->where('id',$id)->first();abort_unless($before,404);try{DB::table($d['table'])->where('id',$id)->delete();if(!empty($before->file_path))Storage::disk('local')->delete($before->file_path);$this->audit($module,'deleted',$id,(array)$before,null);}catch(\Throwable $e){return back()->with('error','Data tidak dapat dihapus karena masih digunakan oleh data lain.');}return back()->with('success',$d['singular'].' berhasil dihapus.');}
 private function validated(Request $r,array $d,?int $id=null):array
 {
     $workflowFields = isset($d['workflow']) ? ['status', 'paid_at'] : [];
     $rules = [];
     foreach ($d['fields'] as $f) {
         if (($f['calculated'] ?? false) === true) {
             continue;
         }
         if (in_array($f['name'], $workflowFields, true)) {
             continue;
         }
         $rule = $f['rules'];
         if (str_contains($rule, 'unique:') && $id) {
             $rule = preg_replace('/unique:([^,]+),([^|]+)/', 'unique:$1,$2,' . $id . ',id', $rule);
         }
         $rules[$f['name']] = $rule;
     }

     // Satu karyawan hanya boleh memiliki satu catatan gaji per periode.
     if (($d['table'] ?? null) === 'payrolls') {
         $uniquePeriod = Rule::unique('payrolls', 'payroll_period')
             ->where(fn ($query) => $query->where('employee_id', $r->input('employee_id')));
         if ($id) {
             $uniquePeriod->ignore($id);
         }
         $rules['payroll_period'] = ['required', 'date_format:Y-m', $uniquePeriod];

         // Cegah salah ketik tahun (misalnya 2006 untuk 2026).
         $yearFrom = now()->year - 2;
         $yearTo = now()->year + 2;
         $rules['payroll_period'][] = function (string $attribute, mixed $value, callable $fail) use ($yearFrom, $yearTo): void {
             if (is_string($value) && preg_match('/^(\d{4})-\d{2}$/', $value, $matches)) {
                 $year = (int) $matches[1];
                 if ($year < $yearFrom || $year > $yearTo) {
                     $fail("Periode gaji harus berada dalam rentang tahun {$yearFrom}–{$yearTo}.");
                 }
             }
         };
     }

     return Validator::make($r->all(), $rules, [
         'payroll_period.unique' => 'Gaji untuk karyawan dan periode ini sudah tercatat. Ubah data yang ada atau pilih periode lain.',
     ])->validate();
 }
 private function activityProgressData(Request $r, array $d): array
 {
     $statusField = collect($d['fields'])->firstWhere('name', 'status');
     $statusOptions = $statusField['options'] ?? [];

     $normalized = $this->normalizeActivityProgress($r->input('progress'), $r->input('status'));

     return Validator::make($normalized, [
         'progress' => ['required', 'integer', 'min:0', 'max:100'],
         'status' => ['required', Rule::in($statusOptions)],
     ])->validate();
 }
 /**
  * Samakan progres dan status aktivitas agar tidak ada kombinasi mustahil
  * seperti "80% tapi Belum Dimulai" atau "100% tapi Proses".
  * Dipakai baik oleh jalur staff (Ubah Progres) maupun jalur form lengkap.
  */
 private function normalizeActivityProgress(mixed $progress, mixed $status): array
 {
     $status = (string) $status;
     $value = is_numeric($progress) ? (int) $progress : $progress;

     if (is_int($value)) {
         if ($value >= 100) {
             $value = 100;
             $status = 'Selesai';
         } elseif ($status === 'Selesai') {
             $value = 100;
         } elseif ($value <= 0) {
             $value = 0;
             if (! in_array($status, ['Belum Dimulai', 'Tertunda'], true)) {
                 $status = 'Belum Dimulai';
             }
         } elseif ($status === 'Belum Dimulai') {
             $value = 0;
         }
     }

     return ['progress' => $value, 'status' => $status];
 }
 private function workflow(?array $d): ?array
 {
     return $d['workflow'] ?? null;
 }
 private function ensureMaker(?array $d): void
 {
     $workflow = $this->workflow($d);
     abort_if($workflow && ! auth()->user()->hasRole($workflow['maker_roles']), 403, 'Anda tidak berwenang menambah atau mengubah data pada alur ini.');
 }
 private function ensureEditable(?array $d, object $row): void
 {
     $workflow = $this->workflow($d);
     if (! $workflow) {
         return;
     }

     abort_unless(in_array($row->status, $workflow['editable_statuses'], true), 403, 'Data yang sudah diajukan atau disetujui tidak dapat diubah. Gunakan prosedur koreksi.');
 }
 private function applyWorkflowDefaults(?array $d, array $data, ?object $row = null): array
 {
     $workflow = $this->workflow($d);
     if (! $workflow) {
         return $data;
     }

     $data['status'] = $row->status ?? $workflow['draft_status'];
     if (($workflow['set_paid_at'] ?? false) || array_key_exists('paid_at', $data)) {
         $data['paid_at'] = $row->paid_at ?? null;
     }

     return $data;
 }

 /**
  * Kembalikan nama kolom nomor untuk modul tertentu.
  */
 private function numberField(string $module): string
 {
     return match($module) {
         'expenses'  => 'expense_number',
         'orders'    => 'order_number',
         'invoices'  => 'invoice_number',
         'contracts' => 'contract_number',
         default     => '',
     };
 }

 /**
  * Generate nomor urut otomatis untuk modul tertentu.
  * Format: PREFIX-YYYY-NNN  (mis. EXP-2026-009)
  */
 private function generateNumber(string $module): string
 {
     $year = now()->format('Y');
     $map = [
         'expenses'       => ['prefix' => 'EXP',  'column' => 'expense_number',  'table' => 'expenses'],
         'orders'         => ['prefix' => 'ORD',  'column' => 'order_number',    'table' => 'orders'],
         'invoices'       => ['prefix' => 'INV',  'column' => 'invoice_number',  'table' => 'invoices'],
         'contracts'      => ['prefix' => 'KON',  'column' => 'contract_number', 'table' => 'contracts'],
     ];
     if (! isset($map[$module])) {
         return '';
     }
     $cfg    = $map[$module];
     $prefix = $cfg['prefix'] . '-' . $year . '-';
     $existing = DB::table($cfg['table'])
         ->where($cfg['column'], 'like', $prefix . '%')
         ->pluck($cfg['column']);

     $maxSeq = 0;
     foreach ($existing as $val) {
         $suffix = substr((string) $val, strlen($prefix));
         if (is_numeric($suffix)) {
             $maxSeq = max($maxSeq, (int) $suffix);
         }
     }
     $seq = $maxSeq + 1;

     return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
 }

 private function prepareData(string $module, array $data): array
 {
     if (isset($data['attachment']) && $data['attachment'] instanceof \Illuminate\Http\UploadedFile) {
         $file = $data['attachment'];
         $folder = match ($module) {
             'contracts' => 'contracts',
             'legal_documents' => 'legal',
             'invoices' => 'invoices',
             default => 'documents',
         };
         $path = $file->store($folder, 'local');
         $data['file_path'] = $path;
         $data['file_name'] = $file->getClientOriginalName();
         $data['file_mime'] = $file->getMimeType();
         $data['file_size'] = $file->getSize();
     }
     unset($data['attachment']);

     // Field numerik opsional memakai rule "nullable", sehingga input kosong
     // jadi NULL oleh middleware ConvertEmptyStringsToNull. Kolom DB terkait
     // bertipe NOT NULL dengan DEFAULT 0, jadi NULL yang dikirim eksplisit
     // menimpa default dan memicu error integritas. Samakan dengan 0.
     foreach (['base_salary', 'allowance', 'deduction', 'tax_amount'] as $numericWithDefault) {
         if (array_key_exists($numericWithDefault, $data) && $data[$numericWithDefault] === null) {
             $data[$numericWithDefault] = 0;
         }
     }

     if ($module === 'orders') {
         $data['total_amount'] = round((float) $data['quantity'] * (float) $data['unit_price'], 2);
     }
     if ($module === 'invoices') {
         $data['total_amount'] = round((float) $data['subtotal'] + (float) ($data['tax_amount'] ?? 0), 2);
     }
     if ($module === 'payrolls') {
         $data['net_salary'] = round((float) $data['basic_salary'] + (float) ($data['allowance'] ?? 0) - (float) ($data['deduction'] ?? 0), 2);
     }
     if ($module === 'activities' && array_key_exists('progress', $data) && array_key_exists('status', $data)) {
         $normalized = $this->normalizeActivityProgress($data['progress'], $data['status']);
         $data['progress'] = $normalized['progress'];
         $data['status'] = $normalized['status'];
     }

     return $data;
 }
 public function payslip(int $id)
 {
     $payroll = DB::table('payrolls')
         ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
         ->select('payrolls.*', 'employees.name as employee_name', 'employees.employee_code', 'employees.department', 'employees.position', 'employees.employment_status')
         ->where('payrolls.id', $id)
         ->first();

     abort_unless($payroll, 404);

     $user = auth()->user();
     if ($user->role === 'staff') {
         abort_unless($user->employee_id === $payroll->employee_id, 403, 'Anda hanya dapat melihat slip gaji milik sendiri.');
     } else {
         abort_unless($user->hasRole(['super_admin', 'director', 'hr', 'finance']), 403);
     }

     return view('modules.payrolls.slip', compact('payroll'));
 }

 public function generateBatchPayroll(Request $r)
 {
     $d = $this->definition('payrolls');
     $this->ensureMaker($d);

     $validated = $r->validate([
         'period' => ['required', 'date_format:Y-m'],
     ]);
     $period = $validated['period'];

     $activeEmployees = DB::table('employees')
         ->where('employment_status', '!=', 'Nonaktif')
         ->whereNotExists(function ($query) use ($period) {
             $query->selectRaw('1')
                 ->from('payrolls')
                 ->whereColumn('payrolls.employee_id', 'employees.id')
                 ->where('payrolls.payroll_period', $period);
         })
         ->get();

     if ($activeEmployees->isEmpty()) {
         return redirect()->route('modules.index', 'payrolls')
             ->with('info', "Semua karyawan aktif sudah memiliki draf gaji untuk periode {$period}.");
     }

     $createdCount = 0;
     DB::transaction(function () use ($activeEmployees, $period, &$createdCount) {
         foreach ($activeEmployees as $emp) {
             $basic = (float) ($emp->base_salary ?? 0);
             $id = DB::table('payrolls')->insertGetId([
                 'employee_id' => $emp->id,
                 'payroll_period' => $period,
                 'basic_salary' => $basic,
                 'allowance' => 0,
                 'deduction' => 0,
                 'net_salary' => $basic,
                 'status' => 'Draft',
                 'created_at' => now(),
                 'updated_at' => now(),
             ]);
             $this->audit('payrolls', 'created', $id, null, [
                 'employee_id' => $emp->id,
                 'payroll_period' => $period,
                 'basic_salary' => $basic,
                 'status' => 'Draft',
             ]);
             $createdCount++;
         }
     });

     return redirect()->route('modules.index', 'payrolls')
         ->with('success', "Berhasil membuat {$createdCount} draf gaji untuk periode {$period}.");
 }

 public function download(string $module,int $id){$d=$this->definition($module);abort_unless(in_array($module,['contracts','legal_documents','invoices']),404);$row=$this->scopedQuery($module,$d)->where('id',$id)->first();abort_unless($row && $row->file_path && Storage::disk('local')->exists($row->file_path),404);return Storage::disk('local')->download($row->file_path,$row->file_name ?: basename($row->file_path));}
 private function audit(string $module,string $action,int $id,?array $before,?array $after):void{DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'module'=>$module,'action'=>$action,'record_id'=>$id,'before_data'=>$before?json_encode($before):null,'after_data'=>$after?json_encode($after):null,'ip_address'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]);}
}
