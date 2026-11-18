<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceiptReady extends Notification
{
    use Queueable;

    public function __construct(
        protected string $reference,
        protected int $totalCents,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your receipt is ready')
            ->greeting('Thanks for your order')
            ->line(sprintf(
                'Order %s, €%s.',
                $this->reference,
                number_format($this->totalCents / 100, 2),
            ))
            ->line('The full receipt is in your account.');
    }
}
