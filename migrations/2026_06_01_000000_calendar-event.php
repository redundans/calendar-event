<?php

use Flarum\Database\Migration;

return Migration::addColumns('discussions', [
    'calendar_event_date' => ['date', 'nullable' => true]
]);
