<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\ClassList;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\BrevoApiService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private AcademicUnit $ccsUnit;
    private AcademicUnit $marineUnit;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccsUnit = AcademicUnit::create(['name' => 'College of Computer Studies']);
        $this->marineUnit = AcademicUnit::create(['name' => 'College of Marine Transportation']);

        ClassList::create([
            'student_id_number' => '20240001',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        $this->student = User::create([
            'name' => 'Registered CCS Student',
            'email' => 'student.reset@uclm.edu.ph',
            'student_id_number' => '20240001',
            'password' => Hash::make('OldPassword123!'),
            'role' => 'student',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);
    }

    public function test_requesting_password_reset_stores_hashed_token_and_sends_frontend_reset_link(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'http://localhost:5173']);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'student.reset@uclm.edu.ph',
        ]);

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'If your email is registered in our system, you will receive a password reset link shortly.'
            );

        // Verify token record exists in password_reset_tokens and is hashed (never stored in plain text)
        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', 'student.reset@uclm.edu.ph')
            ->first();

        $this->assertNotNull($tokenRow);
        $this->assertNotEmpty($tokenRow->token);

        $capturedToken = null;
        Notification::assertSentTo(
            $this->student,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$capturedToken, $tokenRow) {
                $capturedToken = $notification->token;
                $this->assertNotSame($capturedToken, $tokenRow->token);
                $this->assertTrue(Hash::check($capturedToken, $tokenRow->token));

                $mailData = $notification->toMail($this->student);
                $this->assertStringStartsWith(
                    'http://localhost:5173/reset-password?token=',
                    $mailData->actionUrl
                );
                $this->assertStringContainsString(
                    'email=' . urlencode('student.reset@uclm.edu.ph'),
                    $mailData->actionUrl
                );
                return true;
            }
        );

        $this->assertNotNull($capturedToken);
    }

    public function test_nonexistent_email_returns_identical_generic_response_without_sending_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nonexistent.user@uclm.edu.ph',
        ]);

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'If your email is registered in our system, you will receive a password reset link shortly.'
            );

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent.user@uclm.edu.ph',
        ]);
    }

    public function test_resetting_password_with_valid_token_updates_password_and_invalidates_token_against_reuse(): void
    {
        $rawToken = Password::broker()->createToken($this->student);

        // 1. Validate reset token endpoint succeeds for valid token
        $this->postJson('/api/reset-password/validate', [
            'token' => $rawToken,
            'email' => $this->student->email,
        ])
            ->assertOk()
            ->assertJsonPath('valid', true);

        // 2. Reset password with valid token
        $resetResponse = $this->postJson('/api/reset-password', [
            'token' => $rawToken,
            'email' => $this->student->email,
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!',
        ]);

        $resetResponse->assertOk();

        // Verify password was updated with bcrypt/hashed representation
        $this->student->refresh();
        $this->assertTrue(Hash::check('NewSecurePass456!', $this->student->password));
        $this->assertFalse(Hash::check('OldPassword123!', $this->student->password));

        // Verify token row was deleted from password_reset_tokens
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->student->email,
        ]);

        // 3. Attempting to reuse the same token fails with 422
        $this->postJson('/api/reset-password', [
            'token' => $rawToken,
            'email' => $this->student->email,
            'password' => 'AnotherPassword789!',
            'password_confirmation' => 'AnotherPassword789!',
        ])->assertStatus(422);

        $this->postJson('/api/reset-password/validate', [
            'token' => $rawToken,
            'email' => $this->student->email,
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);

        // 4. Student can log in with the new password (both by Student ID and by Email)
        $this->postJson('/api/login', [
            'login' => '20240001',
            'password' => 'NewSecurePass456!',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/login', [
            'login' => $this->student->email,
            'password' => 'NewSecurePass456!',
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_invalid_and_expired_password_reset_tokens_are_rejected(): void
    {
        $rawToken = Password::broker()->createToken($this->student);

        // 1. Invalid token is rejected
        $this->postJson('/api/reset-password/validate', [
            'token' => 'invalid-forged-token-value',
            'email' => $this->student->email,
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-forged-token-value',
            'email' => $this->student->email,
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!',
        ])->assertStatus(422);

        // 2. Age the token past expiration (default 60 minutes -> backdate 65 minutes)
        DB::table('password_reset_tokens')
            ->where('email', $this->student->email)
            ->update([
                'created_at' => now()->subMinutes(65),
            ]);

        $this->postJson('/api/reset-password/validate', [
            'token' => $rawToken,
            'email' => $this->student->email,
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false);

        $this->postJson('/api/reset-password', [
            'token' => $rawToken,
            'email' => $this->student->email,
            'password' => 'NewSecurePass456!',
            'password_confirmation' => 'NewSecurePass456!',
        ])->assertStatus(422);

        // Original password remains unchanged
        $this->assertTrue(Hash::check('OldPassword123!', $this->student->fresh()->password));
    }

    public function test_forgot_password_and_reset_password_enforce_rate_limiting(): void
    {
        Notification::fake();
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));

        // First 3 requests within 1 minute return 200
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/forgot-password', [
                'email' => $this->student->email,
            ])->assertOk();
        }

        // 4th rapid request is rate-limited with HTTP 429
        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])->assertStatus(429);

        // Rate limiting on reset-password attempts (5 failed attempts -> 6th returns 429)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reset-password', [
                'token' => 'wrong-token-' . $i,
                'email' => $this->student->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertStatus(422);
        }

        $this->postJson('/api/reset-password', [
            'token' => 'wrong-token-6',
            'email' => $this->student->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertStatus(429);
    }

    public function test_student_registration_enforces_dean_class_list_and_academic_unit_verification(): void
    {
        ClassList::create([
            'student_id_number' => '20249988',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        // 1. Unlisted Student ID cannot register
        $this->postJson('/api/register', [
            'name' => 'Unlisted Student',
            'student_id_number' => '20247777',
            'email' => 'unlisted@uclm.edu.ph',
            'academic_unit_id' => $this->ccsUnit->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['student_id_number']);

        // 2. Listed Student ID cannot register under a different Academic Unit (Marine instead of CCS)
        $this->postJson('/api/register', [
            'name' => 'Mismatched Unit Student',
            'student_id_number' => '20249988',
            'email' => 'mismatched@uclm.edu.ph',
            'academic_unit_id' => $this->marineUnit->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors(['student_id_number']);

        // 3. Listed Student ID with matching Academic Unit registers successfully
        $regRes = $this->postJson('/api/register', [
            'name' => 'Verified CCS Student',
            'student_id_number' => '20249988',
            'email' => 'verified.ccs@uclm.edu.ph',
            'academic_unit_id' => $this->ccsUnit->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $regRes->assertCreated()
            ->assertJsonPath('user.student_id_number', '20249988')
            ->assertJsonPath('user.academic_unit_id', $this->ccsUnit->id)
            ->assertJsonPath('user.role', 'student');

        // Passwords are never returned in API responses
        $this->assertArrayNotHasKey('password', $regRes->json('user'));
    }

    public function test_failed_email_delivery_preserves_generic_response_avoids_secret_logging_and_allows_safe_retry(): void
    {
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));
        RateLimiter::clear('reset-password:' . sha1($this->student->email . '|127.0.0.1'));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.test.invalid',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'secret-smtp-user',
            'mail.mailers.smtp.password' => 'SuperSecretSmtpPass!999',
        ]);

        Log::spy();

        $channel = new class {
            public int $attempts = 0;
            public ?string $failedToken = null;
            public ?string $deliveredToken = null;

            public function send(object $notifiable, object $notification): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    $this->failedToken = (string) $notification->token;
                    throw new \RuntimeException(
                        "SMTP auth failed for secret-smtp-user / SuperSecretSmtpPass!999 using token {$this->failedToken}",
                        535
                    );
                }

                $this->deliveredToken = (string) $notification->token;
            }
        };

        Notification::extend('mail', fn () => $channel);
        Notification::forgetDrivers();

        $genericMessage = 'If your email is registered in our system, you will receive a password reset link shortly.';

        // 1. First attempt suffers SMTP failure -> generic 200 preserved, undelivered token deleted, no secrets logged
        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])
            ->assertOk()
            ->assertJsonPath('message', $genericMessage);

        $this->assertSame(1, $channel->attempts);
        $this->assertNotEmpty($channel->failedToken);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->student->email,
        ]);

        $failedToken = $channel->failedToken;
        $studentEmail = $this->student->email;

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context = []) use ($failedToken, $studentEmail) {
                $serialized = (string) json_encode($context);

                $this->assertSame('Password reset notification dispatch failed.', $message);
                $this->assertSame('RuntimeException', $context['exception'] ?? null);
                $this->assertSame(535, $context['code'] ?? null);
                $this->assertSame('smtp', $context['mailer'] ?? null);
                $this->assertSame('smtp.test.invalid', $context['host'] ?? null);
                $this->assertSame(587, $context['port'] ?? null);
                $this->assertSame('smtp', $context['scheme'] ?? null);
                $this->assertStringNotContainsString('SuperSecretSmtpPass!999', $serialized);
                $this->assertStringNotContainsString('secret-smtp-user', $serialized);
                $this->assertStringNotContainsString((string) $failedToken, $serialized);
                $this->assertStringNotContainsString($studentEmail, $serialized);

                return true;
            });

        // 2. Immediate retry succeeds without hitting the 60s broker recentlyCreatedToken throttle
        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])
            ->assertOk()
            ->assertJsonPath('message', $genericMessage);

        $this->assertSame(2, $channel->attempts);
        $this->assertNotEmpty($channel->deliveredToken);
        $this->assertNotSame($channel->failedToken, $channel->deliveredToken);

        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $this->student->email)
            ->first();

        $this->assertNotNull($tokenRow);
        $this->assertTrue(Hash::check($channel->deliveredToken, $tokenRow->token));

        // 3. The freshly delivered token validates and completes password reset cleanly
        $this->postJson('/api/reset-password/validate', [
            'token' => $channel->deliveredToken,
            'email' => $this->student->email,
        ])
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->postJson('/api/reset-password', [
            'token' => $channel->deliveredToken,
            'email' => $this->student->email,
            'password' => 'RecoveredPass789!',
            'password_confirmation' => 'RecoveredPass789!',
        ])->assertOk();

        $this->assertTrue(Hash::check('RecoveredPass789!', $this->student->fresh()->password));
    }

    public function test_repeated_failed_email_deliveries_still_enforce_rate_limiting(): void
    {
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.test.invalid',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
        ]);

        Log::spy();

        $failingChannel = new class {
            public int $attempts = 0;

            public function send(object $notifiable, object $notification): void
            {
                $this->attempts++;
                throw new \RuntimeException('SMTP unreachable', 500);
            }
        };

        Notification::extend('mail', fn () => $failingChannel);
        Notification::forgetDrivers();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/forgot-password', [
                'email' => $this->student->email,
            ])->assertOk();

            $this->assertDatabaseMissing('password_reset_tokens', [
                'email' => $this->student->email,
            ]);
        }

        $this->assertSame(3, $failingChannel->attempts);

        // 4th rapid request is still rate-limited even though delivery kept failing
        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])->assertStatus(429);
    }

    public function test_password_recovery_endpoints_succeed_even_with_stale_bearer_token(): void
    {
        Notification::fake();
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));
        RateLimiter::clear('validate-reset-token:' . sha1($this->student->email . '|127.0.0.1'));
        RateLimiter::clear('reset-password:' . sha1($this->student->email . '|127.0.0.1'));

        $staleToken = $this->student->createToken('stale-browser-session')->plainTextToken;
        $this->student->tokens()->delete();

        $headers = ['Authorization' => 'Bearer ' . $staleToken];

        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ], $headers)->assertOk();

        // Replace with a known token for explicit validation and reset verification
        $rawToken = Password::broker()->createToken($this->student);

        $this->postJson('/api/reset-password/validate', [
            'token' => $rawToken,
            'email' => $this->student->email,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->postJson('/api/reset-password', [
            'token' => $rawToken,
            'email' => $this->student->email,
            'password' => 'ResetWithStaleHeader123!',
            'password_confirmation' => 'ResetWithStaleHeader123!',
        ], $headers)->assertOk();

        $this->assertTrue(Hash::check('ResetWithStaleHeader123!', $this->student->fresh()->password));
    }

    public function test_auth_clear_resets_is_scheduled_and_prunes_only_expired_tokens(): void
    {
        $events = collect(app(Schedule::class)->events());
        $hasClearResets = $events->contains(
            fn ($event) => str_contains((string) $event->command, 'auth:clear-resets')
        );

        $this->assertTrue($hasClearResets, 'Expected auth:clear-resets to be registered in the Laravel scheduler.');

        $otherStudent = User::create([
            'name' => 'Second CCS Student',
            'email' => 'second.student@uclm.edu.ph',
            'student_id_number' => '20240002',
            'password' => Hash::make('OldPassword123!'),
            'role' => 'student',
            'academic_unit_id' => $this->ccsUnit->id,
        ]);

        Password::broker()->createToken($this->student);
        Password::broker()->createToken($otherStudent);

        DB::table('password_reset_tokens')
            ->where('email', $this->student->email)
            ->update(['created_at' => now()->subMinutes(65)]);

        $this->artisan('auth:clear-resets')->assertSuccessful();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->student->email,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $otherStudent->email,
        ]);
    }

    public function test_brevo_api_driver_sends_password_reset_email_with_vercel_url_even_when_mail_mailer_is_log(): void
    {
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));
        Notification::fake();

        config([
            'mail.default' => 'log',
            'mail.password_reset_driver' => 'brevo_api',
            'mail.from.address' => 'slimyzg82@gmail.com',
            'mail.from.name' => 'UCLM SCMS',
            'services.brevo.api_key' => 'xkeysib-synthetic-test-api-key-998877',
            'app.frontend_url' => 'https://scms-uclm.vercel.app',
        ]);

        Http::fake([
            BrevoApiService::ENDPOINT => Http::response([
                'messageId' => '<202611260830.12345@smtp-relay.mailin.fr>',
            ], 201),
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ]);

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'If your email is registered in our system, you will receive a password reset link shortly.'
            );

        // Ensure Laravel Notification mail channel was NOT used when brevo_api is active
        Notification::assertNothingSent();

        // Ensure hashed token was stored in password_reset_tokens
        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $this->student->email)
            ->first();
        $this->assertNotNull($tokenRow);

        $extractedToken = null;

        Http::assertSentCount(1);
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($tokenRow, &$extractedToken) {
            $this->assertSame('POST', $request->method());
            $this->assertSame(BrevoApiService::ENDPOINT, $request->url());
            $this->assertTrue($request->hasHeader('accept', 'application/json'));
            $this->assertTrue($request->hasHeader('api-key', 'xkeysib-synthetic-test-api-key-998877'));
            $this->assertStringContainsString('application/json', $request->header('content-type')[0] ?? '');

            $data = $request->data();
            $this->assertSame('slimyzg82@gmail.com', $data['sender']['email'] ?? null);
            $this->assertSame('UCLM SCMS', $data['sender']['name'] ?? null);
            $this->assertSame($this->student->email, $data['to'][0]['email'] ?? null);
            $this->assertSame($this->student->name, $data['to'][0]['name'] ?? null);
            $this->assertSame('UCLM SCMS — Password Reset Request', $data['subject'] ?? null);

            $html = (string) ($data['htmlContent'] ?? '');
            $text = (string) ($data['textContent'] ?? '');
            $this->assertNotEmpty($html);
            $this->assertNotEmpty($text);

            $this->assertMatchesRegularExpression(
                '#https://scms-uclm\.vercel\.app/reset-password\?token=([^&"\'\s]+)&email=' . preg_quote(urlencode($this->student->email), '#') . '#',
                $html,
                'Expected HTML email to contain the URL-encoded Vercel password-reset link.'
            );

            if (preg_match('#https://scms-uclm\.vercel\.app/reset-password\?token=([^&"\'\s]+)&email=#', $html, $m)) {
                $extractedToken = urldecode($m[1]);
            }

            $this->assertNotNull($extractedToken);
            $this->assertTrue(Hash::check((string) $extractedToken, $tokenRow->token));
            $this->assertStringNotContainsString('http://localhost', $html);

            return true;
        });

        // Verify the token extracted from the Brevo HTML email validates and resets the password
        $this->postJson('/api/reset-password/validate', [
            'token' => $extractedToken,
            'email' => $this->student->email,
        ])->assertOk()->assertJsonPath('valid', true);

        $this->postJson('/api/reset-password', [
            'token' => $extractedToken,
            'email' => $this->student->email,
            'password' => 'BrevoResetPass123!',
            'password_confirmation' => 'BrevoResetPass123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrevoResetPass123!', $this->student->fresh()->password));
    }

    public function test_brevo_api_nonexistent_email_preserves_account_enumeration_protection_and_makes_zero_http_calls(): void
    {
        RateLimiter::clear('forgot-password:' . sha1('ghost.student@uclm.edu.ph|127.0.0.1'));

        config([
            'mail.password_reset_driver' => 'brevo_api',
            'services.brevo.api_key' => 'xkeysib-synthetic-test-api-key-998877',
        ]);

        Http::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'ghost.student@uclm.edu.ph',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If your email is registered in our system, you will receive a password reset link shortly.'
            );

        Http::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'ghost.student@uclm.edu.ph',
        ]);
    }

    public function test_brevo_api_failures_401_403_429_500_timeout_and_missing_key_clean_up_token_and_never_log_secrets(): void
    {
        $secretApiKey = 'xkeysib-ultra-secret-brevo-key-do-not-leak';
        $genericMessage = 'If your email is registered in our system, you will receive a password reset link shortly.';

        // 1. Missing API key: zero HTTP requests, token cleaned up, no secrets logged
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));
        config([
            'mail.default' => 'log',
            'mail.password_reset_driver' => 'brevo_api',
            'services.brevo.api_key' => '',
            'app.frontend_url' => 'https://scms-uclm.vercel.app',
        ]);

        $timeoutInvocations = 0;
        Http::fake([
            BrevoApiService::ENDPOINT => Http::sequence()
                ->push(['code' => 'unauthorized', 'message' => "Simulated 401 with {$secretApiKey}"], 401)
                ->push(['code' => 'forbidden', 'message' => "Simulated 403 with {$secretApiKey}"], 403)
                ->push(['code' => 'too_many_requests', 'message' => "Simulated 429 with {$secretApiKey}"], 429)
                ->push(['code' => 'service_unavailable', 'message' => "Simulated 503 with {$secretApiKey}"], 503)
                ->whenEmpty(function () use (&$timeoutInvocations, $secretApiKey) {
                    $timeoutInvocations++;
                    throw new ConnectionException(
                        "Connection timed out contacting Brevo with key {$secretApiKey}"
                    );
                }),
        ]);
        Log::spy();

        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])->assertOk()->assertJsonPath('message', $genericMessage);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->student->email,
        ]);

        // 2. HTTP 401, 403, 429, and 503 responses: single request each (no duplicate-email retries), token deleted, sanitized log
        config(['services.brevo.api_key' => $secretApiKey]);

        foreach ([401, 403, 429, 503] as $statusCode) {
            RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));

            $this->postJson('/api/forgot-password', [
                'email' => $this->student->email,
            ])->assertOk()->assertJsonPath('message', $genericMessage);

            $this->assertDatabaseMissing('password_reset_tokens', [
                'email' => $this->student->email,
            ]);
        }

        // 3. Connection timeout exception: handled safely, token deleted, sanitized log
        RateLimiter::clear('forgot-password:' . sha1($this->student->email . '|127.0.0.1'));

        $this->postJson('/api/forgot-password', [
            'email' => $this->student->email,
        ])->assertOk()->assertJsonPath('message', $genericMessage);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->student->email,
        ]);

        // 4 recorded HTTP error responses (401, 403, 429, 503) + 1 ConnectionException throw (zero automatic retries)
        Http::assertSentCount(4);
        $this->assertSame(1, $timeoutInvocations);

        $studentEmail = $this->student->email;
        Log::shouldHaveReceived('error')
            ->atLeast()
            ->times(6)
            ->withArgs(function (string $message, array $context = []) use ($secretApiKey, $studentEmail) {
                if ($message !== 'Password reset notification dispatch failed.') {
                    return false;
                }
                $serialized = (string) json_encode($context);
                $this->assertSame('brevo_api', $context['transport'] ?? null);
                $this->assertSame(BrevoApiService::ENDPOINT, $context['endpoint'] ?? null);
                $this->assertStringNotContainsString($secretApiKey, $serialized);
                $this->assertStringNotContainsString($studentEmail, $serialized);
                $this->assertStringNotContainsString('reset-password?token=', $serialized);
                return true;
            });
    }
}
