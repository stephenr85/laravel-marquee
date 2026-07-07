<?php

namespace Rushing\Marquee\Commands;

use Rushing\Marquee\Mode;

class MaintenanceCommand extends AliasCommand
{
    protected $signature = 'marquee:maintenance {--secret=} {--retry=} {--redirect=}';

    protected $description = 'Post a responsive maintenance notice (alias for `marquee:mode maintenance`).';

    protected Mode $mode = Mode::Maintenance;
}
