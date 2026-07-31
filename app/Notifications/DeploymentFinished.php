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
     * Единственное место, где решается, куда уходит уведомление: этим же списком
     * джоба проверяет, стоит ли вообще его отправлять.
     *
     * @return list<string>
     */
    public static function enabledChannels(): array
    {
        return array_values(array_filter([
            config('deployer.notify_in_panel', true) ? 'database' : null,
            config('deployer.notify_on_finish', false) ? 'mail' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return self::enabledChannels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $deployment = $this->deployment;

        return [
            'deployment_id' => $deployment->id,
            'instance_id' => $deployment->instance->id,
            'instance_name' => $deployment->instance->name,
            'action' => $deployment->action->value,
            'branch' => $deployment->branch,
            'status' => $deployment->status->value,
        ];
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
