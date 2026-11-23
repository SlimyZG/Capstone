<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the password reset URL pointing to the React frontend Reset Password route.
     *
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $email = urlencode((string) $notifiable->getEmailForPasswordReset());
        $token = urlencode((string) $this->token);

        return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
    }

    /**
     * Build the mail representation of the password reset notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $expireMinutes = (int) config(
            'auth.passwords.' . config('auth.defaults.passwords', 'users') . '.expire',
            60
        );

        return (new MailMessage)
            ->subject('UCLM SCMS — Password Reset Request')
            ->greeting('Hello ' . ($notifiable->name ?: 'UCLM Student') . ',')
            ->line('We received a request to reset the password for your University of Cebu Lapu-Lapu and Mandaue (UCLM) Student Concern Management System account.')
            ->action('Reset Password', $url)
            ->line("This password reset link will expire in {$expireMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required and your password will remain unchanged.');
    }
}
