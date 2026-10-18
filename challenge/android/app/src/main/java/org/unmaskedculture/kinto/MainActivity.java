package org.unmaskedculture.kinto;

import android.graphics.Color;
import android.os.Bundle;
import android.content.Intent;
import android.view.View;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        registerPlugin(ChecklistWidgetPlugin.class);
        super.onCreate(savedInstanceState);
        WindowCompat.enableEdgeToEdge(getWindow());

        if (getBridge() == null) return;

        // Paint behind the transparent system bars, keeping every hosted page
        // inside the safe area, including pages without viewport-fit=cover.
        View content = (View) getBridge().getWebView().getParent();
        content.setBackgroundColor(Color.rgb(245, 240, 232));
        ViewCompat.setOnApplyWindowInsetsListener(content, (view, windowInsets) -> {
            Insets safeArea = windowInsets.getInsets(
                WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout()
            );
            Insets keyboard = windowInsets.getInsets(WindowInsetsCompat.Type.ime());
            view.setPadding(
                safeArea.left,
                safeArea.top,
                safeArea.right,
                Math.max(safeArea.bottom, keyboard.bottom)
            );
            // The native container owns insets. Do not also apply the same
            // safe-area or keyboard padding inside the WebView.
            return WindowInsetsCompat.CONSUMED;
        });
        ViewCompat.requestApplyInsets(content);
        openWidgetLog(getIntent());
    }

    @Override public void onResume() {
        super.onResume();
        ChecklistWidget.render(this);
        ChecklistWidget.refresh(this);
    }

    @Override public void onPause() {
        super.onPause();
        ChecklistWidget.refresh(this);
    }

    @Override protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        openWidgetLog(intent);
    }

    private void openWidgetLog(Intent intent) {
        String date = intent.getStringExtra("widget_date");
        String type = intent.getStringExtra("widget_type");
        if (date == null || !date.matches("\\d{4}-\\d{2}-\\d{2}") || getBridge() == null) return;
        String section = "water_tracker".equals(type) ? "water" : "mood_tracker".equals(type) ? "mood" : "workout".equals(type) ? "workout" : "";
        getBridge().getWebView().loadUrl(ChecklistWidget.BASE + "app/dashboard.php?date=" + date + "&widget_log=" + section);
        intent.removeExtra("widget_date");
    }
}
