<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use App\Support\CompanyProfile;
use App\Models\Board;
use App\Models\Comment;
use App\Models\Subtask;
use App\Models\Task;
use App\Policies\BoardPolicy;
use App\Policies\CommentPolicy;
use App\Policies\SubtaskPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Board::class, BoardPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Subtask::class, SubtaskPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return url('/reset-password').'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $resetUrl): MailMessage {
            $message = (new MailMessage)
                ->subject('Reset Password Notification')
                ->greeting('Hello!')
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset Password', $resetUrl)
                ->line('This password reset link will expire in 60 minutes.')
                ->line('If you did not request a password reset, no further action is required.');

            return $this->applyCompanyMailSettings($message);
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            if (method_exists($notifiable, 'roleNames') && in_array('applicant', $notifiable->roleNames(), true)) {
                $message = (new MailMessage)
                    ->subject('Set up your educational visa applicant portal')
                    ->greeting('Hello '.$notifiable->name.'!')
                    ->line('Your educational visa agency has created a secure applicant portal for your case.')
                    ->line('Verify your email address, then create your password to view your case progress and update your profile.')
                    ->action('Set Up My Applicant Portal', $verificationUrl)
                    ->line('For your security, ignore this message if you were not expecting an invitation and contact the agency directly.');

                return $this->applyCompanyMailSettings($message);
            }

            $message = (new MailMessage)
                ->subject('Verify Email Address')
                ->greeting('Hello!')
                ->line('Please verify your email address to finish setting up your account.')
                ->action('Verify Email Address', $verificationUrl)
                ->line('If you did not create this account, no further action is required.');

            return $this->applyCompanyMailSettings($message);
        });
    }

    private function applyCompanyMailSettings(MailMessage $message): MailMessage
    {
        $profile = app(CompanyProfile::class);
        if ($mailer = $profile->configureMailer()) {
            $message->mailer($mailer);
        }

        if ($from = $profile->mailFrom()) {
            $message->from($from['address'], $from['name']);
        }

        if ($replyTo = $profile->replyTo()) {
            $message->replyTo($replyTo);
        }

        return $message;
    }
}
