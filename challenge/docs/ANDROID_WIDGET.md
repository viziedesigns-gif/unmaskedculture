# Kinto daily checklist widget — Android 1.2.0 (version code 4)

The native, resizable home-screen widget uses a cream card, sage checkmarks,
scrollable tasks, a progress bar, and an amber countdown during the final hour.
Add it from Android's widget picker or **You → Notifications → Add checklist widget**
in the updated app. The launcher asks the user to confirm placement.

## Behavior

- Ordinary tasks can be checked and unchecked without opening Kinto. The API
  uses the existing checklist, streak, completion, and Calm Points services.
- Water, mood, and workout rows open the appropriate activity modal in Kinto,
  preserving the required measurements and activity records.
- The server supplies the deadline from the profile timezone and challenge mode:
  Intermediate closes at midnight; Easy closes at 1:00 AM. During Easy's grace
  hour the widget shows yesterday's closing checklist until 1:00 AM.
- The countdown is visible only while tasks remain and `0 < remaining < 1 hour`.
  Android's Chronometer ticks locally; no per-second network calls are made.
- An inexact alarm requests refreshes at the final-hour threshold and deadline;
  periodic network jobs, launcher updates, manual refresh, and app activity also
  refresh it. Android power-saving rules can delay those refreshes. Exact-alarm
  access is deliberately unnecessary. A delayed launcher refresh can briefly
  show the old timer after its boundary; the server always rejects expired edits.
- Changes require a connection. Checks are confirmed by the server before the
  UI changes. Failed requests show a retry message, without queuing old-day edits.
- Native requests use the app's private WebView cookies and a session-bound CSRF
  token. Cached data is bound to a hash of the cookie identity; account changes
  hide it immediately on the next render. Responses from a previous identity are
  discarded. Background authentication failure clears the cache.
- Android 12+ uses direct RemoteCollectionItems. Android 7–11 uses the legacy
  RemoteViewsService adapter. Multiple widget instances share the signed-in account.

## Build and validation

Run PHP syntax checks, `php challenge/tests/widget_policy_test.php`, JavaScript
syntax validation, and Gradle `:app:assembleDebug :app:bundleRelease
:app:testDebugUnitTest :app:lintRelease`. The release bundle must be signed with
the existing upload key before Google Play upload. A Hostinger deployment supplies
the widget API and in-app controls; users also need the new Android binary.

Before Play rollout, verify on-device adding/resizing/scrolling, rapid taps,
water/mood/workout links, server completion and streak updates, denied/offline
requests, logout/account switching, app/process restart, reboot, and both deadline
boundaries in each mode. Include Android 7–11's legacy collection adapter and an
Android 16 launcher. Check the signed optimized build's Capacitor bridge.
