<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('students.index'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_expired_login_form_returns_to_the_login_page_instead_of_page_expired(): void
    {
        // CSRF checks are skipped in tests, so simulate the expired token on the login URL.
        Route::middleware('web')->post('login', fn () => throw new TokenMismatchException);

        $this->from(route('login'))->post('/login', ['email' => 'someone@example.test', 'password' => 'secret'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your session expired. Please try again.'])
            ->assertSessionHasInput('email', 'someone@example.test')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_users_can_sign_in_and_out_and_it_is_audited(): void
    {
        $school = $this->school('BNG');
        $user = User::factory()->forSchool($school)->create(['email' => 'admin@bng.test']);

        $this->post(route('login'), ['email' => 'admin@bng.test', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id, 'school_id' => $school->id]);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->get(route('dashboard'))->assertOk()->assertSee($school->name);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_password_and_inactive_accounts_are_rejected(): void
    {
        $school = $this->school('BNG');
        User::factory()->forSchool($school)->create(['email' => 'a@bng.test']);
        User::factory()->forSchool($school)->create(['email' => 'off@bng.test', 'status' => 'inactive']);

        $this->post(route('login'), ['email' => 'a@bng.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'off@bng.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_users_of_inactive_schools_cannot_sign_in(): void
    {
        $school = $this->school('BNG');
        $school->update(['status' => 'inactive']);
        User::factory()->forSchool($school)->create(['email' => 'a@bng.test']);

        $this->post(route('login'), ['email' => 'a@bng.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => 'x@y.test', 'password' => 'nope'])->assertSessionHasErrors();
        }

        $this->post(route('login'), ['email' => 'x@y.test', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_super_admin_can_sign_in_without_a_school(): void
    {
        User::factory()->superAdmin()->create(['email' => 'root@system.test']);

        $this->post(route('login'), ['email' => 'root@system.test', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertSee('All Schools');
        $this->assertTrue(auth()->user()->role->slug === Role::SUPER_ADMIN);
    }

    public function test_users_can_change_their_password(): void
    {
        $school = $this->school('BNG');
        $user = User::factory()->forSchool($school)->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('NewPass123', $user->fresh()->password));
    }
}
