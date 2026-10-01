# NTB Djibouti — Flutter mobile app

Reporter-facing mobile client for the Djibouti NTB Portal (EAC NTB–style flows: register, report barriers, track cases, attachments).

Backend API: [`resources/developer-docs/mobile-api.md`](../resources/developer-docs/mobile-api.md)

## Prerequisites

1. [Flutter SDK](https://docs.flutter.dev/get-started/install) (stable channel, 3.19+ recommended)
2. Xcode (iOS Simulator / device builds on macOS)
3. Android Studio or Android SDK (emulator / APK)
4. Laravel API running locally or reachable production URL

Verify setup:

```bash
flutter doctor
cd mobile/ntb_mobile && flutter pub get
```

## Project layout

```
mobile/ntb_mobile/lib/
  core/           # config, theme, HTTP, storage, routing, i18n
  features/       # auth, complaints, home, profile (feature-first)
  app.dart        # MaterialApp + router
  main.dart       # entry + AppConfig.load()
```

## Environment configuration (single switch)

All environments are selected with **`--dart-define`** (no code changes):

| Environment | Command defines |
|-------------|-----------------|
| **Local** (default) | `APP_ENV=local` — API `http://127.0.0.1:8000/api/v1` (iOS) or `http://10.0.2.2:8000/api/v1` (Android emulator) |
| **Staging** | `APP_ENV=staging` — override with `STAGING_API_BASE` if needed |
| **Production** | `APP_ENV=production` — override with `PRODUCTION_API_BASE` or explicit `API_BASE_URL` |

Examples:

```bash
# Local against `php artisan serve` on port 8000
flutter run --dart-define=APP_ENV=local

# Production
flutter run --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://your-portal.dj/api/v1

# Custom URL only
flutter run --dart-define=API_BASE_URL=https://staging.example.com/api/v1
```

Start the Laravel API (from repo root):

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Emulator vs device (local API):**

| Target | API URL (default `APP_ENV=local`) |
|--------|-----------------------------------|
| **Android emulator** | `http://10.0.2.2:8000/api/v1` (maps to your Mac/PC `localhost:8000`) |
| **iOS Simulator** | `http://127.0.0.1:8000/api/v1` |
| **Physical phone** (same Wi‑Fi) | Your computer’s LAN IP — **not** `10.0.2.2`. Example: `flutter run --dart-define=APP_ENV=local --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1` or `--dart-define=LOCAL_API_HOST=192.168.1.50` |
| **Physical phone** (USB + scrcpy/adb) | `adb reverse tcp:8000 tcp:8000` then `flutter run --dart-define=APP_ENV=local --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1` |

Find your Mac’s Wi‑Fi IP: `ipconfig getifaddr en0` (or check System Settings → Network).

Quick check from your machine: `curl -s http://127.0.0.1:8000/api/v1/public/transparency/lookups | head`

The login screen shows the configured API base URL in local/staging builds.

## Run on simulators

```bash
cd mobile/ntb_mobile
flutter devices
flutter run --dart-define=APP_ENV=local
```

After startup/auth code changes, use **hot restart** (`R` in the terminal) or stop and run again — hot reload (`r`) does not re-run session bootstrap.

**Demo mobile login** (after seeders): `operator@trade.dj` / password from `NtbDemoUsersSeeder` (see root `README.md`).

## Build installable test packages

### Android APK

```bash
cd mobile/ntb_mobile
flutter build apk --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Output: `build/app/outputs/flutter-apk/app-release.apk`

For local testing APK pointing at your machine (same Wi‑Fi, use your LAN IP):

```bash
flutter build apk --release \
  --dart-define=APP_ENV=local \
  --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

Install: `adb install build/app/outputs/flutter-apk/app-release.apk`

**Gradle `FlutterAppPluginLoaderPlugin` / `settings.gradle` line 21:** Gradle sometimes loads a stale `gradle-1.0.0.jar` from `~/.gradle/caches` instead of the Flutter SDK plugin. The project’s `android/settings.gradle` uses the SDK `includeBuild` loader (no portal version pin). If the error persists:

```bash
cd mobile/ntb_mobile
rm -rf ~/.gradle/caches/jars-9/c0db806a1a1f0a6be76ee473e65f14a5
cd android && ./gradlew --stop && cd ..
flutter clean && flutter pub get
flutter run --dart-define=APP_ENV=local
```

### iOS (simulator / device)

Simulator (debug):

```bash
flutter run --dart-define=APP_ENV=local
```

Device / TestFlight-style archive (requires Apple signing in Xcode):

```bash
flutter build ios --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Then open `ios/Runner.xcworkspace` in Xcode → **Product → Archive**, or run:

```bash
open ios/Runner.xcworkspace
```

For ad-hoc device testing, configure **Signing & Capabilities** with your Team and a unique bundle id (`dj.ntb.portal.ntbMobile`).

## Features implemented

- Register / login (mobile token API)
- EN / FR UI strings
- Home dashboard and help
- Multi-step NTB report (category, route, product, attachments)
- **Offline drafts** — auto-saved locally; submit when back online
- **Transparency Insights** — public stats + OpenStreetMap heat markers (no login)
- **Push notifications** — FCM when case status changes (see below)
- My cases list and detail with status timeline
- Public track-by-code (no login)
- Profile, logout, visible API environment for support

### Push notifications setup

1. **Backend:** `php artisan migrate` (creates `mobile_push_devices`), then set `NTB_FCM_SERVER_KEY` in Laravel `.env` (Firebase Cloud Messaging legacy server key or project setup).
2. **Mobile:** Install [FlutterFire CLI](https://firebase.flutter.dev/docs/overview), run `flutterfire configure` in `mobile/ntb_mobile` (replaces `lib/core/config/firebase_options.dart`).
3. **Run/build with FCM enabled:**

```bash
flutter run --dart-define=FCM_ENABLED=true --dart-define=APP_ENV=local
```

Add `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) via FlutterFire.

Profile → **Case update notifications** toggles `notify_case_updates` per device.

## Reference

Training deck: `EAC_NTB_App_Training_Presentation.pptx` (EAC NTB App UX reference).
