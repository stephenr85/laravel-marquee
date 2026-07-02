<?php

declare(strict_types=1);

use Rushing\Marquee\Mode;

return [

    /*
    |--------------------------------------------------------------------------
    | Boot default mode
    |--------------------------------------------------------------------------
    | MARQUEE_MODE only seeds the store the first time the app boots against an
    | empty store. After that the stored value (flipped via `marquee:mode`) is
    | authoritative — changing this env on a running app does NOT re-gate it.
    */
    'mode' => env('MARQUEE_MODE', Mode::Live->value),

    /*
    |--------------------------------------------------------------------------
    | Environments that always resolve `live`
    |--------------------------------------------------------------------------
    | The gate never fires in these environments regardless of the stored mode,
    | so local blitz-mode dev is never interrupted. Production reads the store.
    */
    'live_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Bypass secret
    |--------------------------------------------------------------------------
    | Seeds the store's bypass token (mirrors `php artisan down --secret`). The
    | signed preview link is built from this; sharing the link drops a durable
    | signed cookie so a stakeholder bypasses the gate for the whole session.
    */
    'secret' => env('MARQUEE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    | DB is the source of truth; the cache is a read-through busted on write, so
    | a cache flush falls back to the DB row, never to the env default.
    */
    'store' => [
        'connection' => env('MARQUEE_DB_CONNECTION'),
        'table' => 'marquee_state',
        'cache_store' => env('MARQUEE_CACHE_STORE'),
        'cache_key' => 'marquee.state',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass cookie
    |--------------------------------------------------------------------------
    */
    'cookie' => 'marquee_bypass',

    /*
    |--------------------------------------------------------------------------
    | Escape-hatch paths
    |--------------------------------------------------------------------------
    | Glob patterns matched BEFORE any mode logic. These survive every gated
    | mode so monitoring and money-in never drop. Satellites append their own.
    */
    'bypass_paths' => [
        'up',
        'marquee/preview',
        'health',
        'healthz',
        'stripe/webhook*',
        'webhooks/*',
        'horizon',
        'horizon/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowlist policy (first-match-wins)
    |--------------------------------------------------------------------------
    */
    'allow' => [
        // Ability checked via Gate::allows(); satellite defines the policy.
        'ability' => 'bypass-marquee',
        // Client IPs. OFF by default — $request->ip() is unreliable behind the
        // plesk2 reverse proxy unless TrustProxies + X-Forwarded-For verified.
        'ips' => [],
        // Days a signed preview link remains valid.
        'preview_link_ttl_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode registry — behavior is data, not scattered code
    |--------------------------------------------------------------------------
    | gated:   public is stopped at the gate unless allowlisted.
    | view:    Blade gate view (namespaced `marquee::gate.*`, satellite-overridable).
    | status:  HTTP status of the gate response.
    | retry:   Retry-After seconds (503 modes only).
    | noindex: emit X-Robots-Tag: noindex on the gate.
    | variant: maintenance only — 'page' (503 gate) or 'banner' (pass through +
    |          share the `marquee_mode` Inertia prop so React renders a banner).
    */
    'modes' => [
        Mode::Live->value => [
            'gated' => false,
        ],
        Mode::Soon->value => [
            'gated' => true,
            'view' => 'marquee::gate.soon',
            'status' => 200,
            'noindex' => true,
        ],
        Mode::Maintenance->value => [
            'gated' => true,
            'variant' => 'page',
            'view' => 'marquee::gate.maintenance',
            'status' => 503,
            'retry' => 3600,
            'noindex' => true,
        ],
        Mode::Preview->value => [
            'gated' => true,
            'view' => 'marquee::gate.preview',
            'status' => 200,
            'noindex' => true,
        ],
        Mode::Down->value => [
            'gated' => true,
            'view' => 'marquee::gate.down',
            'status' => 503,
            'retry' => 3600,
            'noindex' => true,
        ],
    ],

];
