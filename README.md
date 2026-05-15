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
