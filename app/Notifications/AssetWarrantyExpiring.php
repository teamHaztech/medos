<?php

namespace App\Notifications;

use App\Modules\Asset\Models\AssetWarranty;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetWarrantyExpiring extends Notification
{
    use Queueable;

    /**
     * @param array<int,AssetWarranty> $warranties Expiring warranties for one hospital
     */
    public function __construct(public iterable $warranties)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Asset warranties expiring soon')
            ->greeting('Warranty expiry alert')
            ->line('The following hospital assets have warranties/contracts expiring soon:');

        foreach ($this->warranties as $w) {
            $name = $w->asset?->asset_name ?? 'Asset';
            $mail->line("• {$name} — {$w->typeLabel()} ends " . optional($w->end_date)->format('M d, Y') . " ({$w->daysToExpiry()} days)");
        }

        return $mail->line('Please arrange renewal or service as required.');
    }
}
