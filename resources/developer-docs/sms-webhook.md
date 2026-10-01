# SMS webhook

`POST /api/v1/public/sms/complaints`

Header (when configured): `X-SMS-Secret: {sms_webhook_secret}`

## Payload

The SMS aggregator should map parsed SMS fields into structured JSON:

| Field | Required | Notes |
|-------|----------|-------|
| `phone` | Yes | Reporter MSISDN |
| `message` | Yes | Barrier description |
| `ntb_category_id` | Yes | Resolved by middleware or keyword map |
| `corridor_id`, `location_id` | Yes | Master data ids |
| `country_origin`, `country_destination` | Yes | |
| `occurrence_date` | No | Defaults to today |
| `country_affected` | No | Defaults to `country_destination` |
| `product_*` | No | Same rules as web |
| `user_id` | No | Link to registered reporter |

If an active user matches `phone` (or `user_id`) with `complaints.submit`, the case is attributed to that account. Otherwise a phone-only reporter record is stored with channel `SMS`.

## Response

```json
{
  "message": "SMS complaint recorded",
  "complaint_code": "NTB-2026-000123"
}
```

Integrators should reply to the subscriber SMS with the reference code.
