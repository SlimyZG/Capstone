<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a new student account.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'student_id_number' => [
                'required',
                'string',
                'size:8',
                'regex:/^\d{8}$/',
                'unique:users,student_id_number',
                Rule::exists('class_lists', 'student_id_number')->where(function ($query) use ($request) {
                    return $query->where('academic_unit_id', $request->input('academic_unit_id'));
                }),
            ],
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'academic_unit_id' => 'required|exists:academic_units,id',
        ], [
            'student_id_number.size' => 'Student ID must be exactly 8 digits.',
            'student_id_number.regex' => 'Student ID must contain only numeric digits.',
            'student_id_number.unique' => 'This Student ID is already registered.',
            'student_id_number.exists' => 'This Student ID is not in the class list for the selected department.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'student_id_number' => $validated['student_id_number'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
            'academic_unit_id' => $validated['academic_unit_id'],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user->load('academicUnit'),
            'token' => $token,
        ], 201);
    }

    /**
     * Smart Login: auto-detect Student ID (8-digit numeric) vs Email login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginValue = $request->input('login');
        $password = $request->input('password');

        // Determine if login is a student ID (8-digit numeric) or email
        $isStudentId = preg_match('/^\d{8}$/', $loginValue);

        if ($isStudentId) {
            $user = User::where('student_id_number', $loginValue)->first();
        } else {
            $user = User::where('email', $loginValue)->first();
        }

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke existing tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load(['academicUnit', 'department']),
            'token' => $token,
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load(['academicUnit', 'department']),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->load(['academicUnit', 'department']),
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Handle forgot password requests using Laravel's official Password Broker.
     * - Rate-limited per email + IP to prevent abuse and enumeration.
     * - Always returns a generic response so callers cannot determine whether an email is registered.
     * - Never writes plain-text reset tokens or passwords to application logs.
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $throttleKey = 'forgot-password:' . sha1($normalizedEmail . '|' . (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Too many password reset requests. Please wait ' . $seconds . ' seconds before trying again.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $genericMessage = 'If your email is registered in our system, you will receive a password reset link shortly.';

        $user = null;
        $tokens = null;
        $tokenCreated = false;

        try {
            $user = User::where('email', $normalizedEmail)->first();
            if ($user) {
                $tokens = Password::broker()->getRepository();
                if (!$tokens->recentlyCreatedToken($user)) {
                    $token = $tokens->create($user);
                    $tokenCreated = true;

                    // When MAIL_MAILER is 'log' in local development, avoid writing raw tokens into application log files
                    // unless an active mail transport or test fake is intercepting notifications.
                    $mailer = (string) config('mail.default', 'log');
                    $isNotificationFaked = Notification::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\NotificationFake;
                    $isMailFaked = Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake;

                    if ($mailer !== 'log' || $isNotificationFaked || $isMailFaked) {
                        $user->sendPasswordResetNotification($token);
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($tokenCreated && $user && $tokens) {
                try {
                    // Remove the undelivered token so a genuine dispatch failure does not trigger the 60s broker throttle.
                    $tokens->delete($user);
                } catch (\Throwable $cleanupException) {
                    Log::error('Password reset token cleanup failed.', [
                        'exception' => class_basename($cleanupException),
                    ]);
                }
            }

            // Log non-sensitive transport diagnostics only — never log reset tokens, passwords, email addresses, or SMTP credentials.
            $mailer = (string) config('mail.default', 'log');
            Log::error('Password reset notification dispatch failed.', [
                'exception' => class_basename($e),
                'code' => (int) $e->getCode(),
                'mailer' => $mailer,
                'host' => config("mail.mailers.{$mailer}.host"),
                'port' => config("mail.mailers.{$mailer}.port"),
                'scheme' => config("mail.mailers.{$mailer}.scheme"),
            ]);
        }

        return response()->json([
            'message' => $genericMessage,
        ]);
    }

    /**
     * Validate a password reset token and email combination before displaying the new password form.
     */
    public function validateResetToken(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $throttleKey = 'validate-reset-token:' . sha1($normalizedEmail . '|' . (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'valid' => false,
                'message' => 'Too many token verification attempts. Please wait a minute and try again.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $user = Password::broker()->getUser(['email' => $normalizedEmail]);
        if (!$user || !Password::broker()->tokenExists($user, $validated['token'])) {
            return response()->json([
                'valid' => false,
                'message' => 'This password reset link is invalid, expired, or has already been used.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Password reset link is valid.',
        ]);
    }

    /**
     * Reset a user's password using Laravel's official Password Broker.
     * - Verifies token hash and expiration against password_reset_tokens.
     * - Updates password using Hash::make() and revokes existing Sanctum tokens.
     * - Immediately deletes the reset token so it cannot be reused.
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));
        $throttleKey = 'reset-password:' . sha1($normalizedEmail . '|' . (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => 'Too many password reset attempts. Please wait ' . $seconds . ' seconds before trying again.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $status = Password::broker()->reset(
            [
                'email' => $normalizedEmail,
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke existing Sanctum tokens so old sessions are invalidated after password reset
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This password reset link is invalid, expired, or has already been used. Please request a new password reset link.',
                'errors' => [
                    'token' => ['This password reset link is invalid, expired, or has already been used.'],
                ],
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'message' => 'Your password has been reset successfully. You may now sign in with your new password.',
        ]);
    }
}
