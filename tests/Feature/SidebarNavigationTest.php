<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_has_only_nonempty_sections_for_each_role(): void
    {
        $roles = ['super_admin', 'director', 'hr', 'finance', 'operations', 'staff'];
        $sections = [
            'MENU UTAMA' => [],
            'SUMBER DAYA MANUSIA' => ['employees', 'attendances', 'payrolls'],
            'OPERASIONAL & BISNIS' => ['activities', 'assets', 'clients', 'orders'],
            'DOKUMEN & KEUANGAN' => ['contracts', 'legal_documents', 'invoices', 'expenses'],
        ];

        foreach ($roles as $role) {
            $employeeId = $role === 'staff'
                ? \Illuminate\Support\Facades\DB::table('employees')->insertGetId([
                    'employee_code' => 'SIDEBAR-STAFF',
                    'name' => 'Sidebar Staff',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : null;

            $user = User::create([
                'name' => ucfirst($role),
                'email' => $role.'@sidebar.example.test',
                'password' => 'LongPassphrase!2026',
                'role' => $role,
                'employee_id' => $employeeId,
                'is_active' => true,
            ]);

            $content = $this->actingAs($user)->get('/')->assertOk()->getContent();
            preg_match_all('/<div class="nav-caption">([^<]+)<\/div>/', $content, $matches);

            $expectedCaptions = ['MENU UTAMA'];
            foreach (array_slice($sections, 1, null, true) as $caption => $slugs) {
                foreach ($slugs as $slug) {
                    if (in_array($role, config("simlji.{$slug}.roles", []), true)) {
                        $expectedCaptions[] = $caption;
                        break;
                    }
                }
            }

            if ($role === 'super_admin') {
                $expectedCaptions[] = 'PENGELOLAAN SISTEM';
            }
            if (in_array($role, ['super_admin', 'director', 'finance'], true)) {
                $expectedCaptions[] = 'ANALISIS & LAPORAN';
            }

            $expectedCaptions[] = 'AKUN';

            $this->assertSame($expectedCaptions, $matches[1], "Unexpected sidebar sections for role {$role}.");
            $this->assertStringContainsString('Ubah Kata Sandi', $content);
            $this->assertDoesNotMatchRegularExpression(
                '/<div class="nav-section">\s*<div class="nav-caption">[^<]*<\/div>\s*<\/div>/',
                $content,
                "Empty sidebar section rendered for role {$role}."
            );
        }
    }
}
