# Kinto Android app

This directory contains the PHP web application and a Capacitor Android shell.
The Android app loads the production site at:

`https://unmaskedculture.org/challenge/`

The PHP application, database, email jobs, and APIs continue to run on the web
server. An internet connection is required for the Android app.

## Open in Android Studio

1. Install dependencies with `npm install`.
2. Sync native files with `npm run sync:android`.
3. Open Android Studio with `npm run open:android`, or open the `android` folder.
4. Select an emulator or connected Android device and click **Run**.

## Command-line debug build

Android Studio includes the required JDK. In PowerShell:

```powershell
$env:JAVA_HOME = 'C:\Program Files\Android\Android Studio\jbr'
npm run build:android
```

The debug APK is written to
`android/app/build/outputs/apk/debug/app-debug.apk`.

## Updating the native project

After changing `capacitor.config.json`, installed Capacitor plugins, or files in
`www`, run `npm run sync:android` before rebuilding.

Because the app displays the production PHP site, normal server-side web changes
do not require a new APK. Native configuration or plugin changes do.
