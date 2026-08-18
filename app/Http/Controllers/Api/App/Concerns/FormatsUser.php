<?php

namespace App\Http\Controllers\Api\App\Concerns;

use App\Models\User;

/**
 * Shared user-shaping for the mobile API. The app's data model reads
 * `user.image` (a full URL) + `user.is_verified`; our schema stores an
 * `avatar_path` and uses `email_verified_at`, so we bridge the two here so
 * every auth/profile endpoint returns the identical shape the app expects.
 */
trait FormatsUser
{
    protected function avatarUrl(?User $user): ?string
    {
        $p = $user?->avatar_path;
        if (empty($p)) {
            return null;
        }
        if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
            return $p;
        }
        // Uploaded via this API → public/images/users; otherwise fall back
        // to the active media disk (cloud or local) for avatars saved by the
        // web app.
        if (file_exists(public_path($p))) {
            return asset($p);
        }
        return media_url($p);
    }

    protected function userPayload(User $user): array
    {
        $data = $user->toArray();
        $img = $this->avatarUrl($user);
        $data['image'] = $img;
        $data['image_url'] = $img;
        $data['is_verified'] = $user->email_verified_at ? 1 : 0;

        return $data;
    }
}
