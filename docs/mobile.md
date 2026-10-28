# PayGuard — App Android

Companion app installed on the business phone that receives the payments.
It reads Yape notifications and sends them to the server, even with the
screen off, the app closed or after a reboot.

## Build the APK

Prerequisites: Flutter SDK (stable) and the Android SDK with
`flutter.sdk` set in `mobile/android/local.properties`
(`local.properties` is never committed).

```bash
cd mobile
flutter pub get
flutter build apk --release
```

The APK is generated at
`mobile/build/app/outputs/flutter-apk/app-release.apk`.

Useful commands:

```bash
flutter analyze
flutter test
flutter build apk --debug   # local testing, allows plain HTTP
```

To point a build at another server without touching the hidden setting:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://staging.example.com
```

## Install

1. Copy the APK to the phone (USB, Drive, WhatsApp to yourself).
2. Open it and allow "install unknown apps" when Android asks.
3. Open PayGuard and log in with the owner account.

## Owner guide: permissions (do all of them)

1. **Read notifications.** The app opens the system screen: find
   PayGuard and turn the switch on.
   - On Android 13 or newer, sideloaded apps need one extra step
     first: Settings > Apps > PayGuard > menu (three dots) >
     "Allow restricted settings". Then go back and turn the switch on.
   - The app only reads Yape notifications. Everything else is
     ignored on the phone and never leaves it.
2. **Battery exemption (mandatory).** The app asks to ignore battery
   optimizations: accept. Without this, Android stops the app and
   payments stop arriving.
3. **Autostart.** On Xiaomi, Huawei, Oppo and similar brands, also
   allow PayGuard to start automatically so it keeps working after a
   reboot (the app has a button that opens its system settings page).

The Status screen shows a checklist while anything is missing and the
day stats with recent payments once everything is green. Pull down to
refresh; pending items upload automatically when there is internet.

## Hidden server address (local testing only)

On the login screen, tap the "Iniciar sesión" title 5 times to reveal
the server field. Examples:

- Emulator: `http://10.0.2.2:8000` (debug build)
- Physical phone on the same Wi-Fi: `http://<your-pc-ip>:8000` (debug build)

Leave the field empty to go back to the normal address.
Debug builds allow plain HTTP; release builds are HTTPS-only.

## Signing

Release builds currently sign with the debug keys so the APK can be
installed during the hackathon. Before any public distribution,
create a real keystore, keep it **outside the repo**, and add a
`signingConfigs` block in `mobile/android/app/build.gradle.kts`.
The keystore, its passwords and `local.properties` must never be
committed.

## How survival works (summary)

- `PayGuardListener` (Kotlin `NotificationListenerService`) filters by
  package on the phone, stores each valid notification in a local
  SQLite queue with its own idempotency key, and tries an immediate
  send. If anything is left pending, it enqueues an urgent WorkManager
  job (expedited, requires internet, exponential backoff).
- HTTP 200/201 mark the item sent; 429 and 5xx/network errors retry
  later; any other 4xx discards the item. A 401 keeps the queue and
  marks the session as invalid so the owner logs in again (nothing is
  lost).
- If the listener disconnects, it asks Android to rebind it with
  `requestRebind`. The component is never disabled and re-enabled,
  because that would wipe the permission.
- A boot receiver (`BOOT_COMPLETED`, `MY_PACKAGE_REPLACED`) and a
  watchdog alarm (about every 15 minutes) re-arm the queue and send
  the heartbeat. No exact frequency is promised: in Doze mode Android
  delays alarms, which is fine because the panel only flags
  "Desconectado" after 10 minutes without contact
  (`PAYGUARD_DEVICE_OFFLINE_MINUTES`).
- The token and the server address live encrypted (AES/GCM with an
  Android Keystore key) so both Flutter and the native code can use
  them without Flutter being alive.
