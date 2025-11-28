NETLIFY (Frontend) + RAILWAY (PHP Backend) Deployment Guide
============================================================

Architecture Overview:
- Railway: Hosts PHP 8+ app + MySQL database (backend at https://your-app.railway.app)
- Netlify: Proxy all requests to Railway backend (frontend at your custom domain)
- Result: Seamless user experience; single domain/API

Step 1: Deploy Backend to Railway
==================================

1.1 Create Railway Account & Project
- Go to railway.app
- Sign up (free account available)
- Create new project

1.2 Connect GitHub Repository
- In Railway dashboard, click "New Project"
- Select "Deploy from GitHub"
- Authorize GitHub access
- Select the ejustice_portal repository

1.3 Add MySQL Database Service
- In your Railway project, click "Add Service"
- Search for "MySQL"
- Select MySQL (latest version)
- Railway auto-creates the database and injects DATABASE_URL env var

1.4 Configure Environment Variables
- In Railway project settings, go to "Variables" tab
- Add these environment variables:
  
  DOC_ENC_KEY: (run: openssl rand -hex 32 and paste the output)
  APP_ENV: production
  APP_DEBUG: false

- DATABASE_URL will be auto-injected by Railway (no action needed)

1.5 Deploy
- Railway auto-deploys from GitHub on commit
- Or manually trigger: Railway dashboard > "Trigger Deploy"
- Wait 2-5 minutes for deployment to complete
- Once done, Railway shows your app URL (e.g., https://ejustice-portal-prod.railway.app)

1.6 Run Database Migrations on Railway
- Open Railway terminal (if available) or SSH into the app
- Or create a simple script to run migrations on first deploy:
  - Create file: `scripts/migrate.php`
  - Include and run the SQL imports from the app
  - Call this script in your `index.php` or via a deploy hook

Alternative: Use phpMyAdmin or MySQL CLI to import SQL files:
```bash
mysql -h [railway-db-host] -u [user] -p[password] ejustice_portal < sql/ejustice_portal.sql
mysql -h [railway-db-host] -u [user] -p[password] ejustice_portal < sql/002_add_audit_logs.sql
mysql -h [railway-db-host] -u [user] -p[password] ejustice_portal < sql/003_add_barangay_module.sql
mysql -h [railway-db-host] -u [user] -p[password] ejustice_portal < sql/004_add_barangay_case_routing.sql
```

1.7 Seed Demo Users (Optional)
- Make a request to: https://[your-railway-url]/public/seed_demo_users.php
- Creates demo accounts for testing

1.8 Test Backend
- Visit: https://[your-railway-url]/public/login.php
- Verify database and app are working
- Test login with demo account

---

Step 2: Deploy Frontend Proxy to Netlify
=========================================

2.1 Create Netlify Account
- Go to netlify.com
- Sign up (free account available)

2.2 Connect Repository
- In Netlify dashboard, click "Add new site"
- Select "Import an existing project"
- Connect GitHub
- Select the ejustice_portal repository

2.3 Configure Build Settings
- Build command: (leave empty)
- Publish directory: public
- Click "Deploy site"

2.4 Add Proxy Redirect (Important!)
- In the repo root, file `netlify.toml` already includes redirect rules
- Edit `netlify.toml`:
  - Replace `RAILWAY_BACKEND_URL` with your actual Railway URL
  - Example: `https://ejustice-portal-prod.railway.app`
- Also check `public/_redirects`:
  - Replace `RAILWAY_BACKEND_URL` with your Railway URL

2.5 Custom Domain (Optional but Recommended)
- In Netlify dashboard, go to "Site settings" > "Domain management"
- Click "Add custom domain"
- Point your DNS to Netlify (instructions provided)
- Netlify auto-provisions SSL via Let's Encrypt

2.6 Verify Proxy Works
- After deployment, visit your Netlify URL (or custom domain)
- Requests should automatically route to Railway backend
- Test:
  - Login page loads
  - Login works
  - File upload works
  - Barangay dashboard accessible
  - Escalation creates police case

---

Step 3: Environment Variables & Secrets
========================================

3.1 On Railway
- Database credentials: Auto-injected (DATABASE_URL)
- DOC_ENC_KEY: Must be set manually (generate via `openssl rand -hex 32`)
- APP_ENV: Set to "production"

3.2 On Netlify
- No secrets needed on Netlify (it's just proxying to Railway)
- Netlify redirects all requests to Railway backend

3.3 .env File (Local Development Only)
- Copy `.env.example` to `.env` in your local environment
- Fill in your local MySQL credentials
- DO NOT commit .env to GitHub

---

Step 4: Document Storage
=========================

Option A: Local Storage (Recommended for Small Deployments)
- Files stored in `storage/documents/` on Railway server
- Encrypted with DOC_ENC_KEY before storage
- Railway file system is ephemeral; if app restarts, files persist (for a while)
- Limitation: If Railway container restarts, files may be lost (unless using Railway persistent volumes)

Option B: S3 Storage (Recommended for Production)
- Upload encrypted files to AWS S3 or compatible service
- Requires API keys and bucket configuration
- More reliable and scalable
- I can implement this if needed

Recommendation: Start with local storage (Option A). If you deploy to production with high volume, migrate to S3.

---

Step 5: Monitoring & Maintenance
=================================

5.1 Monitor Railway App
- Railway dashboard shows logs, CPU, memory
- Set up alerts for errors or downtime

5.2 Backup Database
- Railway provides automated backups (Pro plan)
- Or manually export MySQL dump:
  ```bash
  mysqldump -h [host] -u [user] -p[password] ejustice_portal > backup.sql
  ```

5.3 Scale
- If traffic increases, Railway auto-scales (Pro plan)
- Netlify CDN automatically scales static content

---

Troubleshooting
===============

Issue: "Database connection failed"
- Check DATABASE_URL env var on Railway (go to Variables tab)
- Ensure MySQL service is running in Railway project
- Verify migrations were imported

Issue: "Redirects not working; getting 404"
- Check netlify.toml: `RAILWAY_BACKEND_URL` placeholder replaced with actual URL
- Check `public/_redirects`: Same replacement
- Redeploy Netlify after changes (git push or manual trigger)

Issue: "SSL certificate error"
- Netlify and Railway both provide free SSL (Let's Encrypt)
- Should work automatically; wait a few minutes after domain setup
- If persists, manually request cert in respective dashboards

Issue: "File uploads failing"
- Check that `storage/documents/` exists and is writable on Railway
- Ensure DOC_ENC_KEY env var is set (correct length, no typos)
- Check Railway logs for PHP errors

Issue: "Login not persisting across page reloads"
- Check that sessions are working (PHP session cookie)
- Ensure cookies are not blocked by CORS or security headers
- Verify HTTPS is enabled (required for secure cookies)

---

Cost Estimation (as of Nov 2025)
=================================

Railway:
- Free tier: ~$5 credit/month (enough for dev/testing)
- Pro: Pay-as-you-go ($0.50/GB RAM/month, ~$50/month typical production)
- MySQL database: Included in Railway usage

Netlify:
- Free tier: Perfect for this use case (static proxy)
- Pro: $20/month (more build minutes, not needed here)

Total: ~$0-50/month depending on scale

---

Deployment Checklist
====================

[ ] Generate DOC_ENC_KEY: openssl rand -hex 32
[ ] Create Railway project
[ ] Connect GitHub repo to Railway
[ ] Add MySQL service to Railway
[ ] Set env vars on Railway (DOC_ENC_KEY, APP_ENV, APP_DEBUG)
[ ] Deploy Railway app
[ ] Import SQL migrations to Railway MySQL
[ ] Test Railway backend: https://[railway-url]/public/login.php
[ ] Note Railway URL
[ ] Create Netlify site
[ ] Connect GitHub repo to Netlify
[ ] Set Publish directory to "public" in Netlify
[ ] Update netlify.toml: Replace RAILWAY_BACKEND_URL with actual URL
[ ] Update public/_redirects: Replace RAILWAY_BACKEND_URL with actual URL
[ ] Commit and push changes (auto-redeploy on both services)
[ ] Test Netlify proxy: Visit Netlify URL, verify login works
[ ] (Optional) Add custom domain to Netlify and configure DNS
[ ] Full end-to-end test:
    [ ] Login as different roles
    [ ] File case online (complainant)
    [ ] View in Barangay dashboard
    [ ] Add mediation effort
    [ ] Generate CFA and escalate
    [ ] Verify case appears in Police Blotter
    [ ] Upload and decrypt document
    [ ] Check audit logs

---

Next Steps
==========

1. Generate secure DOC_ENC_KEY and create Railway project (5 minutes)
2. Deploy backend to Railway (5-10 minutes)
3. Import database migrations to Railway MySQL (5 minutes)
4. Create Netlify site and configure redirects (5 minutes)
5. Test end-to-end (10-15 minutes)

Total: ~30-45 minutes from start to production!

Questions or issues? Each service has excellent support:
- Railway: docs.railway.app & Discord community
- Netlify: docs.netlify.com & live chat support

---
Last Updated: November 28, 2025
