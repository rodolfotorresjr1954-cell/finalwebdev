# Connect mobile app + local dev to Railway

Your **local Docker MySQL** and **Railway MySQL** are separate unless you point both apps at Railway.

## 1. Railway variables (Burger service)

In Railway → Burger service → **Variables**, set:

| Variable | Value |
|----------|--------|
| `DATABASE_URL` | Copy from Railway **MySQL** plugin → Connect → `MYSQL_URL` |
| `APP_SECRET` | Random string (32+ chars) |
| `JWT_PASSPHRASE` | Random string (openssl rand -hex 32) |
| `OAUTH_PUBLIC_HOST` | Your public host only, e.g. `finalwebdev-production.up.railway.app` |
| `GOOGLE_CLIENT_ID` | From Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | From Google Cloud Console |
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1\|.*\.railway\.app)(:[0-9]+)?$` |

Redeploy after saving variables. Check deploy logs for `Running database migrations...`.

## 2. Google OAuth (if using Sign in with Google)

In [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → your OAuth client → **Authorized redirect URIs**, add:

```
https://YOUR-RAILWAY-DOMAIN.up.railway.app/connect/google/check
```

## 3. Connect the mobile app (ACT1)

1. Railway → Burger → **Settings** → **Networking** → copy the public URL (e.g. `https://finalwebdev-production-xxxx.up.railway.app`).
2. Open `ACT1/src/config/railway.ts` and set:

   ```ts
   export const RAILWAY_PUBLIC_URL = 'https://YOUR-RAILWAY-DOMAIN.up.railway.app';
   ```

3. Reload the app: Metro terminal → press **`r`**.

Logins and orders from the phone now go to the **Railway database**.

## 4. (Optional) Same DB when running Symfony on your PC

To use Railway data while developing on your laptop, add to `Burger/.env.local`:

```env
DATABASE_URL="mysql://USER:PASS@HOST:PORT/railway"
```

(Copy the full `DATABASE_URL` from Railway MySQL → Connect.)

Then run `php -S 0.0.0.0:8000 -t public` — your local site and mobile app (with Railway URL) share one database.

## 5. Create users on Railway

Fixtures are **not** loaded on deploy. Either:

- **Register** on your live Railway site, or
- One-time seed (Railway CLI):

  ```bash
  railway run php bin/console doctrine:fixtures:load --no-interaction
  ```

Default fixture users: `adminss` / `adminpassword`, `staffs` / `staffpassword`.

## 6. View data in Railway

Railway → **MySQL** → **Data** tab → tables `user`, `` `order` ``.
