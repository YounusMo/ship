# Secret rotation playbook

What's already done and what's pending. Each entry has the exact steps for
when you have dashboard access to the provider.

---

## Completed

| Secret | Rotated on | Notes |
|--------|-----------|-------|
| `APP_KEY` | 2026-06-07 | `php artisan key:generate --force`. No encrypted columns, no signed URLs in the app — only effect is that active session cookies invalidate (you log back in once). |
| `DB_PASSWORD` (local MySQL) | 2026-06-07 | New 32-char base64 password; `ALTER USER 'ship_user'@'localhost'` + `.env` updated. App reconnects cleanly, all 135 tests green. |
| `SHIPSGO_API_KEY` | 2026-06-08 | New 36-char UUID-format token; `.env` updated, config cache cleared. Validated by `curl -H "X-Shipsgo-User-Token: ..." https://api.shipsgo.com/v2/ocean/shipments/0000000000` returning `404 NOT_FOUND` (which is the auth-OK response). Old key should now be revoked in the ShipsGo dashboard. |

The old `.env` is preserved at `system/.env.bak-<timestamp>` as a one-time snapshot. Delete once you're confident the rotation is good (a day or two of running is plenty).

---

## Pending — third-party providers (need you on a dashboard)

### 1) `MAIL_PASSWORD` — Mailtrap

> **Currently N/A.** As of 2026-06-08 the local `.env` has
> `MAIL_MAILER=log` and `MAIL_PASSWORD=` (empty) — emails dump to
> `storage/logs/laravel.log` instead of going over the wire. No live
> credential to rotate. The Mailtrap account password should still be
> rotated at the provider since past `.env` history exposed it; once
> rotated you can either leave the local mailer on `log` or swap back
> to `smtp` by re-pasting the new password.

Detected provider (from old config): `MAIL_HOST=live.smtp.mailtrap.io`.

1. Log in at https://mailtrap.io/.
2. **Sending Domains → SMTP/API Integration** → pick the active sending domain.
3. **Generate new credentials** (Mailtrap UI usually shows a "Reset password" or "Regenerate" button under the SMTP credentials block).
4. Copy the new password.
5. On the production host (or locally for now):
   ```bash
   # In system/.env:
   sed -i.bak 's|^MAIL_PASSWORD=.*|MAIL_PASSWORD=<paste-here>|' system/.env

   # Clear config cache so Laravel re-reads .env:
   cd system && php artisan config:clear

   # Restart queue worker so in-flight mail jobs use the new credentials:
   sudo supervisorctl restart shipflow-worker:* 2>/dev/null \
     || echo "no worker running locally, skip"
   ```
6. Smoke-test by triggering a password reset and confirming the email arrives:
   ```bash
   php artisan tinker --execute="
   \App\Models\User::find(1)->sendPasswordResetNotification('test-token');
   echo 'queued; check the mail provider dashboard for delivery';"
   ```

If the password reset email never arrives, check `storage/logs/laravel.log` for SMTP auth errors and re-confirm you pasted the new password correctly.

---

### 2) `SHIPSGO_API_KEY` — ShipsGo

1. Log in at https://my.shipsgo.com/.
2. **My Account → API → Tokens** (path varies slightly; look for "API Keys" or "Tokens").
3. Generate a new key. Don't revoke the old one yet — keep both alive during the swap.
4. Update `.env`:
   ```bash
   sed -i.bak 's|^SHIPSGO_API_KEY=.*|SHIPSGO_API_KEY=<paste-here>|' system/.env
   cd system && php artisan config:clear
   sudo supervisorctl restart shipflow-worker:* 2>/dev/null \
     || echo "no worker running locally, skip"
   ```
5. Smoke-test via the diagnostic command:
   ```bash
   php artisan tracking:shipsgo-smoke
   # Expect: a successful round-trip with a sample container number.
   ```
6. **After** confirming the new key works, revoke the old one in the ShipsGo dashboard.

---

### 3) `SHIPSGO_WEBHOOK_SECRET` — ShipsGo (currently empty)

The local `.env` has this blank, which means webhook signature verification is bypassed in dev. **Before going to production**, set it:

