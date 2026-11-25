<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Employee extends Model
{
    protected $fillable = ['employee_code','name','email','phone','department','position','employment_status','join_date','base_salary'];
    protected function casts(): array { return ['join_date'=>'date','base_salary'=>'decimal:2']; }
    public function user(){ return $this->hasOne(User::class); }
}
