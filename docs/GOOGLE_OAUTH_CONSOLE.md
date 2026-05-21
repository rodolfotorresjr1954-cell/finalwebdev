# Google OAuth – redirect URI locked in Console?

Your client already has:

`http://127.0.0.1:8000/connect/google/check`

The mobile app and dev server are configured to use **port 8000** so you do **not** need to edit redirect URIs for USB + Google sign-in.

## Run (phone on USB)

```bat
adb reverse tcp:8000 tcp:8000
cd Burger
php -S 0.0.0.0:8000 -t public
```

Then reload the ACT1 app and use **Sign in with Google**.

## If redirect URIs are greyed out

Common causes:

1. **View-only access** – You need **Editor** or **Owner** on project “Grilled and Bites Burger” (IAM → grant yourself `roles/editor` or ask the project owner).
2. **Wrong client type** – Only **Web application** clients allow custom redirect URIs. Desktop clients use a fixed loopback URI.
3. **New Google Auth UI** – Open [Credentials](https://console.cloud.google.com/apis/credentials), click the client name, then use **Edit** / pencil at the top if fields look read-only.

## If you must add URIs later

Create a **new** OAuth client (Web application), copy Client ID/secret into `Burger/.env`, and add:

- `http://127.0.0.1:8000/connect/google/check`
- `http://localhost:8000/connect/google/check`
