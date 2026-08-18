<?php

namespace App\Providers;

use App\Services\ModuleLoader;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots in-place add-on modules (see App\Services\ModuleLoader): their
 * migrations become discoverable by `php artisan migrate` and their Blade
 * views resolve, all WITHOUT the files ever being copied into the core tree.
 * PSR-4 autoloading + route loading happen earlier, in bootstrap/app.php.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (ModuleLoader::migrationDirs() as $dir) {
            $this->loadMigrationsFrom($dir);
        }

        // Add each module's views to the finder so `view('instagram.posts.index')`
        // and `admin.instagram.settings` resolve from the addon folder.
        foreach (ModuleLoader::viewDirs() as $dir) {
            View::addLocation($dir);
        }
    }
}
