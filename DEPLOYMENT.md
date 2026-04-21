# Render + FreeDB

## Render

- Runtime: `PHP`
- Branch: `main`
- Build/start commands: see `render.yaml`
- Health check path: `/up`

Set these environment variables in Render:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-render-backend.onrender.com`
- `FRONTEND_URL=https://your-vercel-project.vercel.app`
- `APP_KEY=` your generated Laravel key
- `DB_CONNECTION=mysql`
- `DB_HOST=` your FreeDB host
- `DB_PORT=3306`
- `DB_DATABASE=` your FreeDB database name
- `DB_USERNAME=` your FreeDB username
- `DB_PASSWORD=` your FreeDB password
- `MYSQL_ATTR_SSL_CA=` leave blank unless your FreeDB account specifically requires a CA certificate path

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

## FreeDB

Use the FreeDB MySQL connection details in the Render environment variables above.
