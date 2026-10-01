# NTB submission APIs — overview

The NTB platform exposes multiple **complaint submission channels** that share the same validation rules and workflow engine:

| Channel | Base path | Auth |
|---------|-----------|------|
| Web portal | `/api/v1/public` | Laravel session (cookie) |
| Mobile app | `/api/v1/mobile` | Bearer token (`mobile_api_tokens`) |
| USSD | `/api/v1/channels/ussd` | Gateway secret header + registered phone |
| SMS | `/api/v1/public/sms` | Gateway secret header |
| Partner (read-only) | `/api/v1/partner` | Integration API client |

## Shared complaint payload

Required fields for full submissions:

- `description` — narrative of the barrier
- `occurrence_date` — `YYYY-MM-DD`, not in the future
- `country_affected` — must match configured country list
- `ntb_category_id` — master data id
- `country_origin`, `country_destination` — trade route countries
- `corridor_id`, `location_id` — master data ids

Optional fields:

- `title`
- `product_description`, `product_country_origin`
- `product_value_currency`, `product_value_amount` (both required if either is set)
- `attachments[]` — web/mobile only (PDF, JPG, PNG, DOCX; max 5 files)

Lookups (categories, corridors, locations, countries, currencies) are available from each channel’s `/lookups` endpoint where applicable.

## Reporter accounts

Web and mobile require an **economic operator** account with `complaints.submit`. USSD matches the subscriber MSISDN to an active user’s `phone_number`. SMS may pass `user_id` or match by phone.

## Environment

- `NTB_MOBILE_TOKEN_TTL_DAYS` — mobile bearer token lifetime (default 90)
- `NTB_USSD_SESSION_TTL_MINUTES` — USSD session cache TTL (default 5)
- System settings: `sms_webhook_secret`, `ussd_webhook_secret`

## Admin monitoring

- USSD session audit: `GET /api/v1/channels/ussd/sessions` (admin, `admin.integrations`)
- Session event trail: `GET /api/v1/channels/ussd/sessions/{id}/events`
