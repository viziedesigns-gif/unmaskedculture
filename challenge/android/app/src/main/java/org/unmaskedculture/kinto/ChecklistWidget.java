package org.unmaskedculture.kinto;

import android.app.*;
import android.app.job.*;
import android.appwidget.*;
import android.content.*;
import android.net.Uri;
import android.os.*;
import android.view.View;
import android.webkit.CookieManager;
import android.widget.RemoteViews;
import org.json.*;
import java.net.*;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;

/** Native home-screen UI; all mutations use the existing authenticated server rules. */
public class ChecklistWidget extends AppWidgetProvider {
    static final String BASE = "https://unmaskedculture.org/challenge/";
    static final String REFRESH = "org.unmaskedculture.kinto.WIDGET_REFRESH";
    static final String TAP = "org.unmaskedculture.kinto.WIDGET_TAP";
    static final ExecutorService IO = Executors.newSingleThreadExecutor();
    private static final java.util.concurrent.atomic.AtomicBoolean SYNCING = new java.util.concurrent.atomic.AtomicBoolean();
    private static final int JOB = 701;

    static android.content.SharedPreferences prefs(Context c) {
        return c.getSharedPreferences("checklist_widget", Context.MODE_PRIVATE);
    }
    static String cookies() {
        String value = CookieManager.getInstance().getCookie(BASE);
        return value == null ? "" : value;
    }
    static String fingerprint(String cookie) {
        try {
            byte[] digest = MessageDigest.getInstance("SHA-256").digest(cookie.getBytes(StandardCharsets.UTF_8));
            return android.util.Base64.encodeToString(digest, android.util.Base64.NO_WRAP);
        } catch (Exception e) { throw new IllegalStateException(e); }
    }
    static JSONObject snapshot(Context c) {
        try {
            if (!fingerprint(cookies()).equals(prefs(c).getString("account", ""))) return null;
            return new JSONObject(prefs(c).getString("snapshot", ""));
        } catch (Exception e) { return null; }
    }
    static int[] ids(Context c) {
        return AppWidgetManager.getInstance(c).getAppWidgetIds(new ComponentName(c, ChecklistWidget.class));
    }
    static void refresh(Context c) {
        Context app = c.getApplicationContext();
        if (ids(app).length == 0) return;
        IO.execute(() -> sync(app, null));
    }

