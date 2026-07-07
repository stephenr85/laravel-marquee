<?php

use Illuminate\Support\Facades\Cache;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

it('passes requests through to the real app when live', function () {
    $this->get('app-home')
        ->assertOk()
        ->assertSee('REAL APP HOME', false);
});

it('returns a Laravel-native 503 with Retry-After when down', function () {
    app(ModeStore::class)->set(Mode::Down);

    $response = $this->get('app-home');

    $response->assertStatus(503);
    $response->assertSee('data-marquee-gate="down"', false);
    expect($response->headers->get('Retry-After'))->not->toBeNull();
});

it('resolves the stored mode after a cache flush, not the env default', function () {
    app(ModeStore::class)->set(Mode::Down);

    Cache::flush();

    // env default (config marquee.mode) is `live`; the stored mode is `down`.
    expect(app(ModeStore::class)->mode())->toBe(Mode::Down);
    $this->get('app-home')->assertStatus(503);
});

it('always resolves live in a live environment regardless of the stored mode', function () {
    config()->set('marquee.live_environments', ['testing']);
    app(ModeStore::class)->set(Mode::Down);

    expect(app(ModeStore::class)->mode())->toBe(Mode::Live);
    $this->get('app-home')->assertOk()->assertSee('REAL APP HOME', false);
});
