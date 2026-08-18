<?php

namespace App\Models\Concerns;

use App\Helpers\NotificationHelper;

/**
 * Apply this trait to any Eloquent model to get auto-notifications
 * fired on created / updated / deleted. Override $notifyEvents on
 * the model to opt out of specific events:
 *
 *   protected array $notifyEvents = ['created', 'deleted'];
 */
trait LogsNotifications
{
    public static function bootLogsNotifications(): void
    {
        static::created(function ($m) {
            if (self::shouldNotify($m, 'created')) NotificationHelper::record($m, 'created');
        });
        static::updated(function ($m) {
            if (self::shouldNotify($m, 'updated')) NotificationHelper::record($m, 'updated');
        });
        static::deleted(function ($m) {
            if (self::shouldNotify($m, 'deleted')) NotificationHelper::record($m, 'deleted');
        });
    }

    private static function shouldNotify($model, string $event): bool
    {
        if (property_exists($model, 'notifyEvents') && is_array($model->notifyEvents)) {
            return in_array($event, $model->notifyEvents, true);
        }
        return true;
    }
}
