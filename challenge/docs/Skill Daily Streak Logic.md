# Skill: Daily Streak Logic (Snapchat/Duolingo-style) for Mental Health Checklist (PHP + MySQL)

## Purpose
Implement robust “daily streak” logic for a mental health checklist app:
- Users complete a daily checklist (1+ items) to count as a “day completed.”
- A streak increments when a user completes on consecutive calendar days (in the user’s timezone).
- Missing a day breaks the streak (unless a streak-freeze is available).
- Provide clear, testable PHP functions and MySQL queries for streak state.

This skill is a reference for Cursor when generating or editing the code.

---

## Definitions
- **User Day**: A calendar date in the user’s timezone (not a rolling 24-hour window).
- **Completion Day**: A User Day where the user met the daily completion condition (e.g., checked ≥ N items or finished required set).
- **Streak**: Count of consecutive Completion Days ending on the most recent Completion Day.
- **Last Completed Date**: The most recent User Day that counted as completed.
- **Streak Freeze (optional)**: A consumable that can prevent a streak break for a missed day (Duolingo-like).

---

## Core Rules (Must Follow)
1. **Timezone-aware day boundaries**
   - Determine “today” using the user’s timezone (store timezone per user).
   - Convert server timestamps to user-local dates when evaluating streak.

2. **Idempotent completion**
   - Completing the checklist multiple times in the same User Day must not increment streak multiple times.

3. **Consecutive day logic**
   - If `completed_date == last_completed_date`: no change.
   - If `completed_date == last_completed_date + 1 day`: increment streak by 1.
   - If `completed_date > last_completed_date + 1 day`: streak resets to 1 (or preserved if freeze covers gaps; see optional).

4. **First completion**
   - If no streak exists: streak becomes 1 and last_completed_date is set.

5. **Do not rely on client time**
   - Use server time plus user timezone conversion.
   - Accept client-provided timestamp only if validated; preferred is server-side now.

---

## Database Schema (Recommended)

### `users`
- `id` (PK)
- `timezone` (VARCHAR(64), e.g. `America/Indiana/Indianapolis`)
- other fields…

### `daily_checklist_items`
Represents checklist definitions.
- `id` (PK)
- `name` (VARCHAR)
- `is_required` (TINYINT) default 1
- `active` (TINYINT) default 1

### `daily_checklist_entries`
Records user item checks per day (per item).
- `id` (PK)
- `user_id` (FK -> users.id)
- `item_id` (FK -> daily_checklist_items.id)
- `checked_at_utc` (DATETIME)  // UTC
- `user_date` (DATE)           // derived user-local date for fast grouping
- `value` (TINYINT) default 1  // checked true/false if you need toggles
Indexes:
- UNIQUE KEY `uniq_user_item_day` (`user_id`,`item_id`,`user_date`)
- INDEX (`user_id`,`user_date`)

### `user_daily_completion`
Canonical “day completed” table (preferred for streak correctness).
- `id` (PK)
- `user_id` (FK)
- `user_date` (DATE)                 // user-local date
- `completed_at_utc` (DATETIME)      // when the day was first completed
- `completion_score` (INT)           // optional: number of items completed
- `completion_rule_version` (INT)    // optional: for future rule changes
Indexes:
- UNIQUE KEY `uniq_user_day` (`user_id`,`user_date`)
- INDEX (`user_id`,`user_date`)

### `user_streaks`
Single row per user.
- `user_id` (PK, FK)
- `current_streak` (INT) default 0
- `longest_streak` (INT) default 0
- `last_completed_date` (DATE) NULL
- `streak_updated_at_utc` (DATETIME)
- `freeze_count` (INT) default 0            // optional
- `freeze_used_on_date` (DATE) NULL         // optional (last date freeze consumed)

---

## Completion Condition (Configurable)
Choose one and implement consistently:

### Option A (simple)
A day is complete if the user checks at least **1** item on that day.

### Option B (recommended)
A day is complete if the user checks **all required** items that are active.

### Option C (score threshold)
A day is complete if checks >= N.

Implementation should allow a constant/config:
- `DAILY_COMPLETION_MODE` = `min_one` | `all_required` | `threshold`
- `DAILY_THRESHOLD_N` (if needed)

---

## Data Flow (Recommended)
1. User checks/unchecks items → update `daily_checklist_entries` (UPSERT by user_id, item_id, user_date).
2. After an update, evaluate if the day meets completion condition:
   - If yes, UPSERT into `user_daily_completion` (idempotent).
3. If `user_daily_completion` was newly inserted (or first completion timestamp), update streak in `user_streaks`.

This avoids streak errors from toggling entries repeatedly.

---

## Timezone Handling (Must Implement)
- Store all event timestamps in UTC in DB (`*_utc`).
- Also store `user_date` (DATE) computed using user’s timezone for each entry/completion.
- Derive `user_date` in PHP using:
  - `$tz = new DateTimeZone($userTimezone)`
  - `$now = new DateTimeImmutable('now', new DateTimeZone('UTC'))`
  - `$local = $now->setTimezone($tz)`
  - `$userDate = $local->format('Y-m-d')`

Never compute user_date using server local timezone.

---

## Streak Update Algorithm (Canonical)

### Inputs
- `userId`
- `completedUserDate` (DATE string `Y-m-d`) = the day that became completed
- Current streak row: `current_streak`, `longest_streak`, `last_completed_date`, `freeze_count` (optional)

### Behavior
1. If `last_completed_date` is NULL:
   - `current_streak = 1`
