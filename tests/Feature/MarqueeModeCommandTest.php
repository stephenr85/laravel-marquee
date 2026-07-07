<?php

use Illuminate\Support\Facades\Cache;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

it('flips the mode instantly — the next request reflects it with no config:cache clear', function () {
    $this->artisan('marquee:mode', ['mode' => 'soon'])->assertSuccessful();

    $this->get('app-home')->assertSee('data-marquee-gate="soon"', false);
});

it('prints the current mode and allowlist summary with no argument', function () {
    $this->artisan('marquee:mode')
        ->expectsOutputToContain('Current marquee mode: live')
        ->assertSuccessful();
});

it('rejects an unknown mode', function () {
    $this->artisan('marquee:mode', ['mode' => 'bogus'])->assertFailed();
});

it('routes the aliases through marquee:mode', function () {
    $this->artisan('marquee:soon')->assertSuccessful();
    expect(app(ModeStore::class)->mode())->toBe(Mode::Soon);

    $this->artisan('marquee:maintenance')->assertSuccessful();
    expect(app(ModeStore::class)->mode())->toBe(Mode::Maintenance);

    $this->artisan('marquee:live')->assertSuccessful();
    expect(app(ModeStore::class)->mode())->toBe(Mode::Live);
});

it('accepts and applies the --secret and --retry flags', function () {
    $this->artisan('marquee:mode', [
        'mode' => 'down',
        '--secret' => 'shh',
        '--retry' => '120',
    ])->assertSuccessful();

    expect(app(ModeStore::class)->secret())->toBe('shh');
    expect(app(ModeStore::class)->retry())->toBe(120);

    $this->get('app-home')->assertHeader('Retry-After', '120');
});

it('does not let a changed MARQUEE_MODE env override an already-stored mode', function () {
    $this->artisan('marquee:mode', ['mode' => 'soon'])->assertSuccessful();

    // Simulate someone editing .env after first boot, then a cache flush.
    config()->set('marquee.mode', 'down');
    Cache::flush();

    expect(app(ModeStore::class)->mode())->toBe(Mode::Soon);
});
