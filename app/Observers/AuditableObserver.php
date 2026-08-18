<?php

namespace App\Observers;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-log create/update/delete/restore for any model it's attached to.
 *
 * Wired in AppServiceProvider::boot() for the 8 key models.
 *
 * Action name comes from `class_basename` lowercased:
 *   Workspace::created       → "workspace.created"
 *   Announcement::updated    → "announcement.updated"
 */
class AuditableObserver
{
    public function created(Model $model): void
    {
        Audit::log($this->prefix($model) . '.created', ['resource' => $model]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if (empty($changes)) return;

        Audit::log($this->prefix($model) . '.updated', [
            'resource' => $model,
            'meta'     => ['changes' => $this->compactChanges($changes)],
        ]);
    }

    public function deleted(Model $model): void
    {
        Audit::log($this->prefix($model) . '.deleted', ['resource' => $model]);
    }

    public function restored(Model $model): void
    {
        Audit::log($this->prefix($model) . '.restored', ['resource' => $model]);
    }

    private function prefix(Model $model): string
    {
        return strtolower(class_basename($model));
    }

    /**
     * Strip blob-like columns from change set so audit meta JSON stays small.
     */
    private function compactChanges(array $changes): array
    {
        $out = [];
        foreach ($changes as $key => $value) {
            if (is_string($value) && strlen($value) > 500) {
                $out[$key] = '<' . strlen($value) . ' chars>';
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
