<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pre-approved WhatsApp / chat templates. The chat picker
 * groups these by category (marketing / utility /
 * authentication) — matching the three Meta-approved
 * categories from the old categories lookup table.
 *
 * Class is `ChatTemplate` (not `Template`) because we already
 * have a `templates` blade folder + a future `Template` model
 * earmarked for the broader campaign template system.
 */
class ChatTemplate extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'chat_templates';

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'tone',
        'body',
        'media_path',
        'media_type',
        'status',
    ];

    public const CATEGORIES = ['marketing', 'utility', 'authentication'];

    public function scopeApproved(Builder $q): Builder
    {
        return $q->whereIn('status', ['approved', 'public']);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        if (!$category || $category === 'all' || !in_array($category, self::CATEGORIES, true)) {
            return $q;
        }
        return $q->where('category', $category);
    }
}
