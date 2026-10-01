# Web portal API

Base URL: `{APP_URL}/api/v1/public`

Uses Laravel **session cookies** (same origin as the Vue portal). Include CSRF token header `X-CSRF-TOKEN` from the page meta tag.

## Reporter registration

`POST /auth/register` — creates economic operator account and session.

## Session auth

- `POST /api/v1/auth/login` — staff or reporters (cookie session)
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

## Complaints

- `GET /lookups`
- `POST /complaints` — **requires auth** + `complaints.submit`; supports `multipart/form-data` attachments
- `GET /complaints/{code}` — public tracking by reference code

Payload fields match the mobile API. Submission channel is recorded as `WEB`.

See **Mobile app API** documentation for the shared JSON field list.
