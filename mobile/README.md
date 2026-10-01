# NTB Djibouti — Flutter mobile app

Reporter-facing mobile client for the Djibouti NTB Portal (register, report trade barriers, track cases, attachments, transparency map).

| Resource | Location |
|----------|----------|
| Mobile API | [`resources/developer-docs/mobile-api.md`](../resources/developer-docs/mobile-api.md) |
| Flutter project | `mobile/ntb_mobile/` |
| Application ID (Android) | `dj.ntb.portal.ntb_mobile` |
| Display name | **NTB Djibouti** |

---

## Table of contents

1. [Prerequisites](#prerequisites)
2. [First-time setup](#first-time-setup)
3. [Run the Laravel API locally](#run-the-laravel-api-locally)
4. [Environment configuration](#environment-configuration)
5. [Run locally on Windows (Android)](#run-locally-on-windows-android)
6. [Run locally on macOS (Android + iOS)](#run-locally-on-macos-android--ios)
7. [Physical devices & networking](#physical-devices--networking)
8. [Install & test builds (APK / iOS app)](#install--test-builds-apk--ios-app)
9. [Deploy to Google Play Store](#deploy-to-google-play-store)
10. [Deploy to Apple App Store](#deploy-to-apple-app-store)
11. [Push notifications (optional)](#push-notifications-optional)
12. [Features](#features)
13. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### All platforms

- [Flutter SDK](https://docs.flutter.dev/get-started/install) (stable channel; **3.19+** recommended)
- Git clone of this repo with PHP/Composer for the Laravel backend
- Network access to your API (local LAN or production HTTPS)

Verify:

```bash
flutter doctor -v
cd mobile/ntb_mobile && flutter pub get
```

Fix anything `flutter doctor` marks as missing before continuing.

### Windows (Android local dev & APK builds)

| Tool | Purpose |
|------|---------|
| **Android Studio** | Android SDK, emulator, SDK Platform-Tools (`adb`) |
| **Visual Studio Build Tools** (with “Desktop development with C++”) | Often required by Flutter on Windows |
| **PHP 8.x + Composer** | Run Laravel (`php artisan serve`) on the same PC |

You **cannot** build or run the iOS app on Windows (requires macOS + Xcode).

### macOS (Android + iOS)

| Tool | Purpose |
|------|---------|
| **Xcode** (from App Store) | iOS Simulator, device builds, App Store archives |
| **CocoaPods** | `sudo gem install cocoapods` — iOS dependencies |
| **Android Studio** (optional) | Android emulator; or use a physical Android device |

After installing Xcode:

```bash
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
sudo xcodebuild -runFirstLaunch
cd mobile/ntb_mobile/ios && pod install && cd ../..
```

---

## First-time setup

From the repository root:

```bash
# Backend (once per machine)
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate
php artisan db:seed    # optional demo users

# Mobile
cd mobile/ntb_mobile
flutter pub get
```

**Demo login** (after seeders): see `NtbDemoUsersSeeder` in the repo — typically `operator@trade.dj` / `password` (confirm in root `README.md`).

---

## Run the Laravel API locally

The mobile app talks to **`/api/v1/mobile/*`** (auth, complaints) and **`/api/v1/public/*`** (track, transparency).

From the **repo root**:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

- **`0.0.0.0`** lets emulators and phones on your LAN reach the API (not only `127.0.0.1`).
- Quick sanity check on the dev machine:

```bash
curl -s http://127.0.0.1:8000/api/v1/public/transparency/lookups | head
```

**Windows firewall:** If a phone cannot connect, allow inbound TCP **8000** for Private networks, or temporarily disable the firewall for testing.

---

## Environment configuration

Environments are selected with **`--dart-define`** at `flutter run` / `flutter build` (no source edits).

| Define | Meaning |
|--------|---------|
| `APP_ENV=local` | Default; dev API URLs (see table below) |
| `APP_ENV=staging` | Staging defaults; override with `STAGING_API_BASE` |
| `APP_ENV=production` | Production defaults; override with `PRODUCTION_API_BASE` |
| `API_BASE_URL=https://host/api/v1` | **Full override** (recommended for production builds) |
| `LOCAL_API_HOST=192.168.1.50` | Shorthand for local: `http://HOST:8000/api/v1` |
| `FCM_ENABLED=true` | Enable Firebase push (requires FlutterFire config) |

**Default local API base (if `API_BASE_URL` is not set):**

| Target | Default URL |
|--------|-------------|
| Android **emulator** | `http://10.0.2.2:8000/api/v1` |
| iOS **Simulator** | `http://127.0.0.1:8000/api/v1` |
| **Physical phone** | **Must override** — see [Physical devices](#physical-devices--networking) |

Local/staging builds show the active API URL on the login screen.

**Production example:**

```bash
flutter run \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://portal.example.dj/api/v1
```

---

## Run locally on Windows (Android)

### 1. Start the API (PowerShell or CMD)

```powershell
cd C:\path\to\tbt
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Android emulator

1. Open **Android Studio → Device Manager** → create/start a virtual device (API 30+ recommended).
2. In a second terminal:

```powershell
cd C:\path\to\tbt\mobile\ntb_mobile
flutter devices
flutter run --dart-define=APP_ENV=local
```

The app uses `http://10.0.2.2:8000/api/v1` (emulator alias for your PC’s `localhost`).

### 3. Physical Android phone (USB)

1. Enable **Developer options** → **USB debugging** on the phone.
2. Install [Google USB driver](https://developer.android.com/studio/run/win-usb) if `flutter devices` does not list the phone.
3. **Same Wi‑Fi as the PC** — find your PC’s IPv4 address:

```powershell
ipconfig
# Example: Wireless LAN adapter Wi-Fi → IPv4 Address . . . : 192.168.1.50
```

4. Run with your LAN IP:

```powershell
flutter run --dart-define=APP_ENV=local ^
  --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

**Alternative (USB port forwarding, no Wi‑Fi API URL):**

```powershell
adb reverse tcp:8000 tcp:8000
flutter run --dart-define=APP_ENV=local ^
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

### 4. Hot reload vs restart

- **`r`** — hot reload (UI tweaks).
- **`R`** — hot restart (auth, routing, config).
- After changing **`--dart-define`**, stop the app and **`flutter run`** again.

---

## Run locally on macOS (Android + iOS)

### Laravel API

```bash
cd ~/Sites/tbt   # your clone path
php artisan serve --host=0.0.0.0 --port=8000
```

### Android emulator

```bash
cd mobile/ntb_mobile
flutter emulators
flutter emulators --launch <emulator_id>
flutter run --dart-define=APP_ENV=local
```

Uses `10.0.2.2` like Windows.

### iOS Simulator

```bash
cd mobile/ntb_mobile
open -a Simulator
flutter run --dart-define=APP_ENV=local
```

Uses `http://127.0.0.1:8000/api/v1`.

### Physical iPhone (same Wi‑Fi)

```bash
ipconfig getifaddr en0   # or en1 — your Mac’s Wi‑Fi IP
flutter run --dart-define=APP_ENV=local \
  --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

First-time device run: open **`ios/Runner.xcworkspace`** in Xcode → select your Team under **Signing & Capabilities** → trust the developer certificate on the iPhone (**Settings → General → VPN & Device Management**).

---

## Physical devices & networking

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Login times out after ~25s | Phone still using **`10.0.2.2`** (emulator-only) | Set `API_BASE_URL` or `LOCAL_API_HOST` to your PC/Mac LAN IP |
| “Cannot connect” | API not on `0.0.0.0`, wrong IP, or firewall | `php artisan serve --host=0.0.0.0 --port=8000`; check firewall |
| HTTP blocked on Android | Cleartext HTTP | Debug builds allow HTTP via `network_security_config`; production should use **HTTPS** |
| 419 / CSRF on login | Old backend | Ensure Laravel CSRF exceptions for `api/v1/mobile/*` (see repo `bootstrap/app.php`) |

**Find LAN IP**

- **Windows:** `ipconfig`
- **macOS:** `ipconfig getifaddr en0`

Test from the phone’s browser: `http://YOUR_IP:8000` (you should see Laravel or a response, not a timeout).

---

## Install & test builds (APK / iOS app)

### Android — debug APK (quick share / sideload)

```bash
cd mobile/ntb_mobile
flutter build apk --debug --dart-define=APP_ENV=local \
  --dart-define=API_BASE_URL=http://192.168.1.50:8000/api/v1
```

Output: **`build/app/outputs/flutter-apk/app-debug.apk`**

Install on a connected device:

```bash
adb install -r build/app/outputs/flutter-apk/app-debug.apk
```

Or copy the APK to the phone and open it (allow **Install unknown apps** for your file manager / browser).

### Android — release APK (testing before store)

Production API baked in at build time:

```bash
flutter build apk --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Output: **`build/app/outputs/flutter-apk/app-release.apk`**

```bash
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

> **Note:** Release builds currently use the **debug signing key** in `android/app/build.gradle` so local `flutter build apk --release` works. For Play Store you must configure a **release keystore** (see below).

### Android — App Bundle (recommended for Play Store)

```bash
flutter build appbundle --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Output: **`build/app/outputs/bundle/release/app-release.aab`**

### iOS — run on device / Simulator (debug)

```bash
flutter run --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

### iOS — release build (Archive / TestFlight)

```bash
flutter build ios --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Then:

```bash
open ios/Runner.xcworkspace
```

In Xcode: select **Any iOS Device (arm64)** → **Product → Archive** → **Distribute App** (TestFlight or App Store Connect).

---

## Deploy to Google Play Store

### 1. Create a release keystore (once)

```bash
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

Store the keystore and passwords securely (not in git).

### 2. Configure signing in the project

Create **`mobile/ntb_mobile/android/key.properties`** (add to `.gitignore`):

```properties
storePassword=<password>
keyPassword=<password>
keyAlias=upload
storeFile=<absolute-or-relative-path-to-upload-keystore.jks>
```

Update **`android/app/build.gradle`** to load `key.properties` and set `release { signingConfig signingConfigs.release }` per [Flutter Android deployment docs](https://docs.flutter.dev/deployment/android#signing-the-app).

### 3. Version numbers

Edit **`pubspec.yaml`**: `version: 1.0.0+1` → `name+buildNumber` (Play uses `+1` as `versionCode`).

### 4. Build the App Bundle

```bash
cd mobile/ntb_mobile
flutter build appbundle --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

### 5. Play Console

1. [Google Play Console](https://play.google.com/console) → create app.
2. **Release → Production** (or internal testing first) → upload **`app-release.aab`**.
3. Complete store listing, content rating, privacy policy, and **Data safety**.
4. Ensure production API uses **HTTPS** and valid TLS certificate.

### 6. Internal / closed testing (recommended first)

Upload the same AAB to **Internal testing**, add tester emails, share the opt-in link, then promote to production.

---

## Deploy to Apple App Store

Requirements: **Apple Developer Program** membership, macOS, Xcode.

### 1. App ID & signing

1. [Apple Developer](https://developer.apple.com/account) → **Identifiers** → App ID matching bundle ID (e.g. `dj.ntb.portal.ntbMobile` — confirm in Xcode).
2. In Xcode (**Runner** target): **Signing & Capabilities** → Team, **Automatically manage signing**.

### 2. Version & build

In **`pubspec.yaml`**: `version: 1.0.0+1`  
- **CFBundleShortVersionString** = `1.0.0`  
- **CFBundleVersion** = `1`

### 3. Production API in the binary

```bash
flutter build ios --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

App Store builds must use **HTTPS** (ATS); local HTTP exceptions in `Info.plist` are for development only.

### 4. Archive & upload

1. `open ios/Runner.xcworkspace`
2. Scheme **Runner**, destination **Any iOS Device (arm64)**
3. **Product → Archive**
4. **Organizer → Distribute App** → **App Store Connect** → Upload

### 5. App Store Connect

1. [App Store Connect](https://appstoreconnect.apple.com) → **My Apps** → new app.
2. Attach the uploaded build under **TestFlight** (beta) or **App Store** tab (review).
3. Fill metadata, screenshots, privacy policy, export compliance.

### 6. TestFlight (beta testers)

After processing, add **Internal** or **External** testers in TestFlight before submitting for public App Store review.

---

## Push notifications (optional)

1. **Backend:** `php artisan migrate` (includes `mobile_push_devices`); set `NTB_FCM_SERVER_KEY` in Laravel `.env`.
2. **Mobile:** [FlutterFire CLI](https://firebase.flutter.dev/docs/overview) → `flutterfire configure` in `mobile/ntb_mobile` (updates `lib/core/config/firebase_options.dart`).
3. Add **`google-services.json`** (Android) and **`GoogleService-Info.plist`** (iOS) via FlutterFire.

```bash
flutter run --dart-define=FCM_ENABLED=true \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_PRODUCTION_HOST/api/v1
```

Profile → **Case update notifications** toggles per-device preferences.

---

## Features

- Register / login (mobile token API)
- English / French UI
- Home, help, multi-step NTB report (searchable dropdowns)
- Offline drafts; submit when online
- Transparency insights (public stats + map)
- My cases, detail timeline, public track-by-code
- Push notifications when FCM is enabled
- Profile, logout, visible API environment in non-production builds

---

## Troubleshooting

### Gradle: `FlutterAppPluginLoaderPlugin` / `settings.gradle`

Gradle may load a stale **`gradle-1.0.0.jar`** from `~/.gradle/caches` instead of the Flutter SDK plugin. This repo’s `android/settings.gradle` uses the SDK **`includeBuild`** loader.

```bash
cd mobile/ntb_mobile
rm -rf ~/.gradle/caches/jars-9/c0db806a1a1f0a6be76ee473e65f14a5
cd android && ./gradlew --stop && cd ..
flutter clean && flutter pub get
flutter run --dart-define=APP_ENV=local
```

On Windows, use the same paths under `%USERPROFILE%\.gradle\caches\`.

### `connectivity_plus` / AGP

The project pins **`connectivity_plus: ^6.0.5`** for compatibility with the current Android Gradle Plugin. Do not upgrade to 7.x without upgrading Flutter/AGP together.

### Login timeout on a real phone

See [Physical devices & networking](#physical-devices--networking). The login screen shows a warning when a physical Android device is still pointed at `10.0.2.2`.

### iOS pod errors

```bash
cd mobile/ntb_mobile/ios
pod deintegrate && pod install
cd ..
flutter clean && flutter pub get
```

---

## Project layout

```
mobile/ntb_mobile/lib/
  core/           # config, theme, HTTP, storage, routing, i18n
  features/       # auth, complaints, home, profile, transparency
  app.dart        # MaterialApp + router
  main.dart       # entry + AppConfig.load()
```

---

## Reference

- Training deck: `EAC_NTB_App_Training_Presentation.pptx` (EAC NTB UX reference)
- Flutter deploy: [Android](https://docs.flutter.dev/deployment/android) · [iOS](https://docs.flutter.dev/deployment/ios)
