# USSD channel API

Base URL: `{APP_URL}/api/v1/channels/ussd`

## Production callback (telco / aggregator)

`POST /callback`

Authentication: header `X-USSD-Secret` when `ussd_webhook_secret` is set in system settings.

### Request body (normalized aliases supported)

| Field | Aliases | Description |
|-------|---------|-------------|
| `session_id` | `sessionId`, `SessionId` | Unique session from gateway |
| `phone_number` | `phoneNumber`, `msisdn`, `MSISDN` | Subscriber MSISDN |
| `text` | `userInput`, `input` | Cumulative choices separated by `*` (empty on first dial) |
| `service_code` | `serviceCode` | Optional short code e.g. `*826#` |

### Response

**JSON (default):** `session_active`, `message` (`CON` / `END` prefix), `plain_message`, `state`, `complaint_code`

**Plain text:** `Accept: text/plain` → body is the `CON`/`END` line only (Africa's Talking style).

**Africa's Talking JSON:** `X-USSD-Response-Format: africas_talking` → `{ "response": "...", "close": false }`

## Navigation (revised NTB tree)

1. **Main menu** — Submit complaint · Track complaint · Practical information · Exit
2. **Submit** — Category → location type → location (if needed) → issue → short description → trader ID → confirm → reference
3. **Track** — Reference → verify (MSISDN match, USSD PIN, or SMS OTP) → workflow status (no disclosure without verification)
4. **Practical info** — My references (verified) · Support · How to submit

Configurable copy, support contacts, SLA-aligned status text, and issue lists: `config/ussd.php` (admin settings extension planned).

## Admin simulator

Authenticated staff with `admin.integrations`:

- `POST /api/v1/admin/channels/ussd/simulate` — same engine as production
- `GET /api/v1/admin/channels/ussd/schema` — integration metadata
- UI: **Admin → USSD Simulator**

## Audit

- `GET /api/v1/admin/channels/ussd/sessions`
- `GET /api/v1/admin/channels/ussd/sessions/{id}/events`

## Short-code handoff checklist

1. Set `NTB_USSD_SHORT_CODE` in `.env` when MoTT / Djibouti Telecom confirms the code.
2. Configure `ussd_webhook_secret` in system settings.
3. Point the telco callback URL to `{APP_URL}/api/v1/channels/ussd/callback`.
4. Map gateway fields to `session_id`, `phone_number`, `text` (see aliases above).
5. UAT using the admin simulator with real test MSISDNs registered on the portal.

## Example (curl)

```bash
curl -X POST "$APP_URL/api/v1/channels/ussd/callback" \
  -H "Content-Type: application/json" \
  -H "X-USSD-Secret: YOUR_SECRET" \
  -d '{"session_id":"GW-001","phone_number":"+25377000001","text":""}'
```

Follow-up input `1` (submit): send `"text":"1"`. Next level: `"text":"1*2"` etc.
