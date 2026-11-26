<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function editPassword(): View
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $user->update(['password' => $validated['password']]);

        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'module' => 'users',
            'action' => 'password_changed',
            'record_id' => $user->id,
            'after_data' => json_encode(['password_changed' => true]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('profile.password.edit')
            ->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