    @Override public void onUpdate(Context c, AppWidgetManager manager, int[] ids) {
        render(c);
        schedule(c);
        refresh(c);
    }
    @Override public void onEnabled(Context c) { schedule(c); }
    @Override public void onDisabled(Context c) {
        c.getSystemService(JobScheduler.class).cancel(JOB);
        c.getSystemService(AlarmManager.class).cancel(alarmIntent(c));
        prefs(c).edit().clear().apply();
    }
    @Override public void onReceive(Context c, Intent intent) {
        super.onReceive(c, intent);
        String action = intent.getAction();
        if (Intent.ACTION_BOOT_COMPLETED.equals(action) || Intent.ACTION_TIME_CHANGED.equals(action)
            || Intent.ACTION_TIMEZONE_CHANGED.equals(action)) {
            if (ids(c).length > 0) { schedule(c); render(c); refresh(c); }
        } else if (REFRESH.equals(action) || TAP.equals(action)) {
            if (ids(c).length == 0) return;
            JSONObject data = snapshot(c);
            if (TAP.equals(action) && data != null) {
                // Validate a fill-in against our own cached item and identity, not caller-supplied state.
                if (!data.optString("user_date").equals(intent.getStringExtra("date"))
                    || !prefs(c).getString("account", "").equals(intent.getStringExtra("account"))) return;
                JSONArray items = data.optJSONArray("items");
                for (int i = 0; items != null && i < items.length(); i++) {
                    JSONObject item = items.optJSONObject(i);
                    if (item.optInt("id") != intent.getIntExtra("item", -1)) continue;
                    if (!item.optBoolean("toggleable")) {
                        Intent open = new Intent(c, MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
                        open.putExtra("widget_date", data.optString("user_date"));
                        open.putExtra("widget_type", item.optString("type"));
                        // This launch is explicitly initiated by a tap in the home-screen widget.
                        Bundle creator = null;
                        Bundle sender = null;
                        if (Build.VERSION.SDK_INT >= 35) creator = ActivityOptions.makeBasic()
                            .setPendingIntentCreatorBackgroundActivityStartMode(ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED).toBundle();
                        if (Build.VERSION.SDK_INT >= 34) sender = ActivityOptions.makeBasic()
                            .setPendingIntentBackgroundActivityStartMode(ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED).toBundle();
                        try {
                            PendingIntent.getActivity(c, 704, open, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE, creator)
                                .send(c, 0, null, null, null, null, sender);
                        } catch (PendingIntent.CanceledException ignored) { }
                        return;
                    }
                    break;
                }
            }
            // Render the deadline transition even when there is no network.
            render(c);
            PendingResult pending = goAsync();
            Context app = c.getApplicationContext();
            // Network timeouts are bounded; no work waits behind other refreshes in this receiver.
            new Thread(() -> {
                try { sync(app, TAP.equals(action) ? intent : null); }
                finally { pending.finish(); }
            }, "kinto-widget-tap").start();
        }
    }

    static void sync(Context c, Intent tap) {
        if (!SYNCING.compareAndSet(false, true)) {
            if (tap != null) new Handler(Looper.getMainLooper()).post(() ->
                android.widget.Toast.makeText(c, "Syncing your checklist. Please tap the task again.", android.widget.Toast.LENGTH_SHORT).show());
            return;
        }
        try { syncOnce(c, tap); }
        finally { SYNCING.set(false); }
    }

    private static void syncOnce(Context c, Intent tap) {
        String cookie = cookies();
        String identity = fingerprint(cookie);
        JSONObject cached = snapshot(c);
        try {
            if (cookie.isEmpty()) throw new SecurityException();
            JSONObject body = null;
            if (tap != null) {
                if (cached == null || !identity.equals(tap.getStringExtra("account"))
                    || !cached.optString("user_date").equals(tap.getStringExtra("date"))) return;
                body = new JSONObject().put("csrf", cached.getString("csrf"))
                    .put("item_id", tap.getIntExtra("item", -1))
                    .put("checked", tap.getBooleanExtra("checked", true))
                    .put("user_date", tap.getStringExtra("date"));
            }
            HttpURLConnection connection = (HttpURLConnection) new URL(BASE + "api/widget.php").openConnection();
            try {
                connection.setInstanceFollowRedirects(false);
                connection.setConnectTimeout(1800);
                connection.setReadTimeout(1800);
                connection.setRequestProperty("Cookie", cookie);
                connection.setRequestProperty("Accept", "application/json");
                if (body != null) {
                    connection.setRequestMethod("POST");
                    connection.setDoOutput(true);
                    connection.setRequestProperty("Content-Type", "application/json");
                    try (OutputStream out = connection.getOutputStream()) { out.write(body.toString().getBytes(StandardCharsets.UTF_8)); }
                }
                int code = connection.getResponseCode();
                if (code == 401 || code == 302 || code == 303) throw new SecurityException();
                if (code != 200) throw new IOException("HTTP " + code);
                StringBuilder result = new StringBuilder();
                try (BufferedReader reader = new BufferedReader(new InputStreamReader(connection.getInputStream(), StandardCharsets.UTF_8))) {
                    String line;
                    while ((line = reader.readLine()) != null) {
                        result.append(line);
                        if (result.length() > 200000) throw new IOException("Response too large");
                    }
                }
                JSONObject next = new JSONObject(result.toString());
                if (!next.optBoolean("success") || !next.has("items")) throw new IOException("Invalid response");
                // A logout or account switch while the request was running must discard its response.
                if (!identity.equals(fingerprint(cookies()))) return;
                java.util.List<String> setCookies = connection.getHeaderFields().get("Set-Cookie");
                if (setCookies != null) for (String value : setCookies) CookieManager.getInstance().setCookie(BASE, value);
                CookieManager.getInstance().flush();
                prefs(c).edit().putString("snapshot", next.toString()).putString("account", fingerprint(cookies()))
                    .putString("error", "").apply();
            } finally { connection.disconnect(); }
        } catch (SecurityException e) {
            if (identity.equals(fingerprint(cookies()))) prefs(c).edit().clear().putString("error", "Open Kinto to sign in").apply();
        } catch (Exception e) {
            if (identity.equals(fingerprint(cookies()))) prefs(c).edit().putString("error", "Couldn’t sync · Tap refresh to retry").apply();
        }
        render(c);
    }

    static PendingIntent openIntent(Context c) {
        return PendingIntent.getActivity(c, 0, new Intent(c, MainActivity.class), PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }
    static PendingIntent alarmIntent(Context c) {
        return PendingIntent.getBroadcast(c, 702, new Intent(c, ChecklistWidget.class).setAction(REFRESH), PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }
    static void schedule(Context c) {
        JobInfo job = new JobInfo.Builder(JOB, new ComponentName(c, ChecklistWidgetJob.class))
            .setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY).setPeriodic(15 * 60 * 1000L).setPersisted(true).build();
        c.getSystemService(JobScheduler.class).schedule(job);
    }
    static void render(Context c) {
        JSONObject data = snapshot(c);
        long now = System.currentTimeMillis();
        long deadline = data == null ? 0 : data.optLong("deadline_ms");
        boolean expired = deadline <= now;
        boolean valid = data != null && !expired;
        String error = prefs(c).getString("error", "");
        boolean actionable = valid && error.isEmpty();
        for (int id : ids(c)) {
            RemoteViews views = new RemoteViews(c.getPackageName(), R.layout.checklist_widget);
            int done = valid ? data.optInt("completed") : 0;
            int total = valid ? data.optInt("total") : 0;
            views.setTextViewText(R.id.widget_title, "Your daily rhythm");
            views.setTextViewText(R.id.widget_progress_text, valid ? done + " of " + total + " complete" : "A little time for you");
            views.setProgressBar(R.id.widget_progress, Math.max(1, total), done, false);
            boolean urgent = valid && done < total && WidgetDeadline.isUrgent(deadline, now);
            views.setViewVisibility(R.id.widget_countdown_area, urgent ? View.VISIBLE : View.GONE);
            views.setChronometerCountDown(R.id.widget_countdown, true);
            views.setChronometer(R.id.widget_countdown, SystemClock.elapsedRealtime() + Math.max(0, deadline - now), null, urgent);
            views.setTextViewText(R.id.widget_footer, !error.isEmpty() ? error : valid
                ? (done == total ? "All done. Make room for rest." : "Small steps. A little more you.")
                : data == null ? "Open Kinto to sign in, then refresh" : "New day · Tap refresh for your tasks");
            views.setTextViewText(R.id.widget_empty, valid ? "No tasks today" : "Your next small step starts in Kinto.");
            views.setOnClickPendingIntent(R.id.widget_header, openIntent(c));
            views.setOnClickPendingIntent(R.id.widget_empty, openIntent(c));
            views.setOnClickPendingIntent(R.id.widget_refresh, PendingIntent.getBroadcast(c, 703,
                new Intent(c, ChecklistWidget.class).setAction(REFRESH), PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE));
            Intent template = new Intent(c, ChecklistWidget.class).setAction(TAP).setData(Uri.parse("kinto-widget://" + id));
            views.setPendingIntentTemplate(R.id.widget_list, PendingIntent.getBroadcast(c, id, template,
                PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 31 ? PendingIntent.FLAG_MUTABLE : 0)));
            if (Build.VERSION.SDK_INT >= 31) {
                RemoteViews.RemoteCollectionItems.Builder rows = new RemoteViews.RemoteCollectionItems.Builder().setHasStableIds(true).setViewTypeCount(1);
                JSONArray items = valid ? data.optJSONArray("items") : null;
                for (int i = 0; items != null && i < items.length(); i++) {
                    JSONObject item = items.optJSONObject(i);
                    rows.addItem(item.optInt("id"), row(c, data, item, actionable));
                }
                views.setRemoteAdapter(R.id.widget_list, rows.build());
            } else {
                Intent service = new Intent(c, ChecklistWidgetService.class).putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, id);
                service.setData(Uri.parse("kinto-widget://list/" + id));
                views.setRemoteAdapter(R.id.widget_list, service);
            }
            views.setEmptyView(R.id.widget_list, R.id.widget_empty);
            AppWidgetManager.getInstance(c).updateAppWidget(id, views);
            if (Build.VERSION.SDK_INT < 31) AppWidgetManager.getInstance(c).notifyAppWidgetViewDataChanged(id, R.id.widget_list);
        }
        AlarmManager alarm = c.getSystemService(AlarmManager.class);
        alarm.cancel(alarmIntent(c));
        if (valid && ids(c).length > 0) {
            long next = deadline - now >= 3600000 ? deadline - 3600000 + 1 : deadline;
            // Inexact alarms avoid special alarm permissions. The launcher ticks the timer itself.
            alarm.setAndAllowWhileIdle(AlarmManager.RTC, next, alarmIntent(c));
        }
    }

