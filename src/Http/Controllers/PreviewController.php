<?php

declare(strict_types=1);

namespace Rushing\Marquee\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Support\Bypass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Landing endpoint for a signed preview link. A valid signature (enforced by
 * the `signed` middleware on the route) drops a durable bypass cookie and
 * redirects home, so the stakeholder browses the real app without re-signing
 * every internal link. Proxy-agnostic — the right primary bypass for plesk2.
 */
class PreviewController
{
    public function __invoke(Bypass $bypass, ModeStore $store): RedirectResponse
    {
        $token = $bypass->token();

        if ($token === null) {
            throw new NotFoundHttpException;
        }

        return redirect($store->redirect() ?? '/')
            ->withCookie(cookie()->forever($bypass->cookieName(), $token));
    }
}
