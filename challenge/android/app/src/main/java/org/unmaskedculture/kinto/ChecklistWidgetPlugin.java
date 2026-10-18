package org.unmaskedculture.kinto;

import android.appwidget.AppWidgetManager;
import android.content.ComponentName;
import android.os.Build;
import com.getcapacitor.*;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "ChecklistWidget")
public class ChecklistWidgetPlugin extends Plugin {
    @PluginMethod public void refresh(PluginCall call) {
        // Immediately clear another account's UI before fetching this account's tasks.
        ChecklistWidget.render(getContext());
        ChecklistWidget.refresh(getContext());
        call.resolve();
    }
    @PluginMethod public void pin(PluginCall call) {
        getActivity().runOnUiThread(() -> {
            AppWidgetManager manager = AppWidgetManager.getInstance(getContext());
            boolean supported = Build.VERSION.SDK_INT >= 26 && manager.isRequestPinAppWidgetSupported();
            boolean requested = supported && manager.requestPinAppWidget(new ComponentName(getContext(), ChecklistWidget.class), null, null);
            JSObject result = new JSObject();
            result.put("requested", requested);
            call.resolve(result);
        });
    }
}
