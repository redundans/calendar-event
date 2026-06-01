import Extend from 'flarum/common/extenders';
import CalendarEventSettingsPage from './components/CalendarEventSettingsPage';

export default [
  new Extend.Admin() //
    .page(CalendarEventSettingsPage),
];
