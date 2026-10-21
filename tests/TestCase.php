<?php

namespace AmjadIqbal\MediaViewer\Tests;

use AmjadIqbal\MediaViewer\MediaViewerServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected string $root;

    protected function getPackageProviders($app)
    {
        return [LivewireServiceProvider::class, MediaViewerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->root = sys_get_temp_dir().'/media-viewer-tests-'.getmypid();
        (new Filesystem)->deleteDirectory($this->root);
        (new Filesystem)->ensureDirectoryExists($this->root);

        tap($app['config'], function (Repository $config) {
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
            $config->set('filesystems.default', 'local');
            $config->set('filesystems.disks.local', ['driver' => 'local', 'root' => $this->root.'/local', 'throw' => false]);
            $config->set('filesystems.disks.public', ['driver' => 'local', 'root' => $this->root.'/public', 'throw' => false]);
            $config->set('filesystems.disks.secret', ['driver' => 'local', 'root' => $this->root.'/secret', 'throw' => false]);
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }
}
