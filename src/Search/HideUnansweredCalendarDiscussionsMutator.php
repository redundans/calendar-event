<?php

namespace redundans\Calendarevent\Search;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchCriteria;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Tags\Tag;

class HideUnansweredCalendarDiscussionsMutator
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    public function __invoke(DatabaseSearchState $search, SearchCriteria $criteria): void
    {
        $tagId = $this->resolveCalendarTagId();

        if (! $tagId) {
            return;
        }

        // Only the plain front page, sorted by "senast" (lastPostedAt desc),
        // is affected. Any other filter (a tag page, a search, ...) means the
        // reader has already narrowed the list down themselves, so calendar
        // discussions should show there regardless of whether they've been
        // commented on.
        if (! empty($criteria->filters)) {
            return;
        }

        if (($criteria->sort['lastPostedAt'] ?? null) !== 'desc' || count($criteria->sort) !== 1) {
            return;
        }

        $search->getQuery()->where(function ($query) use ($tagId) {
            $query
                ->whereDoesntHave('tags', function ($tags) use ($tagId) {
                    $tags->where('tags.id', $tagId);
                })
                ->orWhere('comment_count', '>', 1);
        });
    }

    /**
     * There is no admin UI to set `calendar-event-date.tag_id`, so it is
     * effectively never populated. Fall back to the "kalender" tag by slug,
     * the same one the forum frontend assumes when the setting is absent.
     */
    private function resolveCalendarTagId(): ?int
    {
        $tagId = $this->settings->get('calendar-event-date.tag_id');

        if ($tagId) {
            return (int) $tagId;
        }

        return Tag::query()->where('slug', 'kalender')->value('id');
    }
}
