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

## Play Console update: 1.0.1 (version code 2)

The Android activity explicitly enables edge-to-edge on supported Android
versions. Its cream background extends behind the system bars; the WebView
receives native padding for status/navigation bars, display cutouts, and the
keyboard. This protects all hosted pages, including login and password recovery.
SystemBars inset handling is disabled because the activity owns that padding;
enabling both would apply insets twice.

Run `npm run sync:android` before building. In Android Studio, use **Build >
Generate Signed Bundle / APK > Android App Bundle** and the existing upload key.
Upload the signed version-code-2 bundle to replace the version-code-1 artifact.
An unsigned `bundleRelease` output is not ready for Play upload.

Before release, check login, password recovery, bottom navigation, and text entry
on Android 14 and Android 15/16 with gesture and three-button navigation, and a
device with a display cutout. Confirm focused fields remain above the keyboard
and no extra gap remains after the keyboard closes.

The portrait-only activity restriction has been removed and the activity is
explicitly resizable. Rotation and window resizing are handled by Capacitor's
configuration-change handling, preserving the current page and form input.
Check phone landscape, tablet portrait/landscape, and split-screen layouts.

Release builds enable R8 code optimization and resource shrinking. Capacitor's
consumer rules retain dynamically loaded plugins; the app also retains WebView
JavaScript interface methods and Cordova's dynamically loaded configuration XML.
Keep the generated `app/build/outputs/mapping/release/`
files with each release for crash diagnosis. Check native bridge functionality
(including haptics) in the signed release, not only in the debug APK.

The three Play Console recommendations addressed by this version are
edge-to-edge compatibility, unrestricted orientation/resizing, and R8
optimization. Play Console must analyze the new uploaded bundle before its
recommendations can be confirmed as resolved.
