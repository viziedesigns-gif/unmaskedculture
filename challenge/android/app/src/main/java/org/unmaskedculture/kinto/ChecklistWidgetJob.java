package org.unmaskedculture.kinto;

import android.app.job.*;

public class ChecklistWidgetJob extends JobService {
    @Override public boolean onStartJob(JobParameters params) {
        ChecklistWidget.IO.execute(() -> {
            ChecklistWidget.sync(getApplicationContext(), null);
            jobFinished(params, false);
        });
        return true;
    }
    @Override public boolean onStopJob(JobParameters params) { return true; }
}
