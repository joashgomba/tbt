# NTB Djibouti (`ntb_mobile`)

Flutter application package for the Djibouti NTB reporter mobile client.

**Full documentation** (Windows & macOS local setup, APK / App Store deployment, networking, troubleshooting):

→ **[`../README.md`](../README.md)**

Quick start (API already running on port 8000):

```bash
flutter pub get
flutter run --dart-define=APP_ENV=local
```

Physical Android over USB (mirror with scrcpy): see **[Local deployment with scrcpy](../README.md#local-deployment-with-scrcpy-windows--macos)** in the main mobile README.

Production build example:

```bash
flutter build apk --release \
  --dart-define=APP_ENV=production \
  --dart-define=API_BASE_URL=https://YOUR_HOST/api/v1
```
