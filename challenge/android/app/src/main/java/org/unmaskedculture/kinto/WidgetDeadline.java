package org.unmaskedculture.kinto;

/** Pure boundary policy, shared by rendering and unit tests. */
final class WidgetDeadline {
    static boolean isUrgent(long deadline, long now) {
        long remaining = deadline - now;
        return remaining > 0 && remaining < 60 * 60 * 1000L;
    }
}
