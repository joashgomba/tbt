# Mobile app API

Base URL: `{APP_URL}/api/v1/mobile`

All JSON requests should send `Accept: application/json` and `Content-Type: application/json` unless uploading files.

## Authentication

### Register

`POST /auth/register`

```json
{
  "full_name": "Jane Trader",
  "email": "jane@example.com",
  "password": "secret123",
  "password_confirmation": "secret123",
  "phone_number": "+25377000000",
  "organization_name": "Acme Trading",
  "country": "Djibouti",
  "preferred_language": "en",
  "device_name": "Pixel 8",
  "accept_terms": true
}
```

Response `201`:

```json
{
  "token_type": "Bearer",
  "access_token": "<store securely>",
  "expires_at": "2026-12-28T12:00:00+00:00",
  "user": { "id": 1, "name": "Jane Trader", "permissions": ["complaints.submit"] }
}
```

### Login

`POST /auth/login` — same fields as register except use `email`, `password`, `device_name` only.

### Authenticated calls

Header: `Authorization: Bearer {access_token}`

- `GET /auth/me`
- `POST /auth/logout` — revokes current token
- `GET /lookups` — master data for forms
- `GET /complaints` — list own submissions (`?limit=20`)
- `GET /complaints/{code}` — detail for own case
- `POST /complaints` — submit (JSON or `multipart/form-data` with attachments)

### Submit complaint

`POST /complaints`

JSON body example:

```json
{
  "description": "Customs held shipment for 3 days without reason.",
  "occurrence_date": "2026-09-15",
  "country_affected": "Djibouti",
  "ntb_category_id": 3,
  "country_origin": "Ethiopia",
  "country_destination": "Djibouti",
  "corridor_id": 1,
  "location_id": 4,
  "product_description": "Coffee beans, 20 tonnes",
  "product_country_origin": "Ethiopia",
  "product_value_currency": "USD",
  "product_value_amount": 45000
}
```

Duplicate detection returns `409` with `duplicate_warning: true`. Resubmit with `"confirm_duplicate": true` to force creation.

## Push notifications (FCM)

Register the device after login when Firebase is configured in the mobile app:

`POST /push/register`

```json
{
  "fcm_token": "<firebase device token>",
  "platform": "android",
  "device_name": "Pixel 8"
}
```

`POST /push/unregister` — same body with `fcm_token`.

`PUT /push/preferences` — `{ "fcm_token": "...", "notify_case_updates": true }`

Server: set `NTB_FCM_SERVER_KEY` in `.env`. Status changes on cases created by the user trigger push notifications.

## Public transparency (no auth)

The mobile **Insights** tab uses:

- `GET /api/v1/public/transparency/lookups`
- `GET /api/v1/public/transparency/stats?year=2026`
- `GET /api/v1/public/transparency/map?year=2026`

## Error codes

| HTTP | Meaning |
|------|---------|
| 401 | Missing/invalid token |
| 403 | Account cannot submit |
| 409 | Possible duplicate |
| 422 | Validation errors |
