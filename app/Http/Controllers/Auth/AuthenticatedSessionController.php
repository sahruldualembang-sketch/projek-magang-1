<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class AuthenticatedSessionController extends Controller
{
    public function create(){ return view('auth.login'); }
    public function store(Request $request)
    {
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (!Auth::attempt($credentials + ['is_active'=>1], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email'=>'Email atau kata sandi salah, atau akun tidak aktif.']);
        }
        $user = Auth::user();
        if ($user && $user->employee && $user->employee->employment_status === 'Nonaktif') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email'=>'Akun telah dinonaktifkan karena status kepegawaian tidak aktif.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    public function destroy(Request $request){ Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('login'); }
}
