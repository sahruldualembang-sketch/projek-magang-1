<?php
namespace App\Http\Controllers;
use App\Models\User; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Validator; use Illuminate\Validation\Rule;
class UserManagementController extends Controller
{
 public function index(Request $request)
 {
     $roles = ['super_admin', 'director', 'hr', 'finance', 'operations', 'staff'];
     $rules = [
         'q' => ['nullable', 'string', 'max:150'],
         'role' => ['nullable', Rule::in($roles)],
         'is_active' => ['nullable', Rule::in(['0', '1'])],
     ];
     $validator = Validator::make($request->query(), $rules);

     if ($validator->fails()) {
         return redirect()->route('users.index')
             ->withErrors($validator)
             ->withInput($request->query());
     }

     $hasFilters = count(array_intersect(array_keys($rules), array_keys($request->query()))) > 0;
     $validated = $hasFilters ? $validator->validated() : [];
     $searchValue = $hasFilters ? ($validated['q'] ?? '') : $request->old('q', $request->query('q', ''));
     $search = is_string($searchValue) ? trim($searchValue) : '';
     $roleFilter = $hasFilters ? ($validated['role'] ?? '') : $request->old('role', '');
     $activeFilter = $hasFilters ? ($validated['is_active'] ?? '') : $request->old('is_active', '');
     $roleFilter = is_string($roleFilter) ? $roleFilter : '';
     $activeFilter = is_string($activeFilter) ? $activeFilter : '';

     $query = User::with('employee')->orderBy('name');

     if ($search !== '') {
         $query->where(function ($searchQuery) use ($search) {
             $searchQuery->where('users.name', 'like', '%' . $search . '%')
                 ->orWhere('users.email', 'like', '%' . $search . '%')
                 ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                     $employeeQuery->where('name', 'like', '%' . $search . '%');
                 });
         });
     }

     if ($roleFilter !== '') {
         $query->where('users.role', $roleFilter);
     }

     if ($activeFilter !== '') {
         $query->where('users.is_active', $activeFilter === '1');
     }

     $users = $query->paginate(15)->withQueryString();

     return view('users.index', compact(
         'users',
         'search',
         'roleFilter',
         'activeFilter',
         'roles'
     ));
 }
 public function create(){ $employees=DB::table('employees')->orderBy('name')->pluck('name','id'); return view('users.form', ['account'=>null,'employees'=>$employees]); }
 public function store(Request $r){$data=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:150|unique:users,email','password'=>'required|string|min:12','role'=>['required',Rule::in(['super_admin','director','hr','finance','operations','staff'])],'employee_id'=>'required_if:role,staff|nullable|exists:employees,id|unique:users,employee_id','is_active'=>'nullable|boolean']);$data['is_active']=$r->boolean('is_active');$user=User::create($data);$this->audit('created',$user);return redirect()->route('users.index')->with('success','Akun pengguna berhasil dibuat.');}
 public function edit(User $user){$employees=DB::table('employees')->orderBy('name')->pluck('name','id');return view('users.form',['account'=>$user,'employees'=>$employees]);}
 public function update(Request $r,User $user){abort_if($user->id===auth()->id() && (!$r->boolean('is_active') || $r->input('role')!==$user->role),422,'Anda tidak dapat menonaktifkan atau mengubah peran akun sendiri.');$data=$r->validate(['name'=>'required|string|max:150','email'=>['required','email','max:150',Rule::unique('users','email')->ignore($user->id)],'password'=>'nullable|string|min:12','role'=>['required',Rule::in(['super_admin','director','hr','finance','operations','staff'])],'employee_id'=>['required_if:role,staff','nullable','exists:employees,id',Rule::unique('users','employee_id')->ignore($user->id)],'is_active'=>'nullable|boolean']);if($user->role==='super_admin' && ($data['role']!=='super_admin' || !$r->boolean('is_active')) && User::where('role','super_admin')->where('is_active',true)->count()<=1)abort(422,'Sistem harus memiliki minimal satu Super Admin aktif.');if(empty($data['password']))unset($data['password']);$data['is_active']=$r->boolean('is_active');$user->update($data);$this->audit('updated',$user);return redirect()->route('users.index')->with('success','Akun pengguna berhasil diperbarui.');}
 private function audit(string $action,User $user):void{DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'module'=>'users','action'=>$action,'record_id'=>$user->id,'after_data'=>json_encode(['name'=>$user->name,'email'=>$user->email,'role'=>$user->role,'employee_id'=>$user->employee_id,'is_active'=>$user->is_active]),'ip_address'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]);}
}
