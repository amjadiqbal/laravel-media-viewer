<?php

namespace AmjadIqbal\MediaViewer;

use AmjadIqbal\MediaViewer\Console\AssetsCommand;
use AmjadIqbal\MediaViewer\Http\Controllers\AssetController;
use AmjadIqbal\MediaViewer\Http\Controllers\FileController;
use AmjadIqbal\MediaViewer\Livewire\MediaGallery;
use AmjadIqbal\MediaViewer\Support\Assets;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class MediaViewerServiceProvider extends ServiceProvider
{
    public const SUPPORT_URL = 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-media-viewer';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-viewer.php', 'media-viewer');

        $this->app->singleton(MediaViewer::class);
        $this->app->scoped(Assets::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'media-viewer');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'media-viewer');

        $this->registerRoutes();

        Blade::componentNamespace('AmjadIqbal\\MediaViewer\\View\\Components', 'media-viewer');
        Blade::directive('mediaViewerAssets', fn () => '<?php echo app('.Assets::class.'::class)->render(true); ?>');

        if (class_exists(Livewire::class)) {
            Livewire::component('media-viewer-gallery', MediaGallery::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/media-viewer.php' => config_path('media-viewer.php')], 'media-viewer-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/media-viewer')], 'media-viewer-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/media-viewer')], 'media-viewer-lang');

            $this->commands([AssetsCommand::class]);

            AboutCommand::add('Media Viewer', fn () => [
                'Version' => InstalledVersions::isInstalled('amjadiqbal/laravel-media-viewer')
                    ? (string) InstalledVersions::getPrettyVersion('amjadiqbal/laravel-media-viewer')
                    : 'unknown',
                'pdf.js / Video.js' => Assets::pinned()['pdfjs']['version'].' / '.Assets::pinned()['videojs']['version'],
                'Assets' => (string) config('media-viewer.assets.source'),
                'Disks' => implode(', ', (array) config('media-viewer.disks')),
                'Support' => self::SUPPORT_URL,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix((string) config('media-viewer.route.prefix', 'media-viewer'))
            ->group(function (): void {
                Route::get('file/{disk}/{path}', FileController::class)
                    ->where('path', '.*')
                    ->middleware((array) config('media-viewer.route.middleware', ['web']))
                    ->name('media-viewer.file');

                Route::get('assets/{file}', AssetController::class)
                    ->where('file', 'media-viewer\.(js|css)')
                    ->name('media-viewer.asset');
            });
    }
}
