<?php

declare(strict_types=1);

namespace App\Notifications\Workspace;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewInvite extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Invite $invite,
        private readonly User $inviter,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invite->workspace;
        $acceptUrl = route('invites.accept', ['invite' => $this->invite->uuid]);
        $declineUrl = route('invites.decline', ['invite' => $this->invite->uuid]);

        return (new MailMessage)
            ->subject("Convite para workspace: {$workspace->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$this->inviter->name} convidou você para participar do workspace **{$workspace->name}** como **{$this->invite->role->label()}**.")
            ->action('Aceitar Convite', $acceptUrl)
            ->line('Ou, se preferir, você pode recusar este convite:')
            ->line("Este convite foi enviado por {$this->inviter->email}.")
            ->line('Se você não reconhece este convite, pode ignorar este email com segurança.');
    }
}
