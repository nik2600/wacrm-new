/**
 * Wasnap WhatsApp Shop — Google Sheets Editor Add-on
 *
 * Menu-driven flow (matches WATI's pattern):
 *
 *   Extensions → Wasnap WhatsApp Shop
 *     ├── Create shop          (opens product-review modal)
 *     ├── Update shop          (opens same modal in edit mode)
 *     ├── Settings · API key   (paste / rotate / revoke)
 *     └── Help
 *
 * Each menu item opens a modal. The modal is a single HTML file
 * (`Dialog.html`) with multiple "screens" that the client-side JS
 * navigates between — no page reloads, no second dialog hop.
 *
 * Auth: per-user "Sheets API key" generated at <wasnap>/account.
 * Stored in PropertiesService.userProperties so it's:
 *   • per-user (never leaks across collaborators on the same sheet)
 *   • encrypted at rest by Google
 *   • cleared when the user uninstalls the add-on
 */

// ─── Wasnap deployment URL ────────────────────────────────────────
// Pre-configured by the Wasnap server when you download Code.gs from
// /sheets-addon. Update manually if your deployment moves.
const WASNAP_BASE = 'https://wasnap.app';

const ENDPOINTS = {
  health: '/api/v1/sheets-addon/health',
  shops:  '/api/v1/sheets-addon/shops',
  sync:   '/api/v1/sheets-addon/sync',
};

const SHEET_HEADERS = [
  'Product Name', 'Category', 'Description',
  'Image URL', 'Price', 'SKU', 'Stock', 'Active'
];

const DEMO_ROWS = [
  ['Spring Tee',  'Apparel',     'Soft 100% cotton, fits true to size.',          'https://picsum.photos/seed/tee/400',  999,  'ST-001',  20, 'Y'],
  ['Cotton Tote', 'Accessories', 'Sturdy canvas tote · holds a laptop + groceries.', 'https://picsum.photos/seed/tote/400', 499,  'CT-002',  50, 'Y'],
  ['Ceramic Mug', 'Home',        'Hand-thrown stoneware · 350ml · dishwasher safe.',  'https://picsum.photos/seed/mug/400',  299,  'CM-003',  null, 'Y'],
];

// ─── Lifecycle ────────────────────────────────────────────────────
function onInstall(e) { onOpen(e); }
function onOpen(e) {
  // Add a submenu under Extensions. The label here is what appears
  // in the Sheet's Extensions → … menu after install.
  SpreadsheetApp.getUi().createAddonMenu()
    .addItem('Create shop',  'showCreateDialog')
    .addItem('Update shop',  'showUpdateDialog')
    .addSeparator()
    .addItem('Settings · API key', 'showSettingsDialog')
    .addItem('Help',         'showHelpDialog')
    .addToUi();
}

// ─── Menu actions ─────────────────────────────────────────────────
function showCreateDialog() { openWizard_('create'); }
function showUpdateDialog() { openWizard_('update'); }

function openWizard_(mode) {
  const t = HtmlService.createTemplateFromFile('Dialog');
  t.mode = mode;
  const html = t.evaluate()
    .setWidth(640)
    .setHeight(560)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);
  SpreadsheetApp.getUi().showModalDialog(html, 'WhatsApp Shop');
}

function showSettingsDialog() {
  const t = HtmlService.createTemplateFromFile('Settings');
  const html = t.evaluate()
    .setWidth(520)
    .setHeight(380);
  SpreadsheetApp.getUi().showModalDialog(html, 'API key');
}

function showHelpDialog() {
  const html = HtmlService.createHtmlOutputFromFile('Help')
    .setWidth(560)
    .setHeight(540);
  SpreadsheetApp.getUi().showModalDialog(html, 'Help');
}

// ─── API key storage ──────────────────────────────────────────────
function setApiKey(token) {
  token = (token || '').trim();
  if (!token.startsWith('wsn_live_')) {
    throw new Error('Invalid token. Expected to start with wsn_live_…');
  }
  PropertiesService.getUserProperties().setProperty('WSN_API_KEY', token);
  return { ok: true, suffix: token.slice(-8) };
}
function getApiKey() {
  return PropertiesService.getUserProperties().getProperty('WSN_API_KEY') || '';
}
function clearApiKey() {
  PropertiesService.getUserProperties().deleteProperty('WSN_API_KEY');
  return { ok: true };
}
function apiKeyStatus() {
  const k = getApiKey();
  return {
    has_key: !!k,
    suffix:  k ? k.slice(-8) : null,
    wasnap_base: WASNAP_BASE,
  };
}

// ─── API client ───────────────────────────────────────────────────
function apiFetch_(path, opts) {
  const token = getApiKey();
  if (!token) throw new Error('No API key set. Open "Settings · API key" first.');
  const res = UrlFetchApp.fetch(WASNAP_BASE + path, Object.assign({
    method: 'get',
    muteHttpExceptions: true,
    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
  }, opts || {}));
  const status = res.getResponseCode();
  const body = res.getContentText();
  let json = {};
  try { json = JSON.parse(body); } catch (_) {}
  if (status >= 400) {
    throw new Error(json.error || ('HTTP ' + status + ': ' + body.slice(0, 200)));
  }
  return json;
}

