<?php

declare(strict_types=1);

namespace Rushing\Marquee\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

class MarqueeModeCommand extends Command
{
    protected $signature = 'marquee:mode
        {mode? : One of live|soon|maintenance|preview|down}
        {--secret= : Bypass token (mirrors `down --secret`)}
        {--retry= : Retry-After seconds for 503 modes}
        {--redirect= : Redirect gated traffic to this path instead of the gate page}';

    protected $description = 'Show or flip the marquee site mode — instant, no redeploy or config:cache clear.';

    public function handle(ModeStore $store): int
    {
        $arg = $this->argument('mode');

        if ($arg === null) {
            return $this->showStatus($store);
        }

        $mode = Mode::tryFrom($arg);

        if ($mode === null) {
            $this->error("Unknown mode [{$arg}]. Expected one of: ".$this->modeList());

            return self::FAILURE;
        }

        $store->set($mode, $this->flagOptions());

        $this->info("Marquee mode → {$mode->value}");

        if ($mode->isGated() && $store->secret()) {
            $this->line('  Preview link: '.$this->previewLink($store));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{secret?: string, retry?: int, redirect?: string}
     */
    protected function flagOptions(): array
    {
        $options = [];

        if (($secret = $this->option('secret')) !== null) {
            $options['secret'] = $secret;
        }
        if (($retry = $this->option('retry')) !== null) {
            $options['retry'] = (int) $retry;
        }
        if (($redirect = $this->option('redirect')) !== null) {
            $options['redirect'] = $redirect;
        }

        return $options;
    }

    protected function showStatus(ModeStore $store): int
    {
        $mode = $store->mode();

        $this->line('Current marquee mode: <info>'.$mode->value.'</info>');
        $this->line('  Gated to public:    '.($mode->isGated() ? 'yes' : 'no'));
        $this->line('  Bypass ability:     '.(config('marquee.allow.ability') ?: '—'));
        $this->line('  IP allowlist:       '.(config('marquee.allow.ips') ? implode(', ', config('marquee.allow.ips')) : 'off'));
        $this->line('  Bypass secret set:  '.($store->secret() ? 'yes' : 'no'));

        if ($store->secret() && Route::has('marquee.preview')) {
            $this->line('  Preview link:       '.$this->previewLink($store));
        }

        return self::SUCCESS;
    }

    protected function previewLink(ModeStore $store): string
    {
        if (! Route::has('marquee.preview')) {
            return '(register the marquee routes to enable preview links)';
        }

        $days = (int) config('marquee.allow.preview_link_ttl_days', 7);

        return URL::temporarySignedRoute('marquee.preview', now()->addDays($days));
    }

    protected function modeList(): string
    {
        return implode('|', array_map(fn (Mode $m) => $m->value, Mode::cases()));
    }
}
