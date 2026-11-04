<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Trusta#Reset-2026!xQ',
                'password_confirmation' => 'Trusta#Reset-2026!xQ',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_mail_failure_shows_a_clear_message_and_allows_retry(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $user = User::factory()->create();

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => __('passwords.send_failed')]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_email_is_in_indonesian(): void
    {
        app()->setLocale('id');

        $user = User::factory()->create();
        $mail = (new ResetPassword('token-uji'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Reset Password Akun TRUSTA', $mail->subject);
        $this->assertStringContainsString('Anda menerima email ini', $html);
        $this->assertStringContainsString('Salam,', $html);
        $this->assertStringContainsString('tidak bisa diklik', $html);
        $this->assertStringContainsString('/reset-password/token-uji', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
    }

    public function test_reset_page_shows_eye_toggle_and_password_rules(): void
    {
        $this->get('/reset-password/token-uji?email=a@contoh.test')
            ->assertOk()
            ->assertSee('Tampilkan password', false)
            ->assertSee('Syarat password:')
            ->assertSee('Ada huruf besar (A-Z)')
            ->assertSee('x-model="pw"', false)
            ->assertSee('x-model="pw2"', false);
    }

    public function test_weak_password_error_says_what_is_missing_in_indonesian(): void
    {
        app()->setLocale('id');

        $response = $this->from('/reset-password/t')->post('/reset-password', [
            'token' => 't',
            'email' => 'a@contoh.test',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]);

        $errors = session('errors')->get('password');
        $this->assertContains('Password harus minimal 8 karakter.', $errors);
        $this->assertContains('Password harus mengandung minimal satu huruf besar dan satu huruf kecil.', $errors);
        $this->assertContains('Password harus mengandung minimal satu angka.', $errors);
        $this->assertContains('Password harus mengandung minimal satu simbol.', $errors);
    }
}
