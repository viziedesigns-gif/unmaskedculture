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

## Current build: 1.2.0 (version code 4)

Adds a native home-screen daily checklist with progress and a final-hour countdown.
See [widget behavior, security, and release checks](docs/ANDROID_WIDGET.md).
Native widget functionality requires installing this updated Android binary.

## Previous release: 1.1.0 (version code 3)

This release refreshes the mobile interface and adds the optional notification
introduction plus native daily reminders. See [mobile design and behavior](docs/MOBILE_EXPERIENCE.md).
After downloading, the introduction appears on first use; permission is requested
only after Enable is tapped. On Android, scheduling starts after sign-in and
uses the device's timezone. No Firebase configuration is required for these
local reminders. Existing browser push continues to use the server.

The Android activity explicitly enables edge-to-edge on supported Android
versions. Its cream background extends behind the system bars; the WebView
receives native padding for status/navigation bars, display cutouts, and the
keyboard. This protects all hosted pages, including login and password recovery.
SystemBars inset handling is disabled because the activity owns that padding;
enabling both would apply insets twice.

Run `npm run sync:android` before building. In Android Studio, use **Build >
Generate Signed Bundle / APK > Android App Bundle** and the existing upload key.
Upload the signed version-code-3 bundle to replace the previously uploaded artifact.
An unsigned `bundleRelease` output is not ready for Play upload.

Before release, check login, password recovery, bottom navigation, and text entry
on Android 14 and Android 15/16 with gesture and three-button navigation, and a
device with a display cutout. Confirm focused fields remain above the keyboard
and no extra gap remains after the keyboard closes.
For notifications, test Enable, denial, Maybe later, changing the reminder time,
turning reminders off, tapping a notification, and signing out. Use a real
Android 13+ device for the OS permission prompt and closed-app delivery.

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
