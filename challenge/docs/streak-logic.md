# Streak Logic (Duolingo / Snapchat-style)

Reference for the 77-Day Challenge streak implementation. This document is
the single source of truth for how a "day" gets completed, how the streak
advances, how it breaks, how repairs work, and how milestones are broadcast.

Primary implementation: [`challenge/includes/streak_service.php`](../includes/streak_service.php)
Explicit repair / restart endpoint: [`challenge/api/streak_action.php`](../api/streak_action.php)
Scheduled expiry: [`challenge/cron/expire_streaks.php`](../cron/expire_streaks.php)

---

## 1. Core concepts

| Term                 | Meaning                                                                                              |
| -------------------- | ---------------------------------------------------------------------------------------------------- |
| User Day             | A calendar date in the user's own timezone (`users.timezone`). Never the server's timezone.          |
| Completion Day       | A User Day where all 7 required checklist items were checked.                                        |
| Current Streak       | Count of consecutive Completion Days ending at `last_completed_date`.                                |
| Last Completed Date  | Most recent Completion Day (stored in `user_streaks.last_completed_date`).                           |
| Streak Repair        | Consumable token (`users.streak_repairs`, default 3) that can cover exactly one missed day.          |
| 77-Day Challenge Day | `min(77, current_streak)` - a purely derived value, NOT based on calendar days since signup.         |

---

## 2. Completion rule

Configured in `streak_service.php`:

```php
define('DAILY_COMPLETION_MODE', 'all_required');
define('REQUIRED_ITEMS_COUNT', 7);
```

A User Day is complete when the count of `daily_checklist_entries` rows
with `value = 1` for that `(user_id, user_date)` reaches
`REQUIRED_ITEMS_COUNT`. Until then, no completion row is written.

Idempotency is enforced by the unique index
`user_daily_completion.uniq_user_day (user_id, user_date)`.

---

## 3. Streak update algorithm (canonical)

Given a newly completed day `completedUserDate` and the existing
`user_streaks` row `(current_streak, last_completed_date, freeze_used_on_date)`:

1. If the streak row does not exist: create it at `current_streak = 1`.
2. Else if `last_completed_date IS NULL`: `current_streak = 1`.
3. Else compute `diffDays = completedUserDate - last_completed_date`:
   - `diffDays == 0`  -> no change (same day completed twice).
   - `diffDays == 1`  -> `current_streak += 1`.
   - `diffDays == 2`  -> covered only if `freeze_used_on_date` equals the
     single missed date (meaning an explicit repair was recorded via
     `/api/streak_action.php`). Otherwise reset to 1.
   - `diffDays > 2`   -> reset to 1.
4. `last_completed_date = completedUserDate`.
5. `longest_streak = max(longest_streak, current_streak)`.
6. `streak_updated_at_utc = UTC_TIMESTAMP()`.

All of the above runs inside a transaction with `SELECT ... FOR UPDATE` on
the streak row so concurrent toggles from multiple tabs cannot
double-increment.

### Why no silent auto-repair

Earlier revisions of `updateStreakIfNeeded` silently decremented
`users.streak_repairs` on any 1-day gap. This let streaks climb invisibly:
a user could miss Monday, do nothing about it, complete Tuesday, and be
charged a repair without ever seeing the "Streak at Risk" modal. The
current rule ties repair consumption to an explicit user action - the
modal in [`challenge/app/dashboard.php`](../app/dashboard.php) that POSTs
to [`streak_action.php`](../api/streak_action.php).

---

## 4. Streak repair (Duolingo-style freeze)

Explicit only. The modal on the dashboard shows up when the user returns
after missing exactly one day and still has `streak_repairs > 0`.

Repair flow:

1. User POSTs `action=repair` to `streak_action.php`.
2. Server checks `can_repair = true` from `getStreakStatus()`.
3. Server decrements `users.streak_repairs` by 1 (only if `> 0`).
4. Server sets `user_streaks.freeze_used_on_date = <missed_date>`.
5. On the next successful completion, step 3 of the algorithm above sees
   `diffDays == 2` with `freeze_used_on_date` matching the gap and
   increments instead of resetting.
6. Inner Circles receive a `system_milestone` message:
   "<first_name> used a streak repair! (N remaining)".

Invariants:

- One repair per missed day. We track the exact `freeze_used_on_date`.
- A repair is only valid while `diffDays == 2`. Longer gaps always reset.
- Repairs are earned by inviting friends to Inner Circles
  (`awardStreakRepair` in `auth.php`). Restarting the challenge resets
  the pool back to 3.

---

## 5. Restart

Surfaced in two places:

- The "Restart My Challenge" button in the broken-streak modal on the
  dashboard (no repairs available).
