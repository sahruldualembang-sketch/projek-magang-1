<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'OldPassphrase!2026';

    private function user(string $role = 'staff'): User
    {
        return User::create([
            'name' => 'Pengguna Uji',
            'email' => $role . '-' . uniqid() . '@example.test',
            'password' => self::OLD_PASSWORD,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_open_password_page(): void
    {
        $this->get(route('profile.password.edit'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_change_own_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('profile.password.edit'))
            ->assertOk()
            ->assertSee('Ubah Kata Sandi')
            ->assertSee('Kata Sandi Saat Ini');

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => 'NewPassphrase!2026',
                'password_confirmation' => 'NewPassphrase!2026',
            ])
            ->assertRedirect(route('profile.password.edit'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassphrase!2026', $user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->password));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'users',
            'action' => 'password_changed',
            'record_id' => $user->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'SalahPassphrase!2026',
                'password' => 'NewPassphrase!2026',
                'password_confirmation' => 'NewPassphrase!2026',
            ])
            ->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->password));
    }

    public function test_new_password_must_be_at_least_twelve_characters(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => 'pendek12',
                'password_confirmation' => 'pendek12',
            ])
            ->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => 'NewPassphrase!2026',
                'password_confirmation' => 'BedaPassphrase!2026',
            ])
            ->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->password));
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::OLD_PASSWORD,
                'password_confirmation' => self::OLD_PASSWORD,
            ])
            ->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->password));
    }

    public function test_password_page_is_available_for_every_role(): void
    {
        foreach (['super_admin', 'director', 'hr', 'finance', 'operations', 'staff'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->get(route('profile.password.edit'))
                ->assertOk()
                ->assertSee('Ubah Kata Sandi');
        }
    }
}
