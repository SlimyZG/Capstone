<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Http;

/**
 * Dispatches SCMS password-reset emails through Brevo's HTTPS Transactional Email API.
 *
 * Used when PASSWORD_RESET_MAIL_DRIVER=brevo_api (for example on Railway production
 * where outbound SMTP ports are disabled). Shares ResetPasswordNotification as the
 * single source of truth for the reset URL, subject, and email content.
 */
class BrevoApiService
{
    /** Official fixed Brevo v3 Transactional Email HTTPS endpoint. */
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Send a password-reset email to the given user via Brevo's HTTPS API.
     *
     * Note: A 2xx response indicates Brevo accepted the transactional request for
     * processing, not guaranteed final inbox delivery.
     *
     * @throws \RuntimeException|\Illuminate\Http\Client\ConnectionException
     */
    public function sendPasswordResetEmail(User $user, string $token): void
    {
        $apiKey = trim((string) config('services.brevo.api_key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.', 500);
        }

        $senderEmail = trim((string) config('mail.from.address', ''));
        if ($senderEmail === '') {
            throw new \RuntimeException('Mail sender address is not configured.', 500);
        }

        $senderName = trim((string) config('mail.from.name', config('app.name', 'UCLM SCMS')));
        $recipientEmail = trim((string) $user->getEmailForPasswordReset());
        $recipientName = trim((string) ($user->name ?: 'UCLM Student'));

        $notification = new ResetPasswordNotification($token);
        $mailMessage = $notification->toMail($user);
        $resetUrl = $notification->buildResetUrl($user);

        $renderedHtml = (string) $mailMessage->render();
        // Keep the exact URL-encoded reset link unescaped inside the rendered HTML and
        // ensure the email header title link points to the configured frontend origin.
        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://scms-uclm.vercel.app'), '/');
        $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $htmlContent = str_replace(
            [
                htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8'),
                'href="' . $appUrl . '"',
                'href="' . htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') . '"',
            ],
            [
                $resetUrl,
                'href="' . $frontendUrl . '"',
                'href="' . $frontendUrl . '"',
            ],
            $renderedHtml
        );

        $textLines = array_filter([
            $mailMessage->greeting ?: ('Hello ' . $recipientName . ','),
            ...$mailMessage->introLines,
            ($mailMessage->actionText ?: 'Reset Password') . ': ' . $resetUrl,
            ...$mailMessage->outroLines,
        ]);
        $textContent = implode("\n\n", $textLines);
        $subject = (string) ($mailMessage->subject ?: 'UCLM SCMS — Password Reset Request');

        $timeout = max(1, (int) config('services.brevo.timeout', 15));

        // Single request with no automatic retries to prevent duplicate reset emails.
        $response = Http::withHeaders([
            'accept'       => 'application/json',
            'api-key'      => $apiKey,
            'content-type' => 'application/json',
        ])
            ->timeout($timeout)
            ->post(self::ENDPOINT, [
                'sender' => [
                    'email' => $senderEmail,
                    'name'  => $senderName,
                ],
                'to' => [
                    [
                        'email' => $recipientEmail,
                        'name'  => $recipientName,
                    ],
                ],
                'subject'     => $subject,
                'htmlContent' => $htmlContent,
                'textContent' => $textContent,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Brevo transactional email API request failed.',
                $response->status()
            );
        }
    }
}
