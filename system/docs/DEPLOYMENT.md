# ShipFlow — Production Deployment Checklist

Step-by-step from "I have a fresh Ubuntu 24.04 VPS" to "real users are touching the system." Every step has the exact command. Estimated total time: 2–4 hours including DNS propagation.

The reference target is **Ubuntu 24.04 LTS** on a 2-vCPU / 4 GB RAM VPS with at least 40 GB disk. Adjust commands for your distro if you're not on Ubuntu.

---

## 0) Prerequisites

You should have ready before you start:

- A domain name with DNS access (e.g. Cloudflare).
- A VPS with SSH access (root or a sudo user).
- The repository pushed to a remote you can `git clone` from (https://github.com/YounusMo/ship).
- A Firebase project + service-account JSON if you want push notifications.
- A ShipsGo account if you want carrier tracking.
- An SMTP relay (Mailgun, Postmark, SES, or similar) for password reset + proforma emails.

---

## 1) Provision the VPS

```bash
# As root on a fresh host
ssh root@<vps-ip>

# Create a deploy user
adduser --disabled-password --gecos "" deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Lock down root SSH
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh

# Reconnect as deploy
exit
ssh deploy@<vps-ip>
```

---

## 2) Install system packages

```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.3 + extensions
sudo apt install -y software-properties-common ca-certificates lsb-release apt-transport-https
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-gd php8.3-intl php8.3-zip \
  php8.3-bcmath php8.3-readline

# MySQL 8
sudo apt install -y mysql-server

# Nginx
sudo apt install -y nginx

# Composer
sudo curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Supervisor (for queue worker)
sudo apt install -y supervisor

# Git
sudo apt install -y git

# Useful: ufw, fail2ban, certbot
sudo apt install -y ufw fail2ban certbot python3-certbot-nginx
```

---

## 3) Firewall

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

---

## 4) MySQL setup

```bash
sudo mysql_secure_installation
# answer: y (validate password), 2 (strong), y, y, y, y

# Create app database + users
sudo mysql <<'SQL'
CREATE DATABASE ship_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Main app user — full rights EXCEPT delete/update on audit_log.
CREATE USER 'ship_user'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD_1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, REFERENCES,
      CREATE TEMPORARY TABLES, EXECUTE, LOCK TABLES, TRIGGER
      ON ship_system.* TO 'ship_user'@'localhost';

-- Audit-immutability split (gap #10). The main user CANNOT delete or
-- update audit_log; a privileged separate user can.
REVOKE DELETE, UPDATE ON ship_system.audit_log FROM 'ship_user'@'localhost';

CREATE USER 'ship_audit_admin'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD_2';
GRANT SELECT, INSERT, DELETE ON ship_system.audit_log TO 'ship_audit_admin'@'localhost';

FLUSH PRIVILEGES;
SQL
```

Verify:

```bash
mysql -u ship_user -p ship_system -e "SHOW GRANTS FOR CURRENT_USER();"
mysql -u ship_audit_admin -p ship_system -e "SHOW GRANTS FOR CURRENT_USER();"
```

---

## 5) Clone the code

```bash
sudo mkdir -p /var/www
sudo chown deploy:deploy /var/www
cd /var/www
git clone https://github.com/YounusMo/ship.git shipflow
cd shipflow/system
```

---

## 6) Install PHP dependencies

```bash
cd /var/www/shipflow/system
composer install --no-dev --optimize-autoloader --no-progress
```

---

## 7) Load the schema baseline

The legacy schema isn't fully expressible as Laravel migrations — see `database/schema/baseline.sql`.

```bash
mysql -u ship_user -p ship_system < database/schema/baseline.sql

# Apply anything the baseline didn't include
php artisan migrate --force
```

---

## 8) Configure `.env`

```bash
cp .env.example .env
php artisan key:generate --force
nano .env
```

Set these to real values:

```ini
APP_NAME="Ship Flow"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com         # ← your domain

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ship_system
DB_USERNAME=ship_user
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD_1

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# SMTP — fill from your relay provider
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.example.com
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="Ship Flow"

# Sanctum (defaults are fine; override here if you want different TTLs)
SANCTUM_EXPIRATION_MINUTES=43200       # 30 days for client tokens

# Audit immutability — flip on after the GRANT/REVOKE in step 4
AUDIT_ARCHIVE_CONNECTION=audit_admin
AUDIT_ADMIN_HOST=127.0.0.1
AUDIT_ADMIN_USER=ship_audit_admin
AUDIT_ADMIN_PASSWORD=CHANGE_ME_STRONG_PASSWORD_2

# Sentry (optional — leave blank to disable)
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.05
SENTRY_PROFILES_SAMPLE_RATE=0.05
SENTRY_ENVIRONMENT=production

# ShipsGo (optional — for carrier tracking)
SHIPSGO_API_KEY=
SHIPSGO_WEBHOOK_SECRET=

# Firebase Cloud Messaging (optional — for mobile push)
FCM_PROJECT_ID=
FCM_CREDENTIALS_PATH=/var/www/shipflow/system/storage/app/private/fcm.json
```

```bash
# Drop the FCM service-account JSON in place
sudo nano /var/www/shipflow/system/storage/app/private/fcm.json
sudo chown deploy:www-data /var/www/shipflow/system/storage/app/private/fcm.json
sudo chmod 640 /var/www/shipflow/system/storage/app/private/fcm.json
```

---

## 9) Permissions + cache warmup

```bash
cd /var/www/shipflow/system
sudo chown -R deploy:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 10) Nginx vhost

```bash
sudo nano /etc/nginx/sites-available/shipflow
```

```nginx
server {
    listen 80;
    server_name app.example.com;       # ← your domain
    root /var/www/shipflow/system/public;
    index index.php;

    # Cloudflare real-IP restore (skip if not behind CF)
    set_real_ip_from 173.245.48.0/20;
    set_real_ip_from 103.21.244.0/22;
    set_real_ip_from 103.22.200.0/22;
    set_real_ip_from 103.31.4.0/22;
    set_real_ip_from 141.101.64.0/18;
    set_real_ip_from 108.162.192.0/18;
    set_real_ip_from 190.93.240.0/20;
    set_real_ip_from 188.114.96.0/20;
    set_real_ip_from 197.234.240.0/22;
    set_real_ip_from 198.41.128.0/17;
    set_real_ip_from 162.158.0.0/15;
    set_real_ip_from 104.16.0.0/13;
    set_real_ip_from 104.24.0.0/14;
    set_real_ip_from 172.64.0.0/13;
    set_real_ip_from 131.0.72.0/22;
    real_ip_header CF-Connecting-IP;

    # Tight upload limits — clients only upload via sourcing
    client_max_body_size 16M;

    # Hide PHP errors
    fastcgi_hide_header X-Powered-By;
    add_header X-Powered-By "" always;

    # Block hidden files
    location ~ /\. {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 120;
    }

    # Static asset caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/shipflow /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

---

## 11) DNS + TLS

Point your domain at the VPS:

```
A   app.example.com   <vps-ip>
```

Wait for propagation (`dig app.example.com` returns the IP), then:

```bash
sudo certbot --nginx -d app.example.com --agree-tos --no-eff-email -m ops@example.com
```

certbot rewrites the nginx vhost to handle 80→443 redirect + cert renewal. Verify:

```bash
curl -I https://app.example.com/up
# Expect HTTP/2 200
```

If behind Cloudflare, set SSL/TLS mode to **Full (strict)** in the Cloudflare dashboard.

---

## 12) Supervisor for queue worker

```bash
sudo nano /etc/supervisor/conf.d/shipflow-worker.conf
```

```ini
[program:shipflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/shipflow/system/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/shipflow/worker.log
stopwaitsecs=3600
```

```bash
sudo mkdir -p /var/log/shipflow
sudo chown deploy:deploy /var/log/shipflow

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start shipflow-worker:*
sudo supervisorctl status
```

---

## 13) Cron for scheduler

```bash
sudo crontab -u deploy -e
```

Add:

```
* * * * * cd /var/www/shipflow/system && php artisan schedule:run >> /var/log/shipflow/schedule.log 2>&1
```

Verify it's registered:

```bash
sudo crontab -u deploy -l
```

---

## 14) Create the first admin user

```bash
cd /var/www/shipflow/system
php artisan tinker --execute="
\$u = new \App\Models\User(['name' => 'Founder', 'email' => 'founder@example.com', 'password' => bcrypt('CHANGE_ME_NOW'), 'type' => 'admin', 'code' => '1']);
\$u->save();
echo 'Admin id ' . \$u->id . ' created.';
"
```

Then log in at `https://app.example.com/login`, immediately:
1. Reset your password via the UI to something only you know.
2. Visit `/two-factor/enroll` and enable 2FA.
3. Create the rest of your staff users from `/users`.
4. Seed branches from the admin UI or via tinker.

---

## 15) Smoke-test the whole stack

```bash
# 1. Health endpoint
curl -I https://app.example.com/up
# Expect 200

# 2. Login page
curl -L https://app.example.com/ | grep -i "ship flow"
# Expect HTML with the company name

# 3. Queue worker is alive
sudo supervisorctl status shipflow-worker:*
# Expect RUNNING

# 4. Scheduler is registered
sudo crontab -u deploy -l | grep schedule:run
# Expect the line

# 5. Sanctum migrations applied (check version)
mysql -uship_user -p ship_system -e "SELECT * FROM migrations ORDER BY id DESC LIMIT 5;"
# Expect 2026_06_07_144808_add_two_factor_to_users_table

# 6. Trial balance balances
php artisan tinker --execute="
echo \DB::table('journal_lines')->selectRaw('currency, SUM(dr) - SUM(cr) AS diff')->groupBy('currency')->get()->toJson();
"
# Expect every currency's diff to be 0
```

If everything green, the system is live.

---

## 16) Hardening (do this within a week)

- [ ] **Backups**: nightly `mysqldump` to off-site bucket (S3, B2).
- [ ] **Backup encryption**: `gpg --symmetric --cipher-algo AES256` on dumps before upload.
- [ ] **fail2ban**: configure jails for nginx 401/403 spikes and ssh auth failures.
- [ ] **Patching**: enable unattended-upgrades for security patches.
- [ ] **Monitoring**: Sentry DSN provisioned, pointed at the production project.
- [ ] **Uptime check**: UptimeRobot / Better Uptime hitting `/up` every minute, alert to PagerDuty / Slack.
- [ ] **DB grant audit**: re-confirm `SHOW GRANTS FOR 'ship_user'@'localhost'` doesn't include DELETE on `audit_log`.
- [ ] **TLS check**: `sslyze app.example.com` — confirm TLS 1.2+ only, strong ciphers, HSTS preload.
- [ ] **CSP review**: tighten the permissive CSP in `app/Http/Middleware/SecurityHeaders.php` once you know what scripts/styles the legacy Blade views actually load.

---

## 17) Deploy updates (after the initial bring-up)

```bash
# As deploy user, from inside the repo
cd /var/www/shipflow/system

# Pull
git pull origin master

# Install any new deps (only if composer.lock changed)
composer install --no-dev --optimize-autoloader

# If new migrations: load any baseline updates first, then migrate
mysql -u ship_user -p ship_system < database/schema/baseline.sql  # only on first deploy of a schema-dump bump
php artisan migrate --force

# Refresh caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart the worker so it picks up new code
sudo supervisorctl restart shipflow-worker:*

# Reload nginx (only if vhost changed)
sudo nginx -t && sudo systemctl reload nginx
```

For zero-downtime deploys, set up [Deployer](https://deployer.org/) or a similar tool that uses symlinks + atomic releases. That's out of scope for the initial deployment.

---

## Troubleshooting

**`502 Bad Gateway`**: PHP-FPM isn't running or socket path mismatch. Check `sudo systemctl status php8.3-fpm` and the `fastcgi_pass` line in the vhost.

**`419 Page Expired` on every form**: `APP_URL` doesn't match the host the browser used. Check `.env`.

**`SQLSTATE[42S22] Unknown column 'two_factor_secret'`**: the 2FA migration hasn't run. `php artisan migrate --force`.

**Push notifications don't arrive**: queue worker isn't running, or FCM credentials are wrong. `sudo supervisorctl status` and check `/var/log/shipflow/worker.log`.

**Audit log writes work but archive command fails with permission denied**: `AUDIT_ARCHIVE_CONNECTION` is set to `audit_admin` but the privileged DB user doesn't exist. Re-run the GRANT in step 4.

**Tests fail on this host**: tests are not meant to run on production. Use a separate staging host.