1. In the ShipsGo dashboard, **Webhooks** section, generate a webhook secret.
2. ShipsGo lets you keep two secrets active during rotation (most providers do). Paste both, then rotate one at a time.
3. Update `.env`:
   ```bash
   sed -i.bak 's|^SHIPSGO_WEBHOOK_SECRET=.*|SHIPSGO_WEBHOOK_SECRET=<paste-here>|' system/.env
   php artisan config:clear
   ```
4. The signature is verified inside `App\Modules\Tracking\Services\ShipsGo\ShipsGoWebhookVerifier` using `hash_hmac('sha256', $rawBody, $secret)`. Test by replaying a recent webhook from the ShipsGo dashboard with the new secret and confirming `webhook_deliveries.processed_at` populates.

---

## Pending — production infrastructure (only relevant when you deploy)

These don't exist yet because the local dev environment isn't using them.

### `AUDIT_ADMIN_PASSWORD` — generate fresh on first deploy

Per the gap #10 split, the production main DB user (`ship_user`) gets stripped of DELETE on `audit_log`, and a separate user (`ship_audit_admin`) holds DELETE rights. Generate this user's password at deploy time, never rotate it back into the dev environment.

See `system/docs/DEPLOYMENT.md` step 4 for the exact `CREATE USER` + `GRANT` block.

### `FCM_CREDENTIALS_PATH` JSON

Firebase Cloud Messaging service-account JSON. **Not** an env-var secret — it's a file. Rotation:

1. **Firebase Console → Project Settings → Service Accounts → Generate new private key**.
2. Drop the new JSON in place over the file at `FCM_CREDENTIALS_PATH` (default: `/var/www/shipflow/system/storage/app/private/fcm.json`).
3. Restart the queue worker.
4. **Then** revoke the old service-account key in the Firebase console (under the same Service Accounts page).

There is no `FCM_CREDENTIALS_PATH` value to rotate — only the file behind it. If the file is ever leaked, that single rotation in Firebase invalidates the leaked key.

### `SENTRY_LARAVEL_DSN`

A DSN isn't strictly a secret — it grants only "send events to this project," not read access — but treat it as one anyway. To rotate:

1. Sentry Project Settings → Client Keys (DSN) → **Generate new key** then **Disable old**.
2. Update `.env`, `php artisan config:clear`, restart workers.

---

## What "history scrub" would still buy you (and what it costs)

After all of the above:
- The current `master` and all future commits contain zero secrets.
- The git history still contains every past `.env` value (from before today's untrack).

If `git filter-repo --path system/.env --invert-paths` is run:
- The history values disappear forever.
- This is a **destructive rewrite** — every existing clone is now incompatible. Anyone with an outstanding clone will need to re-clone.
- Force-push is required.

Run it only if:
- The repo will be made public.
- Outside contractors get added.
- Compliance / regulatory pressure demands clean history.

If you don't, rotation alone is enough — the leaked history values are now invalid credentials that don't authenticate anywhere.

---

## Tracking sheet

Stamp each row as you complete it.

| Secret | Provider | Status | Rotated on | Verified by |
|--------|----------|--------|-----------|-------------|
| APP_KEY | local | ✅ rotated | 2026-06-07 | `phpunit` green |
| DB_PASSWORD | local MySQL | ✅ rotated | 2026-06-07 | `phpunit` green |
| MAIL_PASSWORD | Mailtrap | N/A — local mailer set to `log` (2026-06-08) | — | dispatched test mail lands in storage/logs/laravel.log |
| SHIPSGO_API_KEY | ShipsGo | ✅ rotated | 2026-06-08 | API probe → `404 NOT_FOUND` (auth OK) |
| SHIPSGO_WEBHOOK_SECRET | ShipsGo | pending (deploy-time) | — | replay webhook from dashboard |
| AUDIT_ADMIN_PASSWORD | local MySQL | not yet needed | — | requires production deploy first |
| FCM service-account JSON | Firebase | pending (deploy-time) | — | send test push from `notifications:test` |
| SENTRY_LARAVEL_DSN | Sentry | pending (deploy-time) | — | deliberate test exception lands in Sentry |
