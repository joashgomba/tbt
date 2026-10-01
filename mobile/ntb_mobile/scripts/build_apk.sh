#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

APP_ENV="${APP_ENV:-production}"
API_BASE_URL="${API_BASE_URL:-}"

ARGS=(--release --dart-define="APP_ENV=${APP_ENV}")
if [[ -n "${API_BASE_URL}" ]]; then
  ARGS+=(--dart-define="API_BASE_URL=${API_BASE_URL}")
fi

flutter build apk "${ARGS[@]}"
echo "APK: build/app/outputs/flutter-apk/app-release.apk"
