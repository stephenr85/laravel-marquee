<?php

namespace Rushing\Marquee\Commands;

use Rushing\Marquee\Mode;

class PreviewCommand extends AliasCommand
{
    protected $signature = 'marquee:preview {--secret=} {--redirect=}';

    protected $description = 'Open an invite-only preview (alias for `marquee:mode preview`).';

    protected Mode $mode = Mode::Preview;
}
