<?php

namespace Cable8mm\Youtube\Events;

use Carbon\Carbon;

/**
 * A Sync hit the API quota and a Quota Block started.
 */
class QuotaExceeded
{
    public function __construct(
        public Carbon $until,
    ) {}
}
