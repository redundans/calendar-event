import app from 'flarum/forum/app';
import { override, extend } from 'flarum/common/extend';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import ItemList from 'flarum/common/utils/ItemList';
import Mithril from 'mithril';
import GlobalSearchState from 'flarum/forum/states/GlobalSearchState';

let selectedCalendarEventDate: string = '';

const CALENDAR_SORT_VALUES = ['calendar_event_date_asc', 'calendar_event_date_desc'];

function isCalendarSlug(slug?: string): boolean {
  const calendarTagSlug = String(app.data.calendarEventTagSlug || '').toLocaleLowerCase();
  const currentSlug = String(slug || '').toLocaleLowerCase();

  return currentSlug === 'kalender' || Boolean(calendarTagSlug && calendarTagSlug === currentSlug);
}

function isCalendarTag(): boolean {
  return isCalendarSlug(m.route.param('tags'));
}

app.initializers.add('redundans-calendar-event', () => {
  override(Discussion.prototype, 'title', function (this: Discussion, original: Function) {
    const originalTitle = original();
    const eventDateRaw = this.attribute<string>('calendarEventDate');

    if (eventDateRaw) {
      try {
        const formattedDate = eventDateRaw.substring(0, 10);
        return `${formattedDate} - ${originalTitle}`;
      } catch (e) {
      }
    }
    return originalTitle;
  });

  override('flarum/forum/components/DiscussionComposer', 'data', function (this: any, original: Function) {
    const data = original();
    if (selectedCalendarEventDate) {
      data.calendarEventDate = selectedCalendarEventDate;
    }
    return data;
  });

  override('flarum/forum/components/DiscussionComposer', 'headerItems', function (this: any, original: Function) {
    const items: ItemList<Mithril.Children> = original();
    const rawSettings = app.data['calendarEventTags'] || '{}';

    let allowedTags: Record<string, boolean> = {};

    try {
      allowedTags = JSON.parse(rawSettings);
    } catch (e) {
    }

    const composerState = this.composer || this.body.attrs?.composer;
    const selectedTags: any[] = composerState?.fields?.tags || [];
    const hasActiveTag = selectedTags.some((tag: any) => {
      const slug = typeof tag.slug === 'function' ? tag.slug() : tag.slug;
      return allowedTags[`calendar-event.tags.${slug}`] === true;
    });

    if(hasActiveTag) {
      items.add('calendar-event-date-field', (
        <div className="Composer-calendarEventDate">
          <label>Datum: </label>
          <input
            type="date"
            className="FormControl"
            value={selectedCalendarEventDate}
            oninput={(e: Event) => {
              const target = e.target as HTMLInputElement;
              selectedCalendarEventDate = target.value;
            }}
          />
        </div>
      ), 90);
    }
    return items;
  });

  extend(DiscussionListState.prototype, 'sortMap', function (this: any, map: any) {
    if (!isCalendarTag()) {
      return;
    }

    map.calendar_event_date_asc = 'calendar_event_date';
    map.calendar_event_date_desc = '-calendar_event_date';
  });

  // Sidebar tag links carry the current sort along by default (so it isn't
  // lost when only the tag filter changes). Our calendar-only sort values
  // don't make sense for other tags, so strip them from links that don't
  // point at the calendar tag. `initAttrs` is a static method, so it has to
  // be reached via `flarum.reg.onLoad` rather than the string-path form of
  // `extend`, which only patches an extension's prototype methods.
  flarum.reg.onLoad('flarum-tags', 'forum/components/TagLinkButton', (TagLinkButton: any) => {
    extend(TagLinkButton, 'initAttrs', function (_result: void, attrs: any) {
      const targetSlug = attrs.params?.tags;

      if (attrs.params && CALENDAR_SORT_VALUES.includes(attrs.params.sort) && !isCalendarSlug(targetSlug)) {
        delete attrs.params.sort;
        attrs.route = app.route('tag', attrs.params);
      }
    });
  });

  extend(GlobalSearchState.prototype, 'params', function (this: any, params: any) {
    if (!isCalendarTag() && ['calendar_event_date_asc', 'calendar_event_date_desc'].includes(params.sort)) {
      delete params.sort;
    }
  });

});
