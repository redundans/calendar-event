<?php

/*
 * This file is part of redundans/calendar-event.
 *
 * Copyright (c) 2026 redundans.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use redundans\Calendarevent\Console\FetchEventsCommand;
use redundans\Calendarevent\Api\CalendarEventDateSort;
use redundans\Calendarevent\Search\CalendarEventDateSortMutator;
use Flarum\Extend;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Search\Database\DatabaseSearchDriver;
use Flarum\Discussion\Discussion;
use Flarum\Api\Resource\DiscussionResource;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;
use Illuminate\Console\Scheduling\Event;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(function (\Flarum\Frontend\Document $document) {
            $settings = app(SettingsRepositoryInterface::class);
            $filteredTags = [];
            foreach ($settings->all() as $key => $value) {
                if (str_starts_with($key, 'calendar-event.tags.')) {
                    $filteredTags[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
            }
            $document->payload['calendarEventTags'] = json_encode($filteredTags);
            $calendarTagId = $settings->get('calendar-event-date.tag_id', '');
            $document->payload['calendarEventTagSlug'] = $calendarTagId
                ? Tag::query()->whereKey($calendarTagId)->value('slug')
                : null;
        }),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),

    // Gör kolumnen tillgänglig på Discussion-modellen
    (new Extend\Model(Discussion::class))
        ->cast('calendar_event_date', 'date'),

    // Spara fältet när en ny diskussion startas
    (new Extend\Event())
        ->listen(\Flarum\Discussion\Event\Saving::class, function (\Flarum\Discussion\Event\Saving $event) {
            $discussion = $event->discussion;
            $data = $event->data;

            if (isset($data['attributes']['calendarEventDate'])) {
                $discussion->calendar_event_date = $data['attributes']['calendarEventDate'] ?: null;
            }
        }),

    // Flarum 2.0-metod för att lägga till fält i API-responsen via ApiResource
    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(fn () => [
            Schema\DateTime::make('calendarEventDate')
                ->get(fn (Discussion $discussion) => $discussion->calendar_event_date)
                ->writable(),
        ])
        // Rätt metod i Flarum 2.0 för att tillåta sortering via API:et
        ->sorts(fn () => [
            new CalendarEventDateSort('calendarEventDate'),
            (new CalendarEventDateSort('calendar_event_date'))
                ->ascendingAlias('calendar_event_date_asc')
                ->descendingAlias('calendar_event_date_desc'),
            new CalendarEventDateSort('calendar_event_date_asc'),
            new CalendarEventDateSort('calendar_event_date_desc'),
        ]),

    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addMutator(DiscussionSearcher::class, CalendarEventDateSortMutator::class),

    // Den korrekta metoden i Flarum för att hantera admin-inställningar
    (new Extend\Settings())
        ->default('calendar-event-date.tag_id', '') // Sätter ett standardvärde
        ->serializeToForum('calendar-event-date.tag_id', 'calendar-event-date.tag_id'), // Skickar inställningen till frontenden

    // Registrera kommandot


    (new Extend\Console())
        ->command(FetchEventsCommand::class)
        ->schedule('noden:fetch-events', function (Event $event) {
            $event->hourly();
        })

];
