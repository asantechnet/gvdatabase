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

        VerifyEmail::toMailUsing(function (object $notifiable, string $verificationUrl): MailMessage {
            $message = (new MailMessage)
                ->subject('Verify Email Address')
                ->greeting('Hello!')
                ->line('Please verify your email address to finish setting up your account.')
                ->action('Verify Email Address', $verificationUrl)
                ->line('If you did not create this account, no further action is required.');

            $from = app(CompanyProfile::class)->mailFrom();
            if ($from) {
                $message->from($from['address'], $from['name']);
            }

            return $message;
        });
    }
}
