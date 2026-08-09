# Heliora Consulting — Deployment Guide

> ## Migrations run BEFORE the code that needs them
>
> The site is already live, so this is now the rule that matters most on every
> deploy that touches the `leads` table.
>
> `submit-lead.php` INSERTs a fixed column list. If the code ships before the
> migration, **every** submission throws a PDOException and the visitor sees
> "An error occurred" — leads are lost outright, and paid clicks are lost with
> them. Adding columns first is harmless: the old code simply ignores them.
>
> ```
> 1. phpMyAdmin → run the migration → confirm with SHOW COLUMNS
> 2. git push origin main   (or push-live.bat)
> 3. Submit one real test lead and confirm it lands
> ```
>
> Pending migrations live in `migrations/`, named by date. Currently:
> `2026-08-09-tracking.sql`, `2026-08-09-qualification-fields.sql`.

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
7. Click **Import** → upload **`db-schema.sql`** — NOT `database.sql`

   > **`database.sql` is stale and will build the wrong table.** It predates
   > the `project_scale` and `client_type` fields and omits both, so a form
   > submission against it fails on INSERT. It is kept only because the live
   > table's `zoho_lead_id` / `zoho_synced_at` columns trace back to it.
   >
   > Truth = `db-schema.sql` + every file in `migrations/`, applied in date
   > order. Run the migrations after the import.

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
   The scopes actually needed are wider than the two above — field metadata
   and layouts are required if you ever recreate the custom fields:
   ```
   ZohoCRM.modules.leads.CREATE,ZohoCRM.modules.leads.READ,
   ZohoCRM.settings.fields.READ,ZohoCRM.settings.layouts.READ
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

6. **Paste them into the SERVER copy of `config/config.php`.**

   `config/config.php` is listed in `.gitignore` and is not tracked, so the
   server holds the only real copy and a deploy never overwrites it. This is
   the same place the `META_CAPI_TOKEN` already lives. Secrets stay out of git
   because the file is out of git — no env vars involved.

   cPanel → **File Manager** → `public_html/config/config.php` → Edit, and
   replace the empty fallbacks:
   ```php
   define('ZOHO_CLIENT_ID',     getenv('ZOHO_CLIENT_ID')     ?: 'your_client_id');
   define('ZOHO_CLIENT_SECRET', getenv('ZOHO_CLIENT_SECRET') ?: 'your_client_secret');
   define('ZOHO_REFRESH_TOKEN', getenv('ZOHO_REFRESH_TOKEN') ?: 'your_refresh_token');
   define('ZOHO_ENABLED',       true);   // ← was (bool)(getenv(...) ?: false)
   ```
   Leave `ZOHO_API_DOMAIN` as `https://www.zohoapis.com` — correct for a
   `.com` account. Only change it if your CRM URL is `zoho.eu` / `zoho.in`,
   in which case use `zohoapis.eu` / `zohoapis.in` AND the matching
   `ZOHO_ACCOUNTS_URL`. A region mismatch fails with INVALID_TOKEN and looks
   exactly like a bad credential, so check the domain first if that happens.

   Until `ZOHO_ENABLED` is true, `pushLeadToZoho()` returns `null` on its
   first line. Leads still save to MySQL and still send email — they just
   never reach the CRM, silently.

   > **Do NOT use `SetEnv` in `.htaccess` for this.** Namecheap runs
   > LiteSpeed, which does not implement Apache's `SetEnv` for PHP, so the
   > values would silently never arrive.

7. Custom fields on the Leads module — **already created, 9 Aug 2026.**
   Nothing to do here. Do NOT recreate them.

   The list that used to sit here was wrong in two ways, and is kept below
   only so the mistake is recognisable if it resurfaces:

   - It used a `__c` suffix (`Service_Interest__c`). That is **Salesforce**
     convention. Zoho uses a plain api_name, so none of those fields ever
     existed and every custom value sent to them was discarded.
   - It listed `Project_Budget__c`. There is deliberately no budget field —
     Section 03 of the campaign strategy defers one until conversion volume
     is known.

   The 19 fields that now exist, verified by reading them back from the API:

   | api_name | type |
   |---|---|
   | `Client_Type`, `Project_Scale`, `Project_Stage` | picklist |
   | `Decision_Horizon`, `Decision_Authority` | picklist |
   | `Qualification_Asked` | picklist (Yes/No) |
   | `Qualification_Score` | integer(3) |
   | `Service_Interest` | text(50) |
   | `Lead_UID` | text(32) |
   | `Consent_State` | text(20) |
   | `UTM_Source`, `UTM_Medium` | text(100) |
   | `UTM_Campaign` | text(150) |
   | `UTM_Content` | text(255) |
   | `Meta_Campaign_ID`, `Meta_AdSet_ID`, `Meta_Ad_ID` | text(64) |
   | `Ad_Placement` | text(100) |
   | `Website_Source` | textarea(2000) |

   Two names are not the obvious choice because Zoho reserves them:
   `Lead Score` → `Qualification_Score`, and `Placement` → `Ad_Placement`.
   `Website_Source` is a textarea rather than text because a real landing URL
   with a full UTM set plus `fbclid` exceeds 255 characters.

   Picklist values are capped at 25 characters by Zoho, so the CRM labels are
   shorter than the form labels. The exact strings live in the `map*()`
   functions in `includes/zoho.php` — if you change one, change the other in
   the same commit, or Zoho will reject the record.

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