- The "Challenge Status" card in Settings (`profile.php`), visible
  whenever the current streak is zero, broken, or lost.

The endpoint `streak_action.php?action=restart` does:

1. `user_streaks`: `current_streak = 0`, `last_completed_date = NULL`,
   `freeze_used_on_date = NULL`.
2. `users`: `created_at = NOW()` (so the start date on the Settings card
   shows today), `streak_repairs = 3` (fresh pool).
3. Clears session caches (`clearStreakSessionCache`) so the UI shows
   Day 0 immediately.
4. Clears only the current local day's checklist/completion/water/mood rows
   so Day 1 can be earned cleanly after restart.

Historical `user_daily_completion` and `mood_entries` are intentionally
preserved so the user keeps their journal history.

---

## 5a. Scheduled expiry

The cron script `challenge/cron/expire_streaks.php` should run every minute.
It calls `expireStreakIfNeeded()` for active streaks. The helper is
idempotent:

- If the user missed exactly one local day and has repairs, the streak is
  left active and `getStreakStatus()` exposes `can_repair = true`.
- If the user has no repairs, or missed more than one day, `current_streak`
  is set to 0 while `last_completed_date` is preserved for restart context.
- Normal app/API requests also call the same helper, so expiry still works if
  cron is delayed.

---

## 6. Milestones

Defined in `streak_service.php`:

```php
const STREAK_MILESTONES = [
    1  => 'just completed their first day!',
    7  => 'hit a 7-day streak! One week strong.',
    14 => 'reached a 2-week streak!',
    30 => 'hit 30 days! A full month of showing up.',
    50 => 'hit 50 days! Two-thirds of the way there.',
    77 => 'completed the 77-Day Challenge!',
];
```

`postMilestoneCelebration($userId, $previousStreak, $newStreak)` fires
for every milestone day in the half-open range
`(previousStreak, newStreak]`. Because it's called only when
`newStreak > previousStreak` inside the committed streak transaction:

- A same-day re-completion (`diffDays == 0`) never posts.
- A reset (`newStreak = 1` from a broken streak) re-posts the day-1
  message only if `currentStreak` was 0 (first day of a new attempt).
- A repair that increments from 76 to 77 posts the 77-day completion
  message exactly once.

Messages are written to every `inner_circle_members` row for the user as
`system_milestone` entries in `circle_messages`.

---

## 7. Timezone handling

- All event timestamps go into the DB in UTC (`*_utc` columns).
- `user_date` is derived at write time using the user's timezone:

  ```php
  $tz = new DateTimeZone($userTimezone);
  $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  $userDate = $now->setTimezone($tz)->format('Y-m-d');
  ```

- Historical `user_date` values are never recomputed if the user later
  changes their timezone. Only future entries use the new zone.
- Using `DATE` columns (not `DATETIME`) for `user_date` sidesteps DST
  edge cases entirely.

---

## 8. Concurrency

Any streak mutation must:

1. Begin a transaction.
2. `SELECT * FROM user_streaks WHERE user_id = ? FOR UPDATE`.
3. Perform the writes.
4. `COMMIT`.

Milestone / celebration messages are posted **after** commit so a
circle-message failure can never roll back the streak update.

---

## 9. Testing checklist

| Scenario                                                         | Expected                                               |
| ---------------------------------------------------------------- | ------------------------------------------------------ |
| First ever day completed                                         | streak = 1, day-1 milestone posted to all circles      |
| Same day completed twice (toggle last item off/on)               | streak unchanged, no duplicate milestone               |
| Consecutive days                                                 | streak increments by 1 per day                         |
| Miss exactly one day, no repair                                  | next completion -> streak = 1                          |
| Miss exactly one day, explicit repair used                       | next completion -> streak = previous + 1               |
| Miss two or more days                                            | next completion -> streak = 1 (repair cannot cover)    |
| Two tabs racing on the last checkbox of the day                  | Exactly one completion row, one streak increment       |
| User signs up, does nothing for 77 days                          | profile / insights show Day 0 of 77, NOT "Completed"   |
| Streak reaches 77                                                | 77-day completion message posted exactly once          |
| Restart challenge                                                | streak = 0, repairs = 3, created_at reset, cache clear |

---

## 10. What NOT to do

- Do not compute challenge progress from `users.created_at` + elapsed
  days. That was the original bug. Progress is always `current_streak`.
- Do not decrement `streak_repairs` anywhere except the explicit
  `streak_action.php?action=repair` handler.
- Do not write `user_daily_completion` rows for past dates. Completion
  is always "today" in the user's timezone.
- Do not compute `user_date` with `date()` (server-local). Always use
  `computeUserDate()` with the user's stored timezone.
