<?php

namespace Rushing\Marquee\Commands;

use Rushing\Marquee\Mode;

class LiveCommand extends AliasCommand
{
    protected $signature = 'marquee:live {--redirect=}';

    protected $description = 'Flip the site live (alias for `marquee:mode live`).';

    protected Mode $mode = Mode::Live;
}
