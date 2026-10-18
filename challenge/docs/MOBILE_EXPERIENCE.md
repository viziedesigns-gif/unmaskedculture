# Kinto mobile refresh — 1.1.0 / Android version code 3

## Research and design decisions

Reviewed official product descriptions and design guidance on October 18, 2026.
These are reference patterns; the Kinto interface and brand assets remain its own.

| Reference | Relevant pattern | Kinto implementation |
| --- | --- | --- |
| [Headspace](https://www.headspace.com/app) | A daily starting point and approachable content choices | A personal greeting, one primary daily action, and simple progress |
| [Gentler Streak's navigation redesign](https://docs.gentler.app/release-notes-and-announcements/a-more-intuitive-streak) | Put the most relevant information first and reduce navigation complexity | Streak, calm points, and repairs are readable at a glance; the existing five destinations stay familiar |
| [Calm's getting-started guide](https://support.calm.com/hc/en-us/articles/115002474567-Getting-Started-With-Calm-A-Beginner-s-Guide-to-Meditation-Sleep) | Make today's content easy to discover | “Continue your day” leads directly to the daily rhythm |
| [Finch's self-care approach](https://help.finchcare.com/hc/en-us/articles/37935669335309-Our-Approach-to-Self-Care) | Gentle encouragement and small achievable steps | Supportive daily copy; no new pressure or competition mechanics |
| [Nike Training Club](https://www.nike.com/ntc-app) | Clear actions around movement, recovery, and consistent habits | Compact activity controls and a stronger visual hierarchy for daily tasks |
| [Apple motion guidance](https://developer.apple.com/design/human-interface-guidelines/motion) | Motion should provide feedback and respect accessibility preferences | Brief cross-document transitions, quiet press feedback, no artificial splash delay, and reduced-motion support |
| [Android notification guidance](https://developer.android.com/design/ui/mobile/guides/home-screen/notifications) | Explain value and request permission at a meaningful moment | A first-install introduction with separate Enable and Maybe later actions |

The visual system retains cream, gold, the kintsugi vase, and a serif Kinto
wordmark. Self-hosted variable Manrope handles interface text and headings.
Muted green supports the existing warm palette. Cards have quieter borders,
consistent corner radii, and minimal shadows. The app fills the available screen
without the website footer or marketing navigation. Android's native safe-area
handling protects system bars and the keyboard.

## Navigation and accessibility

The PHP navigation, form submissions, history, and authentication are retained.
CSS cross-document View Transitions enhance supported browsers; ordinary
navigation remains the fallback. Pages are not replaced by client-side HTML
injection. Successful routine actions show a non-blocking status toast; errors
and confirmations retain the existing dialog flow.

Pinch zoom is enabled. Fields retain 16px text, primary controls have generous
touch targets, the current tab has `aria-current`, and the reminder explanation
traps keyboard focus and makes the underlying page inert. Reduced motion
disables decorative animation and page motion.

## Reminder behavior

On a new Android installation, the explanation appears on first use, including
the login screen. It requests system permission only after Enable is tapped.
If enabled before sign-in, scheduling waits until a signed-in page loads.
Installed web apps receive the introduction after sign-in or installation.
Maybe later is remembered on that device. Reminders remain optional and can be
enabled later in Settings. The introduction waits for existing dialogs and does
not interrupt onboarding forms.

Android uses the official [Capacitor Local Notifications plugin](https://capacitorjs.com/docs/apis/local-notifications)
to schedule a generic daily check-in on the device. It works without the app
being open and follows the device's clock. Schedule changes are applied when
the updated settings page loads. Daily reminders respect the account preference
and the device opt-out; sign-out clears the owned schedule. This feature uses
inexact scheduling and does not ask for exact-alarm access. Android battery
management can affect delivery time. Notification content contains no private
journal or health data.

Browser push retains the existing server subscription, profile timezone, and
conditional streak-at-risk alerts. Native reminders do not pretend to know
whether a streak is at risk while the app is closed. Settings labels distinguish
the daily device reminder from the existing browser alert. Unsupported older
APKs show an update message instead of trying unsupported WebView push.

## Validation and release

- JavaScript reminder regression checks: consent, denial, scheduling, changed
  time, opt-out, logout, older APKs, browser subscription and save rollback.
- PHP syntax checks and responsive browser review of production view fragments
  with sample data, plus public login, registration, and recovery pages.
- Android debug and optimized release bundle builds; release lint.
- Real Android permission UI and delivery still require an authorized device;
  the attached emulator currently reports `unauthorized`.

The web refresh ships through Hostinger. Native scheduling requires the new
Android build. Generate a signed version-code-3 Android App Bundle with the
existing upload key and use Play Console's testing track before rollout. The
locally generated `bundleRelease` output is unsigned.
