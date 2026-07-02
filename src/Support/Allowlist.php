<?php

declare(strict_types=1);

namespace Rushing\Marquee\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * First-match-wins bypass resolution. Order is deliberate and plesk2-aware:
 * the signed-cookie path is primary because it is proxy-agnostic; IP matching
 * is last and off by default because $request->ip() is unreliable behind the
 * reverse proxy unless TrustProxies + X-Forwarded-For are verified.
 */
class Allowlist
{
    public function __construct(protected Bypass $bypass) {}

    public function allows(Request $request): bool
    {
        // 1. Signed preview cookie (primary).
        if ($this->bypass->present($request)) {
            return true;
        }

        // 2. Authenticated staff via a satellite-defined ability.
        $ability = config('marquee.allow.ability', 'bypass-marquee');

        if ($ability && Gate::has($ability) && Gate::allows($ability)) {
            return true;
        }

        // 3. IP allowlist (opt-in only).
        if ($this->ipAllowed($request)) {
            return true;
        }

        return false;
    }

    protected function ipAllowed(Request $request): bool
    {
        $ips = (array) config('marquee.allow.ips', []);

        return $ips !== [] && in_array($request->ip(), $ips, true);
    }
}
