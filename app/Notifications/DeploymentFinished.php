<?php

namespace App\Notifications;

use App\Enums\DeployStatus;
use App\Models\Deployment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentFinished extends Notification
{
    public function __construct(
        private readonly Deployment $deployment,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deployment = $this->deployment;
        $instance = $deployment->instance;
        $succeeded = $deployment->status === DeployStatus::Success;
        $verb = $succeeded ? 'succeeded' : 'failed';

        $mail = (new MailMessage)
            ->subject("Deploy {$verb}: {$instance->name}")
            ->line("Action: {$deployment->action->value}");

        if ($deployment->branch) {
            $mail->line("Branch: {$deployment->branch}");
        }

        return $mail
            ->line($succeeded ? 'The deployment finished successfully.' : 'The deployment failed.')
            ->action('Open instance', route('instances.show', $instance));
    }
}