    static RemoteViews row(Context c, JSONObject data, JSONObject item, boolean actionable) {
        RemoteViews row = new RemoteViews(c.getPackageName(), R.layout.checklist_widget_row);
        boolean checked = item.optBoolean("completed");
        row.setTextViewText(R.id.widget_item_name, item.optString("name"));
        row.setTextColor(R.id.widget_item_name, android.graphics.Color.parseColor(checked ? "#667569" : "#263C34"));
        row.setImageViewResource(R.id.widget_item_check, checked ? R.drawable.widget_check_done : R.drawable.widget_check_empty);
        row.setTextViewText(R.id.widget_item_hint, item.optBoolean("toggleable") ? "" : "Log in app  ›");
        row.setViewVisibility(R.id.widget_item_hint, item.optBoolean("toggleable") ? View.GONE : View.VISIBLE);
        row.setContentDescription(R.id.widget_row, item.optString("name") + (checked ? ", complete" : ", incomplete")
            + (item.optBoolean("toggleable") ? ", tap to change" : ", open activity log"));
        if (actionable) row.setOnClickFillInIntent(R.id.widget_row, new Intent()
            .putExtra("item", item.optInt("id")).putExtra("checked", !checked)
            .putExtra("date", data.optString("user_date")).putExtra("account", prefs(c).getString("account", "")));
        return row;
    }
}