function apiHealth() { return apiFetch_(ENDPOINTS.health); }
function apiShops()  { return apiFetch_(ENDPOINTS.shops); }
function apiSync(payload) {
  return apiFetch_(ENDPOINTS.sync, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify(payload),
  });
}

// ─── Sheet I/O ────────────────────────────────────────────────────
/**
 * Read the active sheet into a list of product objects. Tolerant of
 * column reordering — we match by header name, not by position.
 * Empty rows skipped. Returns { products: [...], header: [...], stats: {...} }.
 */
function readSheet() {
  const sheet = SpreadsheetApp.getActiveSheet();
  const range = sheet.getDataRange();
  const values = range.getValues();
  if (values.length < 1) {
    return { products: [], headerOk: false, rowCount: 0, message: 'Sheet is empty. Click "Start from demo" to populate it.' };
  }

  const header = values[0].map(h => String(h || '').trim().toLowerCase());
  const idx = {
    name:        firstMatch_(header, ['product name', 'name']),
    category:    firstMatch_(header, ['category', 'product category']),
    description: firstMatch_(header, ['description', 'product description']),
    image_url:   firstMatch_(header, ['image url', 'image']),
    price:       firstMatch_(header, ['price']),
    sku:         firstMatch_(header, ['sku']),
    stock:       firstMatch_(header, ['stock', 'stock qty', 'quantity']),
    active:      firstMatch_(header, ['active', 'active(y/n)', 'active y/n']),
  };

  if (idx.name < 0 || idx.price < 0) {
    return {
      products: [],
      headerOk: false,
      rowCount: 0,
      message: 'Missing required columns. The sheet needs at least "Product Name" and "Price" — click "Start from demo" to set up the headers.',
    };
  }

  const products = [];
  for (let r = 1; r < values.length; r++) {
    const row = values[r];
    const name = String(row[idx.name] || '').trim();
    if (!name) continue;
    const price = Number(row[idx.price]);
    if (!isFinite(price) || price < 0) continue;
    const active = idx.active >= 0 ? !/^n/i.test(String(row[idx.active] || 'Y').trim()) : true;

    products.push({
      no:          products.length + 1,
      name:        name,
      category:    idx.category    >= 0 ? String(row[idx.category]    || '').trim() : '',
      description: idx.description >= 0 ? String(row[idx.description] || '').trim() : '',
      image_url:   idx.image_url   >= 0 ? String(row[idx.image_url]   || '').trim() : '',
      price:       price,
      sku:         idx.sku         >= 0 ? String(row[idx.sku]         || '').trim() : '',
      stock:       idx.stock       >= 0 && row[idx.stock] !== '' ? Number(row[idx.stock]) : null,
      active:      active ? 'Y' : 'N',
    });
  }

  return {
    products: products,
    headerOk: true,
    rowCount: products.length,
    sheet_name: sheet.getName(),
  };
}

function firstMatch_(arr, candidates) {
  for (let i = 0; i < candidates.length; i++) {
    const k = candidates[i].toLowerCase();
    const idx = arr.indexOf(k);
    if (idx >= 0) return idx;
  }
  return -1;
}

/**
 * Wipe the active sheet and write demo headers + sample rows. Called
 * when the user clicks "Start from demo" → "Agree" in the warning.
 */
function populateDemo() {
  const sheet = SpreadsheetApp.getActiveSheet();
  sheet.clear();
  sheet.getRange(1, 1, 1, SHEET_HEADERS.length).setValues([SHEET_HEADERS]);
  sheet.getRange(1, 1, 1, SHEET_HEADERS.length)
    .setFontWeight('bold')
    .setBackground('#E8F4F1');
  sheet.getRange(2, 1, DEMO_ROWS.length, SHEET_HEADERS.length).setValues(DEMO_ROWS);
  sheet.autoResizeColumns(1, SHEET_HEADERS.length);
  SpreadsheetApp.flush();
  return readSheet();
}

/**
 * Write just the header row (no demo data). Used when the user
 * already has products elsewhere and wants the right column shape.
 */
function writeHeadersOnly() {
  const sheet = SpreadsheetApp.getActiveSheet();
  sheet.getRange(1, 1, 1, SHEET_HEADERS.length).setValues([SHEET_HEADERS]);
  sheet.getRange(1, 1, 1, SHEET_HEADERS.length).setFontWeight('bold').setBackground('#E8F4F1');
  SpreadsheetApp.flush();
  return readSheet();
}

// ─── Publish ──────────────────────────────────────────────────────
/**
 * Called from the wizard's final "Publish" button. Reads the sheet,
 * merges in shop config from the modal, posts to Wasnap.
 */
function publishShop(shopConfig) {
  const sheet = readSheet();
  if (!sheet.headerOk || sheet.products.length === 0) {
    throw new Error('No valid product rows to publish.');
  }
  const payload = {
    shop_id:     shopConfig.shop_id || null,
    shop_name:   shopConfig.shop_name,
    description: shopConfig.description || '',
    currency:    shopConfig.currency || 'INR',
    theme_key:   shopConfig.theme_key || 'aurora',
    products: sheet.products.map(p => ({
      name:        p.name,
      category:    p.category || null,
      description: p.description || null,
      image_url:   p.image_url || null,
      price:       p.price,
      sku:         p.sku || null,
      stock:       p.stock,
      active:      p.active === 'Y',
    })),
  };
  return apiSync(payload);
}
