<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

it('serves a 503 maintenance page with Retry-After in the page variant', function () {
    app(ModeStore::class)->set(Mode::Maintenance);

    $response = $this->get('app-home');

    $response->assertStatus(503);
    $response->assertSee('data-marquee-gate="maintenance"', false);
    expect($response->headers->get('Retry-After'))->not->toBeNull();
});

it('passes through with a shared marquee_mode Inertia prop in the banner variant', function () {
    config()->set('marquee.modes.maintenance.variant', 'banner');
    app(ModeStore::class)->set(Mode::Maintenance);

    // The app stays responsive — no Blade gate, no broken SPA boot.
    $this->get('app-home')->assertOk()->assertSee('REAL APP HOME', false);

    expect(Inertia::getShared('marquee_mode'))->toBe('maintenance');
})->skip(! class_exists(Inertia::class), 'inertia-laravel not installed');

it('gates the public in preview mode but lets allowlisted visitors reach the app', function () {
    app(ModeStore::class)->set(Mode::Preview);

    $this->get('app-home')->assertSee('data-marquee-gate="preview"', false);

    Gate::define('bypass-marquee', fn ($user = null) => true);
    $this->get('app-home')->assertSee('REAL APP HOME', false);
});
