# Render + PlanetScale

## Render

- Runtime: `PHP`
- Branch: `main`
- Build/start commands: see `render.yaml`
- Health check path: `/up`

Set these environment variables in Render:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-render-backend.onrender.com`
- `FRONTEND_URL=https://your-cloudflare-pages.pages.dev`
- `APP_KEY=` your generated Laravel key
- `DB_CONNECTION=mysql`
- `DB_HOST=` your PlanetScale host
- `DB_PORT=3306`
- `DB_DATABASE=` your PlanetScale database name
- `DB_USERNAME=` your PlanetScale username
- `DB_PASSWORD=` your PlanetScale password
- `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`

Optional if used by your app:

- `MAIL_*`
- `PUSHER_*`
- `ERTITECH_*`
- `RETAILER_RECHARGE_*`
- `RAZORPAY_*`

Generate an app key locally:

```bash
php artisan key:generate --show
```

## PlanetScale

Use the PlanetScale MySQL connection details in the Render environment variables above.
