<?php

declare(strict_types=1);

namespace Rushing\Marquee\Commands;

use Rushing\Marquee\Mode;

class SoonCommand extends AliasCommand
{
    protected $signature = 'marquee:soon {--secret=} {--redirect=}';

    protected $description = 'Show the launching-soon splash (alias for `marquee:mode soon`).';

    protected Mode $mode = Mode::Soon;
}
