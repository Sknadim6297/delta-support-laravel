<?php

namespace App\Services;

use Illuminate\Pagination\CursorPaginator;

class ReportCursorPaginator extends CursorPaginator
{
    public function setHasMore(bool $hasMore): void
    {
        $this->hasMore = $hasMore;
    }
}
