# Push Notifications

This app uses browser Web Push.

## Server setup

From the `challenge` directory on the server:

```bash
composer install --no-dev --optimize-autoloader
php vendor/bin/web-push generate:vapid
cp config/push.example.php config/push.php
```

Paste the generated VAPID values into `config/push.php`.

If the app cannot auto-create tables, run `schema.sql` or create the
`push_subscriptions` table from that file.

## Cron (required for scheduled reminders)

Scheduled reminders are sent by:

```bash
*/15 * * * * php /path/to/challenge/cron/send_push_notifications.php
```

The cron:

Each notification is deduplicated per user and checklist date. If a cron run
is delayed, the next run catches up after the configured reminder time and
before the checklist closes at 1:00 AM.

- Sends **daily check-in reminders** in a 15-minute window starting at each
  user’s `daily_reminder_time` (profile timezone).
- Sends **streak-at-risk** alerts at **9:00 PM** local time (same 15-minute window).
- Skips users with no device subscription, or whose day already counts as complete
  (Easy = 1+ items; Intermediate = all required items).
- Uses mode-aware copy for Easy vs Intermediate.

Also ensure streak expiry cron is scheduled (see `docs/streak-logic.md`):

```bash
*/15 * * * * php /path/to/challenge/cron/expire_streaks.php
```

## User flow

Users enable notifications in Settings. Each device/browser creates its own
subscription. The app stores the subscription in `push_subscriptions` and syncs
reminder preferences from the user profile.

The Settings page includes a test notification button. Feed messages send
push notifications to other circle members with active subscriptions.

## Notes

- HTTPS is required.
- iPhone users usually need to add the app to the Home Screen before Web Push
  is available.
- `config/push.php` contains private keys and should not be committed to a
  public repository.
