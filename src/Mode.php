<?php

declare(strict_types=1);

namespace Rushing\Marquee;

/**
 * The five literal site modes. The theater metaphor lives in the package name;
 * the values stay literal so a `.env` diff needs no glossary.
 */
enum Mode: string
{
    case Live = 'live';
    case Soon = 'soon';
    case Maintenance = 'maintenance';
    case Preview = 'preview';
    case Down = 'down';

    /**
     * Config-declared behavior for this mode (view, status, retry, ...).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config('marquee.modes.'.$this->value, []);
    }

    /** Public traffic is stopped at the gate unless allowlisted. */
    public function isGated(): bool
    {
        return (bool) ($this->config()['gated'] ?? false);
    }
}
