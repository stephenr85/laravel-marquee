# Laravel Marquee

A site-mode switch for Laravel apps.

Think of the marquee out front of a theater: the sign says *now showing*, *coming soon*, or *dark tonight* — independent of whether the show is actually running inside. Marquee is that sign for your app. At its simplest it's a coming-soon page. Underneath, it's a small state machine you can flip at runtime **without a redeploy**.

## The 30-second version

```bash
php artisan marquee:soon
```

The public now sees a launching-soon splash. When you're ready:

```bash
php artisan marquee:live
```

That's the launch. No redeploy, no `config:cache` clear — the switch takes effect on the very next request.

## "Isn't that just `php artisan down`?"

No — and the difference is the whole reason this package exists.

`php artisan down` is **downtime**. Every route returns 503, it's built for "we're mid-migration, back in five minutes," and you flip it off by redeploying or clearing state. You don't leave an app in `down` for three weeks while you build a launch.

Marquee is for the **deployed-but-not-launched** app. The real thing is running on the server — health checks green, webhooks flowing, your team logged in and using it — but the public sees a coming-soon page until you decide to go live. The current mode lives in your database (read through a cache), not in a config file, so flipping it is instant and reversible.

The normal launch flow becomes:

1. Deploy with `MARQUEE_MODE=soon` in `.env`. The public sees the splash from the first request.
2. Build, test, and preview against the real production box.
3. `php artisan marquee:mode live` when you're ready. Done.

Pull it back just as fast: `php artisan marquee:soon`.

## Installation

Marquee resolves from git. Add the repository to your app's `composer.json`:

```json
"repositories": [
    { "type": "git", "url": "https://github.com/stephenr85/laravel-marquee.git" }
]
```

Then:

```bash
composer require rushing/laravel-marquee:dev-main

php artisan vendor:publish --tag="marquee-migrations"
php artisan migrate
```

