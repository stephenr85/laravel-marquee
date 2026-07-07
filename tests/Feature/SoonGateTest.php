<?php

use Illuminate\Support\Facades\View;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

beforeEach(fn () => app(ModeStore::class)->set(Mode::Soon));

it('renders the soon splash with 200 and a noindex signal', function () {
    $response = $this->get('app-home');

    $response->assertOk();
    $response->assertSee('data-marquee-gate="soon"', false);
    $response->assertHeader('X-Robots-Tag', 'noindex');
});

it('short-circuits before Inertia — the SPA root is never emitted on the gate', function () {
    // The real app route renders `<div id="app">`; a gated request must never
    // reach it, proving the gate returns before the Inertia middleware runs.
    expect($this->get('app-home')->getContent())->not->toContain('id="app"');
});

it('drives the gate view and status from the config mode registry', function () {
    View::addNamespace('marqueetest', __DIR__.'/../Stubs/views');
    config()->set('marquee.modes.soon.view', 'marqueetest::custom-soon');

    $this->get('app-home')->assertSee('CUSTOM SOON SPLASH', false);
});
