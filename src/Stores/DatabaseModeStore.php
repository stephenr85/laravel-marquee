<?php

namespace Rushing\Marquee\Stores;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Rushing\Marquee\Contracts\ModeStore;
use Rushing\Marquee\Mode;

/**
 * DB row is the source of truth; the cache is a read-through busted on every
 * write. A cache flush falls back to the DB row — never to the env default —
 * so clearing cache can neither re-gate a launched site nor un-gate an
 * unlaunched one. MARQUEE_MODE only seeds the row the first time it is missing.
 */
class DatabaseModeStore implements ModeStore
{
    public function __construct(protected Application $app) {}

    public function mode(): Mode
    {
        if ($this->forcedLive()) {
            return Mode::Live;
        }

        return Mode::from($this->state()['mode']);
    }

    public function set(Mode $mode, array $options = []): void
    {
        $state = $this->read();
        $state['mode'] = $mode->value;

        foreach (['secret', 'retry', 'redirect'] as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null) {
                $state[$key] = $options[$key];
            }
        }

        $this->persist($state);
        $this->cache()->forget($this->cacheKey());
    }

    public function secret(): ?string
    {
        return $this->state()['secret'] ?? config('marquee.secret');
    }

    public function retry(): ?int
    {
        $retry = $this->state()['retry'] ?? null;

        return $retry === null ? null : (int) $retry;
    }

    public function redirect(): ?string
    {
        return $this->state()['redirect'] ?? null;
    }

    /**
     * Hard-default to live in dev/test environments so the gate never fires
     * during a blitz-mode dev loop.
     */
    protected function forcedLive(): bool
    {
        return in_array(
            $this->app->environment(),
            (array) config('marquee.live_environments', ['local', 'testing']),
            true,
        );
    }

    /**
     * @return array{mode: string, secret: ?string, retry: ?int, redirect: ?string}
     */
    protected function state(): array
    {
        return $this->cache()->rememberForever($this->cacheKey(), fn () => $this->read());
    }

    /**
     * @return array{mode: string, secret: ?string, retry: ?int, redirect: ?string}
     */
    protected function read(): array
    {
        $row = $this->query()->first();

        if ($row === null) {
            $seed = [
                'mode' => (string) config('marquee.mode', Mode::Live->value),
                'secret' => config('marquee.secret'),
                'retry' => null,
                'redirect' => null,
            ];

            $this->persist($seed);

            return $seed;
        }

        return [
            'mode' => $row->mode,
            'secret' => $row->secret,
            'retry' => $row->retry === null ? null : (int) $row->retry,
            'redirect' => $row->redirect,
        ];
    }

    /**
     * @param  array{mode: string, secret: ?string, retry: ?int, redirect: ?string}  $state
     */
    protected function persist(array $state): void
    {
        $this->query()->updateOrInsert(['id' => 1], [
            'mode' => $state['mode'],
            'secret' => $state['secret'] ?? null,
            'retry' => $state['retry'] ?? null,
            'redirect' => $state['redirect'] ?? null,
            'updated_at' => now(),
        ]);
    }

    protected function query(): Builder
    {
        return DB::connection(config('marquee.store.connection'))
            ->table((string) config('marquee.store.table', 'marquee_state'));
    }

    protected function cache(): Repository
    {
        return Cache::store(config('marquee.store.cache_store'));
    }

    protected function cacheKey(): string
    {
        return (string) config('marquee.store.cache_key', 'marquee.state');
    }
}
