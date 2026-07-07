<?php

use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

it('lets health, webhook and Horizon paths through under every gated mode', function () {
    foreach ([Mode::Soon, Mode::Maintenance, Mode::Preview, Mode::Down] as $mode) {
        app(ModeStore::class)->set($mode);

        $this->get('health')->assertOk()->assertSee('HEALTH OK');
        $this->get('stripe/webhook')->assertOk()->assertSee('STRIPE OK');
        $this->get('webhooks/incoming')->assertOk()->assertSee('WEBHOOK OK');

        // A normal route stays gated in the same mode.
        $this->get('app-home')->assertDontSee('REAL APP HOME', false);
    }
});

it('lets a satellite append its own bypass patterns without replacing the defaults', function () {
    app(ModeStore::class)->set(Mode::Soon);

    // Not yet allowlisted — gated.
    $this->get('custom-open')->assertDontSee('CUSTOM OPEN', false);

    config()->set('marquee.bypass_paths', [
        ...config('marquee.bypass_paths'),
        'custom-open',
    ]);

    // Appended pattern passes; the default health path still works too.
    $this->get('custom-open')->assertOk()->assertSee('CUSTOM OPEN');
    $this->get('health')->assertOk()->assertSee('HEALTH OK');
});
