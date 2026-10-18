package org.unmaskedculture.kinto;

import org.junit.Test;
import static org.junit.Assert.*;

public class WidgetDeadlineTest {
    @Test public void countdownOnlyDuringFinalHour() {
        long now = 10000000;
        assertFalse(WidgetDeadline.isUrgent(now + 3600001, now));
        assertFalse(WidgetDeadline.isUrgent(now + 3600000, now));
        assertTrue(WidgetDeadline.isUrgent(now + 3599999, now));
        assertTrue(WidgetDeadline.isUrgent(now + 1, now));
        assertFalse(WidgetDeadline.isUrgent(now, now));
        assertFalse(WidgetDeadline.isUrgent(now - 1, now));
    }
}
