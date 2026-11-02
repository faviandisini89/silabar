<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericNotification extends Notification
{
    public function __construct(public string $text, public ?string $route = null)
    {
    }

    public function via(object $notifiable): array
    {
        return config('silabar.mail_notifications') ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['text' => $this->text, 'route' => $this->route];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('SILABAR: '.mb_strimwidth($this->text, 0, 60, '...'))->line($this->text);
    }
}
