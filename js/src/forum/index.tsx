import app from 'flarum/forum/app';
import { override, extend } from 'flarum/common/extend';
import Discussion from 'flarum/common/models/Discussion';
import ItemList from 'flarum/common/utils/ItemList';
import Mithril from 'mithril';

let selectedCalendarEventDate: string = '';

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

  extend('flarum/forum/states/DiscussionListState', 'sortMap', function (this: any, map: any) {
    map.calendar_event_date_asc = 'calendarEventDate';
    map.calendar_event_date_desc = '-calendarEventDate';
  });

});
