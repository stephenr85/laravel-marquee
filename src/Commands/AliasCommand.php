<?php

namespace Rushing\Marquee\Commands;

use Illuminate\Console\Command;
use Rushing\Marquee\Mode;

/**
 * Thin alias delegating to `marquee:mode <mode>`. Subclasses set $mode + the
 * command name; flags are forwarded so `marquee:soon --secret=…` works too.
 */
abstract class AliasCommand extends Command
{
    protected Mode $mode;

    public function handle(): int
    {
        $arguments = ['mode' => $this->mode->value];

        foreach (['secret', 'retry', 'redirect'] as $option) {
            if ($this->hasOption($option) && $this->option($option) !== null) {
                $arguments['--'.$option] = $this->option($option);
            }
        }

        return $this->call(MarqueeModeCommand::class, $arguments);
    }
}
