package org.unmaskedculture.kinto;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertTrue;

import android.content.Intent;
import android.view.View;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.test.ext.junit.runners.AndroidJUnit4;
import androidx.test.platform.app.InstrumentationRegistry;
import org.junit.After;
import org.junit.Before;
import org.junit.Test;
import org.junit.runner.RunWith;

/** Exercises the actual activity listener, including keyboard dismissal. */
@RunWith(AndroidJUnit4.class)
public class WindowInsetsTest {
    private MainActivity activity;

    @Before
    public void launch() {
        var instrumentation = InstrumentationRegistry.getInstrumentation();
        Intent intent = new Intent(instrumentation.getTargetContext(), MainActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        activity = (MainActivity) instrumentation.startActivitySync(intent);
        instrumentation.waitForIdleSync();
    }

    @After
    public void close() {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(() -> activity.finish());
    }

    @Test
    public void systemBarsAndSideCutoutsKeepTheWebViewSafe() {
        checkInsets(Insets.of(12, 80, 24, 48), 0, 48);
    }

    @Test
    public void keyboardReplacesBottomBarPaddingAndDismissalRestoresIt() {
        checkInsets(Insets.of(0, 80, 0, 48), 600, 600);
        checkInsets(Insets.of(0, 80, 0, 48), 0, 48);
    }

    @Test
    public void smallerKeyboardInsetDoesNotHideNavigationControls() {
        checkInsets(Insets.of(0, 80, 0, 48), 24, 48);
    }

    private void checkInsets(Insets safeArea, int keyboardBottom, int expectedBottom) {
        InstrumentationRegistry.getInstrumentation().runOnMainSync(() -> {
            View content = (View) activity.getBridge().getWebView().getParent();
            WindowInsetsCompat insets = new WindowInsetsCompat.Builder()
                .setInsets(WindowInsetsCompat.Type.systemBars(), safeArea)
                .setInsets(WindowInsetsCompat.Type.ime(), Insets.of(0, 0, 0, keyboardBottom))
                .build();
            WindowInsetsCompat remaining = ViewCompat.dispatchApplyWindowInsets(content, insets);
            assertEquals(safeArea.left, content.getPaddingLeft());
            assertEquals(safeArea.top, content.getPaddingTop());
            assertEquals(safeArea.right, content.getPaddingRight());
            assertEquals(expectedBottom, content.getPaddingBottom());
            assertTrue("Web content must not receive the same insets twice", remaining.isConsumed());
        });
    }
}