2. Else:
   - `diffDays = days_between(last_completed_date, completedUserDate)`
   - If `diffDays == 0`: do nothing (already counted today)
   - If `diffDays == 1`: `current_streak += 1`
   - If `diffDays > 1`:
     - If freeze enabled and can cover the missed day(s), apply freeze logic (see below)
     - Else reset: `current_streak = 1`
3. Set `last_completed_date = completedUserDate`
4. `longest_streak = max(longest_streak, current_streak)`
5. Update `streak_updated_at_utc = NOW_UTC`

### MySQL day difference
Use `DATEDIFF(completedUserDate, last_completed_date)`.

---

## Optional: Streak Freeze Logic (Duolingo-like)
Goal: preserve streak if exactly **one missed day** and `freeze_count > 0`.

Rules (simple version):
- If `diffDays == 2` (missed exactly one day) AND `freeze_count > 0`:
  - consume 1 freeze
  - `current_streak += 1` (treat as consecutive)
  - set `freeze_used_on_date = completedUserDate` (or the missed date)
- If `diffDays > 2`: do not allow freeze (or require multiple freezes if you want complex behavior)

Important:
- Prevent using more than one freeze per completion event.
- Prevent reusing freeze for the same missed day.

---

## Concurrency & Consistency (Must Do)
Use transactions and row-level locking to avoid double increments:
- Start transaction
- Ensure `user_daily_completion` UPSERT returns whether it was newly created
- If newly created:
  - `SELECT ... FROM user_streaks WHERE user_id = ? FOR UPDATE`
  - apply streak update
  - `UPDATE user_streaks ...`
- Commit

MySQL InnoDB required.

---

## Queries (Patterns)

### Check if day meets completion condition (Option B: all required)
- Count required active items
- Count user checked required active items for `user_date`

Then completed if `checked_count >= required_count`.

### Insert/Upsert completion day
`INSERT INTO user_daily_completion (user_id, user_date, completed_at_utc, completion_score)
 VALUES (?, ?, UTC_TIMESTAMP(), ?)
 ON DUPLICATE KEY UPDATE
 completion_score = GREATEST(completion_score, VALUES(completion_score));`

Only update streak if the row was newly inserted. In PHP, detect with `ROW_COUNT()` behavior or select-before-insert in transaction.

---

## PHP Function Contracts (What Cursor Should Generate)

### `getUserTimezone(int $userId): string`
- Read from `users.timezone`
- Default to `UTC` if missing

### `computeUserDate(string $timezone, ?DateTimeImmutable $utcNow = null): string`
- Returns `Y-m-d` in user timezone

### `upsertChecklistEntry(int $userId, int $itemId, string $userDate, bool $checked): void`
- Upsert into `daily_checklist_entries` with `checked_at_utc = UTC_TIMESTAMP()`
- If unchecked, either delete row or store `value=0` consistently

### `evaluateAndCompleteDay(int $userId, string $userDate): bool`
- Returns true if `user_daily_completion` was newly created for this `userDate`
- Computes completion_score and completion criteria
- Upserts into `user_daily_completion`

### `updateStreakIfNeeded(int $userId, string $completedUserDate): void`
- Transaction + `FOR UPDATE` on `user_streaks`
- Applies canonical streak update algorithm
- Updates longest streak

### `getStreakStatus(int $userId): array`
Return:
- `current_streak`
- `longest_streak`
- `last_completed_date`
- `is_today_completed` (based on `user_daily_completion` for today)
- `days_until_break` (optional UI helper)
- `freeze_count` (optional)

---

## Edge Cases to Handle
- User completes day late at night: should count for that local calendar date.
- User travels / changes timezone:
  - Option 1 (simple): apply new timezone going forward; historical `user_date` remains as stored.
  - Avoid recomputing old user_date entries.
- DST transitions:
  - Using local DATE avoids DST complexity.
- Backfilling / late completion:
  - If user completes a past date (should you allow?):
    - Recommended: disallow completing dates other than “today” to keep streak fair.
- Double submit / multiple tabs:
  - Must not double-increment; require transaction + unique constraints.

---

## API Endpoints (Suggested)
- `POST /api/checklist/toggle`
  - body: `item_id`, `checked` (bool)
  - server computes `user_date` and updates entries
  - server evaluates completion; if newly completed, updates streak
  - returns `streak_status`

- `GET /api/streak/status`
  - returns `streak_status`

---

## Testing Scenarios (Must Pass)
1. First completion ever → streak = 1
2. Complete same day twice → streak unchanged
3. Complete consecutive days → increments
4. Skip one day → resets to 1 (or preserved if freeze enabled and diffDays==2)
5. Multiple requests at same time completing day → streak increments only once
6. User timezone boundary: completion at 00:30 local time counts for new day
7. Day meets completion rule only after last required item checked → only then completion is inserted

---

## Implementation Notes for Cursor
- Prefer `DateTimeImmutable` and explicit timezones.
- Store UTC timestamps in DB; store derived local DATE for grouping.
- Use prepared statements (PDO) and transactions.
- Enforce unique constraints to guarantee idempotency.
- Keep streak logic in one service/module (e.g., `streak_service.php`) to prevent duplication.

---

## Deliverable Expectations
When asked to implement this feature, generate:
- MySQL migration(s) for the tables above (or compatible alterations)
- PHP service functions implementing the contracts
- Example endpoint handlers calling the service
- Minimal config constants for completion mode
- Clear comments where business rules are applied
