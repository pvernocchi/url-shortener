# URL Shortener

## API Tokens

Admins can issue API bearer tokens from **Admin → API Tokens → New Token**.

- Tokens are shown in plaintext only once after creation.
- Copy and store the token immediately; only the hash is saved in the database.

Example: create a short link via API:

```bash
curl -X POST "https://your-shortener.example/api/v1/links" \
  -H "Authorization: Bearer usk_your_token_here" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","redirect_type":302}'
```

## API Specification

A machine-readable OpenAPI specification is available at [`openapi.yaml`](openapi.yaml).

You can view it with Swagger UI, Redoc, or by importing it into https://editor.swagger.io.

## Login Security

- Configure global MFA policy and allowed factors in **Admin → Settings**.
- Users can manage profile settings in **Profile**.
- Users can opt in to TOTP in **Security** when MFA is globally enabled (supports Google Authenticator / Microsoft Authenticator).
- Login CAPTCHA supports **Google reCAPTCHA** and **Cloudflare Turnstile**.
