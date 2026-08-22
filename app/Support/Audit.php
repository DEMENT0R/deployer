<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Instance;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Audit
{
    /**
     * Запись в журнал. Падение самой записи не должно ронять действие, которое она описывает:
     * не смогли записать — жалуемся в лог приложения и идём дальше.
     *
     * @param  string  $action  Машинный тип: `instance.updated`, `env.updated`, `screen.stopped`.
     * @param  ?string  $subject  Над чем: имя инстанса, e-mail пользователя, имя сессии.
     * @param  ?string  $summary  Что именно изменилось, человеческим языком.
     */
    public static function record(
        string $action,
        ?string $subject = null,
        ?string $summary = null,
        ?Instance $instance = null,
    ): void {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'instance_id' => $instance?->id,
                'action' => $action,
                'subject' => $subject,
                'summary' => $summary,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
