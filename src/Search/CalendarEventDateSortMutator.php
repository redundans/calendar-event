<?php

namespace redundans\Calendarevent\Search;

use Carbon\Carbon;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchCriteria;

class CalendarEventDateSortMutator
{
    public function __invoke(DatabaseSearchState $search, SearchCriteria $criteria): void
    {
        $direction = $this->direction($criteria->sort);

        if ($direction === null) {
            return;
        }

        if ($direction === 'asc') {
            $search
                ->getQuery()
                ->whereNotNull('calendar_event_date')
                ->whereDate('calendar_event_date', '>=', Carbon::today());
        }

        $search->getQuery()->reorder('calendar_event_date', $direction);
    }

    private function direction(?array $sort): ?string
    {
        foreach ($sort ?? [] as $field => $direction) {
            if ($field === 'calendar_event_date_asc') {
                return 'asc';
            }

            if ($field === 'calendar_event_date_desc') {
                return 'desc';
            }

            if (in_array($field, ['calendarEventDate', 'calendar_event_date'], true)) {
                return $direction;
            }
        }

        return null;
    }
}
