<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

it('gates the public but lets authenticated staff through', function () {
    app(ModeStore::class)->set(Mode::Soon);

    // Public is gated.
    $this->get('app-home')->assertSee('data-marquee-gate="soon"', false);

    // Staff passing the ability reach the real app.
    Gate::define('bypass-marquee', fn ($user = null) => true);
    $this->get('app-home')->assertSee('REAL APP HOME', false);
});

it('drops a durable bypass cookie from a signed preview link that persists across requests', function () {
    app(ModeStore::class)->set(Mode::Soon, ['secret' => 'shh-secret']);

    $link = URL::temporarySignedRoute('marquee.preview', now()->addDay());

    $response = $this->get($link);
    $response->assertRedirect('/');

    $cookie = collect($response->headers->getCookies())
        ->firstWhere('getName', 'marquee_bypass')
        ?? collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'marquee_bypass');

    expect($cookie)->not->toBeNull();

    // A subsequent request carrying the cookie reaches the real app.
    $this->withUnencryptedCookie('marquee_bypass', $cookie->getValue())
        ->get('app-home')
        ->assertSee('REAL APP HOME', false);
});

it('ignores the IP allowlist by default', function () {
    app(ModeStore::class)->set(Mode::Soon);

    $this->get('app-home')->assertSee('data-marquee-gate="soon"', false);
});

it('bypasses when a client IP is explicitly allowlisted', function () {
    config()->set('marquee.allow.ips', ['127.0.0.1']);
    app(ModeStore::class)->set(Mode::Soon);

    $this->get('app-home')->assertSee('REAL APP HOME', false);
});
