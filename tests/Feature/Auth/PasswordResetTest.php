<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', $notification->token));

            $response->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_link_request_response_is_identical_for_unknown_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $knownEmailResponse = $this->post(route('password.email'), ['email' => $user->email]);
        $unknownEmailResponse = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        $knownEmailResponse->assertSessionHasNoErrors();
        $unknownEmailResponse->assertSessionHasNoErrors();

        $this->assertSame(
            $knownEmailResponse->getSession()->get('status'),
            $unknownEmailResponse->getSession()->get('status'),
        );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => $user->email]);
        }

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertStatus(429);
    }
}
