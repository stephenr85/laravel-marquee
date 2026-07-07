<?php

namespace Rushing\Marquee\Contracts;

use Rushing\Marquee\Mode;

interface ModeStore
{
    /** The mode a request is resolved against right now. */
    public function mode(): Mode;

    /**
     * Flip the mode (and optionally persist bypass secret / retry / redirect).
     *
     * @param  array{secret?: ?string, retry?: ?int, redirect?: ?string}  $options
     */
    public function set(Mode $mode, array $options = []): void;

    /** Bypass token mirroring `php artisan down --secret`. */
    public function secret(): ?string;

    /** Retry-After seconds for 503 modes, if overridden. */
    public function retry(): ?int;

    /** Post-bypass redirect target, if configured. */
    public function redirect(): ?string;
}