Register the middleware in your web group. In Laravel 11+ (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Rushing\Marquee\Middleware\EnforceSiteMode::class,
    ]);
})
```

Appending (rather than prepending) runs the gate after session and auth are resolved, so the staff bypass below works.

That's the whole setup. `local` and `testing` environments always resolve `live`, so the gate never interrupts local development.

## Five modes, not two

`live` and `down` are the two you'd expect. The three in between are where the name earns its keep.

| Mode | Who gets through | HTTP status | What renders |
| --- | --- | --- | --- |
| `live` | everyone | 200 | your app |
| `soon` | allowlist; public sees the gate | 200 | coming-soon splash |
| `maintenance` | everyone (responsive) | 503 *or* in-app banner | notice page / banner |
| `preview` | allowlist only | 200 | your app, behind the gate |
| `down` | allowlist only | 503 | hard offline page |

- **`soon`** — pre-launch. Public sees a splash; the site emits `noindex` so it doesn't get crawled early.
- **`maintenance`** — the site is *up*. Show a responsive "we're doing maintenance" page (503 + `Retry-After`), or leave the app running and drop a banner on top (see Inertia, below). Distinct from a hard `down`.
- **`preview`** — invite-only. Run a closed beta on production; only people you let in reach the app.
- **`down`** — a real hard 503 + `Retry-After`, for actual deploys and migrations.

Flip to any of them the same way:

```bash
php artisan marquee:mode maintenance
php artisan marquee:mode preview
php artisan marquee:mode down
```

Run `php artisan marquee:mode` with no argument to print the current mode and who's allowed through — handy for confirming production state over SSH.

## Letting the right people in

While the public sees the gate, you and the people you invite see the real app. Three ways through, checked in order:

**1. A signed preview link (the default, and the one to reach for).** Share a link, not credentials. Visiting it drops a durable signed cookie, so the recipient browses the whole app without re-signing every page. It's proxy-agnostic, which matters behind a reverse proxy. Set a secret and generate one:

```bash
php artisan marquee:mode soon --secret=whatever-you-like
php artisan marquee:mode          # prints the current preview link
```

**2. Authenticated staff.** Define a `bypass-marquee` gate ability in your app and anyone who passes it walks straight through:

```php
Gate::define('bypass-marquee', fn ($user) => $user->isStaff());
```

**3. An IP allowlist.** Off by default — `$request->ip()` is unreliable behind a proxy unless you've verified `TrustProxies` + `X-Forwarded-For`. Turn it on in config if your setup is sound.

## Things that must never go dark

Health checks, payment webhooks, and your queue dashboard should keep working no matter what mode you're in. Marquee lets a configurable list of paths bypass the gate entirely, matched *before* any mode logic:

```php
// config/marquee.php
'bypass_paths' => [
    'up', 'health', 'healthz',
    'stripe/webhook*', 'webhooks/*',
    'horizon', 'horizon/*',
    // add your own
],
```

Queue workers and scheduled commands never touch HTTP middleware, so they're unaffected regardless — this list is only for HTTP endpoints (dashboards, webhooks, uptime pings).

## Inertia, React, and Vue apps

Marquee is SPA-aware, and follows one rule:

- **Gated out → plain Blade.** A `soon` / `down` page ships as static HTML and never boots your JavaScript bundle. No point spinning up the SPA to show a splash.
- **Degraded but in → a shared prop.** The `maintenance` banner variant lets the request through to the real app and shares a `marquee_mode` prop (via Inertia) so your front-end can render a banner without breaking the SPA.

Switch `maintenance` to the banner variant in config:

```php
// config/marquee.php
'modes' => [
    'maintenance' => ['variant' => 'banner', /* ... */],
],
```

```jsx
// then in your layout
const { marquee_mode } = usePage().props
{marquee_mode === 'maintenance' && <MaintenanceBanner />}
```

Inertia is optional — if it isn't installed, the banner path simply no-ops and the page-variant (503) still works.

## Customizing the gate pages

Publish the views and edit them:

```bash
php artisan vendor:publish --tag="marquee-views"
# resources/views/vendor/marquee/gate/soon.blade.php, etc.
```

Or point a mode at one of your own views without touching the package:

```php
// config/marquee.php
'modes' => [
    'soon' => ['view' => 'gates.launching-soon', /* ... */],
],
```

The default pages are deliberately plain — a dark background and a line of text — so they look intentional out of the box and obvious to replace.

## Configuration

Two env vars and one config file. Publish it with `php artisan vendor:publish --tag="marquee-config"`.

```dotenv
MARQUEE_MODE=live      # boot default only — see below
MARQUEE_SECRET=        # bypass token for preview links
```

`config/marquee.php` controls the per-mode behavior (view, status, `Retry-After`, `noindex`), the allowlist policy, the escape-hatch paths, and the store. Every mode's behavior is data, not scattered code — adding or adjusting a gated mode is a config edit.

## How it decides the mode

`MARQUEE_MODE` only seeds the **boot default** — the value used the first time the app runs against an empty store. After that, the stored mode is authoritative:

- The mode lives in a single-row `marquee_state` table (the source of truth), read through a cache that's busted on every write.
- `php artisan marquee:mode <mode>` writes that row and clears the cache, so the next request reflects it — no redeploy, no `config:cache` clear.
- Clearing your cache can't accidentally re-gate a launched site or un-gate an unlaunched one: a cache miss falls back to the database, never to the env default.
- `local` and `testing` always resolve `live`, regardless of what's stored.

Editing `MARQUEE_MODE` in `.env` on an already-running app does nothing — the store already has a value. That's intentional: the command is the runtime authority.

## Commands

| Command | Does |
| --- | --- |
| `marquee:mode` | print the current mode and allowlist |
| `marquee:mode <mode>` | flip to a mode |
| `marquee:live` / `:soon` / `:maintenance` / `:preview` | aliases for the above |
| `marquee:mode down` | hard 503 (Laravel-native behavior) |

Flags on `marquee:mode` (and the aliases where they apply): `--secret` (bypass token), `--retry` (`Retry-After` seconds), `--redirect` (send gated traffic to a path instead of the gate page).

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13

## License

MIT.
