<?php

declare(strict_types=1);

namespace Rushing\Marquee\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;
use Rushing\Marquee\Support\Allowlist;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single seam. Registered in the web group before the Inertia middleware
 * (and thus after TrustProxies + EncryptCookies), so a gated-out request is
 * short-circuited with a plain Blade response and the SPA bundle never boots.
 *
 * Resolution order: escape-hatch path → live/banner pass-through → allowlist →
 * render the current mode's gate.
 */
class EnforceSiteMode
{
    public function __construct(
        protected ModeStore $store,
        protected Allowlist $allowlist,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->pathBypassed($request)) {
            return $next($request);
        }

        $mode = $this->store->mode();

        if (! $mode->isGated()) {
            return $next($request);
        }

        // Degraded-but-in: everyone reaches the real app, React renders a
        // banner from the shared Inertia prop. Never boots a Blade gate.
        if ($this->isBannerMaintenance($mode)) {
            $this->shareBanner($mode);

            return $next($request);
        }

        if ($this->allowlist->allows($request)) {
            return $next($request);
        }

        return $this->gate($request, $mode);
    }

    protected function pathBypassed(Request $request): bool
    {
        foreach ((array) config('marquee.bypass_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function isBannerMaintenance(Mode $mode): bool
    {
        return $mode === Mode::Maintenance
            && ($mode->config()['variant'] ?? 'page') === 'banner';
    }

    protected function shareBanner(Mode $mode): void
    {
        if (class_exists(Inertia::class)) {
            Inertia::share('marquee_mode', $mode->value);
        }
    }

    protected function gate(Request $request, Mode $mode): Response
    {
        $config = $mode->config();

        if (($redirect = $this->store->redirect()) && ! $request->is(ltrim($redirect, '/'))) {
            return redirect($redirect);
        }

        $status = (int) ($config['status'] ?? 200);

        $response = response()->view(
            $config['view'] ?? 'marquee::gate.'.$mode->value,
            ['mode' => $mode],
            $status,
        );

        if ($status === 503) {
            $retry = $this->store->retry() ?? ($config['retry'] ?? 3600);
            $response->header('Retry-After', (string) $retry);
        }

        if ($config['noindex'] ?? false) {
            $response->header('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
