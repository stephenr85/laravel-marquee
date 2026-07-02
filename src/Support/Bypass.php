<?php

declare(strict_types=1);

namespace Rushing\Marquee\Support;

use Illuminate\Http\Request;
use Rushing\Marquee\Contracts\ModeStore;

/**
 * Derives and validates the durable bypass token. The token is an HMAC of the
 * store's secret, so the value in the cookie never exposes the secret itself
 * and rotating the secret invalidates every outstanding preview link/cookie.
 */
class Bypass
{
    public function __construct(protected ModeStore $store) {}

    public function token(): ?string
    {
        $secret = $this->store->secret();

        if ($secret === null || $secret === '') {
            return null;
        }

        return hash_hmac('sha256', 'marquee-bypass', $secret);
    }

    public function cookieName(): string
    {
        return (string) config('marquee.cookie', 'marquee_bypass');
    }

    public function present(Request $request): bool
    {
        $expected = $this->token();

        if ($expected === null) {
            return false;
        }

        $cookie = $request->cookie($this->cookieName());

        return is_string($cookie) && hash_equals($expected, $cookie);
    }
}
