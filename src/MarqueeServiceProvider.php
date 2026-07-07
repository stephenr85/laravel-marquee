<?php

namespace Rushing\Marquee;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Rushing\Marquee\Commands\LiveCommand;
use Rushing\Marquee\Commands\MaintenanceCommand;
use Rushing\Marquee\Commands\MarqueeModeCommand;
use Rushing\Marquee\Commands\PreviewCommand;
use Rushing\Marquee\Commands\SoonCommand;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Stores\DatabaseModeStore;
use Rushing\Marquee\Support\Allowlist;
use Rushing\Marquee\Support\Bypass;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MarqueeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-marquee')
            ->hasConfigFile('marquee')
            ->hasViews('marquee')
            ->hasRoute('marquee')
            ->hasMigration('create_marquee_state_table')
            ->hasCommands([
                MarqueeModeCommand::class,
                LiveCommand::class,
                SoonCommand::class,
                MaintenanceCommand::class,
                PreviewCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModeStore::class, fn ($app) => new DatabaseModeStore($app));
        $this->app->singleton(Bypass::class, fn ($app) => new Bypass($app->make(ModeStore::class)));
        $this->app->singleton(Allowlist::class, fn ($app) => new Allowlist($app->make(Bypass::class)));
    }

    public function packageBooted(): void
    {
        // The bypass cookie must survive as plaintext both ways so it works
        // identically behind the plesk2 proxy and in the test client.
        EncryptCookies::except(config('marquee.cookie', 'marquee_bypass'));
    }
}
