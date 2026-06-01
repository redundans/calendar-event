import app from 'flarum/admin/app';

export { default as extend } from './extend';

app.initializers.add('redundans-calendar-event', () => {
  console.log('[redundans/calendar-event] Hello, admin!');
});
