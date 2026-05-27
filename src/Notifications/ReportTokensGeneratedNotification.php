<?php

namespace Illimi\Gradebook\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportTokensGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int   $count,
        public array $context
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->count} Report Token(s) Generated Successfully")
            ->greeting("Hello {$notifiable->name},")
            ->line("**{$this->count}** report token(s) have been generated and are now ready for distribution.")
            ->action('View Tokens', url('/gradebook/tokens'))
            ->salutation('Regards, ' . config('app.name'));
    }
}
