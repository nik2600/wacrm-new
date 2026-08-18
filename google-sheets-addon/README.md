# Wasnap WhatsApp Shop — Google Sheets Add-on

This folder is the **Apps Script** project for the Wasnap WhatsApp Shop
add-on. It is NOT part of the Laravel application — it lives in a
separate Google Cloud project and is published to the Workspace
Marketplace as a Workspace add-on.

## What's here

| File | Purpose |
|---|---|
| `appsscript.json` | Manifest — OAuth scopes, add-on triggers, runtime version |
| `Code.gs` | Server-side Apps Script — sheet reading, API client, lifecycle hooks |
| `Sidebar.html` | The "Open sidebar" UI rendered inside Sheets |
| `README.md` | This file |

## Upload to Google (one-time)

Two paths — pick one.

### Path A: Manual upload via script.google.com

1. Go to <https://script.google.com> → **New project**
2. Rename "Untitled project" → **Wasnap WhatsApp Shop**
3. **Project Settings** (gear icon) → tick "Show appsscript.json manifest file in editor"
4. Editor → paste `appsscript.json`, `Code.gs`, and create a new HTML file `Sidebar` with the contents of `Sidebar.html`
5. Save (Ctrl+S)
6. **Deploy** → **Test deployments** → **Install** — this lets you test in your own Google account before submitting to the Marketplace

### Path B: clasp CLI (recommended if you'll iterate)

```bash
npm install -g @google/clasp
clasp login
cd google-sheets-addon
clasp create --type sheets --title "Wasnap WhatsApp Shop"
clasp push
clasp open
```

## Switch the backend host

`Code.gs` line 13:

```js
const WASNAP_BASE = 'https://wasnap.app';
```

Change to your staging/local URL during development:

```js
const WASNAP_BASE = 'https://staging.wasnap.app';
```

⚠ Local IPs like `192.168.1.189:8008` won't work from Apps Script —
Google's URL Fetch service can only call public HTTPS endpoints.
Use ngrok or similar to expose your local Laravel:

```bash
ngrok http 8008
# then set WASNAP_BASE = 'https://abc123.ngrok.app'
```

## Test it

1. Set your `WASNAP_BASE` to a reachable Wasnap instance
2. Generate an API key on Wasnap → `/account` → "Generate Sheets API key"
3. In Apps Script editor: **Run → onOpen** (will prompt for OAuth consent the first time)
4. Open any Google Sheet with these headers:
   ```
   Product Name | Category | Description | Image URL | Price | SKU | Stock | Active
   ```
5. Sheet menu: **Extensions → Wasnap WhatsApp Shop → Open sidebar**
6. Paste your API key, click **Save**, then **Sync to Wasnap**
7. Result panel shows the public shop URL + Share button

## Publish to Workspace Marketplace

Once it's working end-to-end:

1. **Cloud Console** → enable the project on Google Cloud Console (you'll be prompted in Apps Script)
2. Fill in **OAuth consent screen** — production, with these scopes:
   - `https://www.googleapis.com/auth/spreadsheets.currentonly` (NOT the full `spreadsheets` scope — WATI got blocked for over-requesting)
   - `https://www.googleapis.com/auth/script.container.ui`
   - `https://www.googleapis.com/auth/script.external_request`
3. **Workspace Marketplace SDK** in the same Cloud project → fill in listing details
4. **Required assets**:
   - 1 logo, 72×72 PNG
   - 1 logo, 220×140 PNG (banner)
   - 4 screenshots, 1280×800 PNG
   - 30-second screencast (mp4 or YouTube link)
   - Privacy policy URL (`https://wasnap.app/legal/privacy`)
   - Terms of service URL (`https://wasnap.app/legal/terms`)
5. **Submit for review** — Google typically responds in 5–10 business days
6. The Marketplace listing URL goes into `config('services.sheets_addon.marketplace_url')` on the Laravel side so the `/sheets-addon` Step 1 button points at it

## Why we ask for minimum scopes

`spreadsheets.currentonly` only grants access to the active spreadsheet
the user has open — not "all of Drive", not "all sheets". This is what
distinguishes us from WATI (review #2 in `rev.md`: "Google has blocked
the Add On for Security reasons") which over-scoped.

If you ever need to add a scope, bump the manifest, **then** the OAuth
consent screen — Google review treats scope changes as a re-submission.

## Privacy policy (template)

Required for Marketplace listing. Minimum content:

> Wasnap WhatsApp Shop reads the active Google Sheet ONLY when the
> user clicks "Sync". The add-on does not read other sheets, does not
> read at scheduled intervals, and does not write back to the sheet.
> Sheet contents are transmitted to Wasnap (https://wasnap.app) over
> HTTPS using the user's personal API key. We retain the synced data
> only as long as the user's storefront exists on Wasnap. Deleting the
> Wasnap shop deletes the synced data. We do not sell or share the
> data with third parties.

Host this at `https://wasnap.app/legal/privacy-sheets-addon` and link
it from the Marketplace listing.
