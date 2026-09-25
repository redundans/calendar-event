<?php

namespace redundans\Calendarevent\Api;

use Flarum\Api\Sort\SortColumn;
use Tobyz\JsonApiServer\Context;

class CalendarEventDateSort extends SortColumn
{
    public function apply(object $query, string $direction, Context $context): void
    {
        if ($this->name === 'calendar_event_date_asc' || $direction === 'asc') {
            $query
                ->whereNotNull('calendar_event_date')
                ->whereDate('calendar_event_date', '>=', \Carbon\Carbon::today());

            $direction = 'asc';
        } elseif ($this->name === 'calendar_event_date_desc') {
            $direction = 'desc';
        }

        $query->orderBy('calendar_event_date', $direction);
    }
}
