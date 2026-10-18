package org.unmaskedculture.kinto;

import android.content.Intent;
import android.widget.RemoteViews;
import android.widget.RemoteViewsService;
import org.json.*;

/** Collection adapter for Android 7–11; Android 12+ receives rows directly. */
public class ChecklistWidgetService extends RemoteViewsService {
    @Override public RemoteViewsFactory onGetViewFactory(Intent intent) {
        return new RemoteViewsFactory() {
            JSONObject data;
            JSONArray items = new JSONArray();
            boolean actionable;
            public void onCreate() { onDataSetChanged(); }
            public void onDataSetChanged() {
                data = ChecklistWidget.snapshot(ChecklistWidgetService.this);
                boolean valid = data != null && data.optLong("deadline_ms") > System.currentTimeMillis();
                items = valid ? data.optJSONArray("items") : new JSONArray();
                actionable = valid && ChecklistWidget.prefs(ChecklistWidgetService.this).getString("error", "").isEmpty();
            }
            public void onDestroy() { }
            public int getCount() { return items == null ? 0 : items.length(); }
            public RemoteViews getViewAt(int position) {
                JSONObject item = items.optJSONObject(position);
                return item == null ? null : ChecklistWidget.row(ChecklistWidgetService.this, data, item, actionable);
            }
            public RemoteViews getLoadingView() { return null; }
            public int getViewTypeCount() { return 1; }
            public long getItemId(int position) { return items.optJSONObject(position).optInt("id"); }
            public boolean hasStableIds() { return true; }
        };
    }
}
