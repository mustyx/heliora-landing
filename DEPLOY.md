# Heliora Consulting — Deployment Guide

## Step 1: Buy Namecheap Shared Hosting

1. Go to namecheap.com → **Hosting** → **Shared Hosting**
2. Choose **Stellar** (~$2.98/mo billed annually) — it includes:
   - Free SSL, PHP 8+, MySQL, unlimited email
3. During checkout, select **helioraconsulting.com** as the primary domain

---

## Step 2: Set Up MySQL Database (cPanel)

1. Log into **cPanel** (Namecheap dashboard → cPanel)
2. Go to **MySQL Databases**
3. Create database: `username_heliora_leads`
4. Create user: `username_heliora_user` with a strong password
5. Add user to database with **ALL PRIVILEGES**
6. Open **phpMyAdmin**, select your database
7. Click **Import** → upload `database.sql` from this project
   → All 4 tables are created automatically

---

## Step 3: Configure `config/config.php`

Update these values to match your Namecheap setup:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpanelusername_heliora_leads');  // cPanel prefix!
define('DB_USER', 'cpanelusername_heliora_user');
define('DB_PASS', 'your_db_password');

define('SMTP_HOST', 'mail.helioraconsulting.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'info@helioraconsulting.com');
define('SMTP_PASS', 'your_email_password');

define('ADMIN_EMAIL', 'info@helioraconsulting.com');
define('ADMIN_USER',  'your_admin_username');
define('ADMIN_PASS',  'your_admin_password');
```

---

## Step 4: Set Up Email (Namecheap Private Email)

1. cPanel → **Email Accounts** → Create `info@helioraconsulting.com`
2. Use these SMTP settings in `config.php`:
   - Host: `mail.helioraconsulting.com`
   - Port: `587` (TLS) or `465` (SSL)
   - User: `info@helioraconsulting.com`
   - Pass: your email password

---

## Step 5: Upload Files via cPanel File Manager

1. cPanel → **File Manager** → navigate to `public_html/`
2. Delete the default `index.html` if present
3. Upload ALL files from this project folder
4. **Do NOT upload** `.claude/` folder
5. Ensure folder structure:
   ```
   public_html/
   ├── index.html
   ├── submit-lead.php
   ├── database.sql       ← can delete after import
   ├── assets/
   ├── config/
   ├── includes/
   └── admin/
   ```

---

## Step 6: Enable SSL

1. cPanel → **SSL/TLS** → **AutoSSL** → Run AutoSSL
2. Or: cPanel → **AutoSSL** (Namecheap provides free PositiveSSL)
3. Update `config.php`: `define('APP_URL', 'https://helioraconsulting.com');`

---

## Step 7: Set Up Google Analytics 4

1. Go to analytics.google.com → Create property
2. Set up a **Web** data stream for `helioraconsulting.com`
3. Copy your **Measurement ID** (format: `G-XXXXXXXXXX`)
4. In `index.html`, replace both instances of `GA_MEASUREMENT_ID`:
   ```html
   <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
   ...
   gtag('config', 'G-XXXXXXXXXX');
   ```

---

## Step 8: Connect Zoho CRM (Optional but Recommended)

1. Sign up at **zoho.com/crm** (free up to 3 users)
2. Go to **api-console.zoho.com** → Create a **Self Client**
3. Generate a grant token with scope:
   ```
   ZohoCRM.modules.leads.CREATE,ZohoCRM.modules.leads.READ
   ```
4. Exchange for refresh token (one-time, via Postman or curl):
   ```
   POST https://accounts.zoho.com/oauth/v2/token
   grant_type=authorization_code
   client_id=YOUR_ID
   client_secret=YOUR_SECRET
   redirect_uri=https://helioraconsulting.com
   code=YOUR_GRANT_TOKEN
   ```
5. Copy the `refresh_token` from the response
6. In `config.php`:
   ```php
   define('ZOHO_CLIENT_ID',     'your_client_id');
   define('ZOHO_CLIENT_SECRET', 'your_client_secret');
   define('ZOHO_REFRESH_TOKEN', 'your_refresh_token');
   define('ZOHO_ENABLED',       true);
   ```
7. In Zoho CRM, add custom fields to Leads module:
   - `Service_Interest__c` (Text)
   - `Project_Budget__c` (Text)
   - `UTM_Source__c` (Text)
   - `UTM_Medium__c` (Text)
   - `UTM_Campaign__c` (Text)
   - `Website_Source__c` (URL)

---

## Step 9: Secure Admin Panel

Add a `.htaccess` inside `admin/` to restrict by IP:

```apache
# admin/.htaccess
AuthType Basic
AuthName "Restricted"
AuthUserFile /home/username/.htpasswds/admin
Require valid-user

# Optionally restrict to your IP:
# Order deny,allow
# Deny from all
# Allow from YOUR.IP.ADDRESS
```

Or generate an `.htpasswd` via cPanel → **Password Protect Directories**.

---

## Step 10: Update Content

Before going live, update in `index.html`:
- Phone number (search `+1 (234) 567-890`)
- Real testimonial (James Mensah is a placeholder)
- Real case study details
- LinkedIn URL in footer

---

## Security Checklist

- [ ] Change `ADMIN_PASS` from default
- [ ] Change `CSRF_SECRET` to a random 32-char string
- [ ] Set `APP_ENV` to `production`
- [ ] Ensure `config/config.php` is not publicly accessible
  - Add to `config/.htaccess`: `Deny from all`
- [ ] Enable HTTPS redirect in cPanel
- [ ] Set up daily MySQL backups in cPanel → **Backup Wizard**

---

## Cost Summary (Zero Monthly Platform Fees)

| Item | Cost |
|------|------|
| Namecheap Shared Hosting (Stellar) | ~$35/yr |
| helioraconsulting.com domain | Already owned |
| Namecheap Private Email | Already owned |
| Google Analytics 4 | Free |
| Zoho CRM (up to 3 users) | Free |
| SSL Certificate | Free (AutoSSL) |
| **Total ongoing** | **~$35/yr** |

---

## Lead Gen Campaign Setup (Google / Meta Ads)

When running ads, append UTM parameters to your URL:

```
https://helioraconsulting.com?utm_source=google&utm_medium=cpc&utm_campaign=structural-engineering
```

All UTM data is automatically captured and stored with each lead, and synced to Zoho CRM for attribution analysis.
