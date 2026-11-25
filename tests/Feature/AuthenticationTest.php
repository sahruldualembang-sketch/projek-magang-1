<?php
namespace Tests\Feature;
use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class AuthenticationTest extends TestCase { use RefreshDatabase; public function test_login_page_is_available(): void {$this->get('/login')->assertOk();} public function test_user_can_login_and_logout(): void {$u=User::create(['name'=>'Tester','email'=>'tester@example.test','password'=>'SecretPass123!','role'=>'super_admin','is_active'=>true]);$this->post('/login',['email'=>$u->email,'password'=>'SecretPass123!'])->assertRedirect('/');$this->assertAuthenticatedAs($u);$this->post('/logout')->assertRedirect('/login');$this->assertGuest();} }
