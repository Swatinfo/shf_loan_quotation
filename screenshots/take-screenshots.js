/**
 * Automated Screenshot Tool — Shreenathji Home Finance
 * Takes full-page screenshots of every screen at multiple viewport sizes.
 * Supports tabbed pages — captures each tab as a separate screenshot.
 * Files are numbered sequentially (001-, 002-, ...) to show capture flow order.
 *
 * Usage: node screenshots/take-screenshots.js
 * Requires: puppeteer (globally installed)
 */

const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'https://oldloanproposal.test';
const LOGIN_EMAIL = 'admin@shf.com';
const LOGIN_PASSWORD = 'Admin@123';

// All viewport configurations (keyed by menu number)
const ALL_VIEWPORTS = {
  1: { key: 'mobile/portrait',   width: 375,  height: 812,  label: 'Mobile Portrait'  },
  2: { key: 'mobile/landscape',  width: 812,  height: 375,  label: 'Mobile Landscape' },
  3: { key: 'tablet/portrait',   width: 768,  height: 1024, label: 'Tablet Portrait'  },
  4: { key: 'tablet/landscape',  width: 1024, height: 768,  label: 'Tablet Landscape' },
  5: { key: 'laptop',            width: 1366, height: 768,  label: 'Laptop'           },
  6: { key: 'desktop',           width: 1920, height: 1080, label: 'Desktop (Full HD)'},
  7: { key: 'large-desktop',     width: 2560, height: 1440, label: 'Large Desktop (QHD)' },
};

// Will be populated after user selection
let VIEWPORTS = {};

// Pages to screenshot (name → path)
// tabs: array of { name, dataTab } for pages with shf-tab switching
const PAGES = [
  // Auth (unauthenticated)
  { name: 'login',                  path: '/login',               auth: false },
  { name: 'forgot-password',        path: '/forgot-password',     auth: false },

  // Dashboard — tabs
  { name: 'dashboard',              path: '/dashboard', tabs: [
    { name: 'dashboard-tasks',       dataTab: 'dash-tasks' },
    { name: 'dashboard-loans',       dataTab: 'dash-loans' },
    { name: 'dashboard-quotations',  dataTab: 'dash-quotations' },
  ]},

  // Quotations
  { name: 'quotation-create',       path: '/quotations/create' },

  // Loans
  { name: 'loans-index',            path: '/loans' },
  { name: 'loan-create',            path: '/loans/create' },

  // Users
  { name: 'users-index',            path: '/users' },
  { name: 'user-create',            path: '/users/create' },

  // Quotation Settings — tabs
  { name: 'settings',               path: '/settings', tabs: [
    { name: 'settings-company',      dataTab: 'company' },
    { name: 'settings-charges',      dataTab: 'charges' },
    { name: 'settings-bank-charges', dataTab: 'bank-charges' },
    { name: 'settings-gst',          dataTab: 'gst' },
    { name: 'settings-services',     dataTab: 'services' },
    { name: 'settings-tenures',      dataTab: 'tenures' },
    { name: 'settings-documents',    dataTab: 'documents' },
    { name: 'settings-permissions',  dataTab: 'permissions' },
  ]},

  // Loan Settings — tabs
  { name: 'loan-settings',          path: '/loan-settings', tabs: [
    { name: 'loan-settings-locations',        dataTab: 'locations' },
    { name: 'loan-settings-banks',            dataTab: 'banks' },
    { name: 'loan-settings-branches',         dataTab: 'branches' },
    { name: 'loan-settings-master-stages',    dataTab: 'master-stages' },
    { name: 'loan-settings-products',         dataTab: 'products' },
    { name: 'loan-settings-role-permissions', dataTab: 'role-permissions' },
  ]},

  // Permissions
  { name: 'permissions',            path: '/permissions' },

  // Notifications
  { name: 'notifications',          path: '/notifications' },

  // Activity Log
  { name: 'activity-log',           path: '/activity-log' },

  // Profile
  { name: 'profile',                path: '/profile' },
];

// Per-loan pages — captured for EVERY loan (to show each stage)
const PER_LOAN_PAGES = [
  { suffix: 'show',   pathFn: (id) => `/loans/${id}` },
  { suffix: 'stages', pathFn: (id) => `/loans/${id}/stages` },
];

// Single-loan pages — captured only for the first loan
const SINGLE_LOAN_PAGES = [
  { name: 'loan-edit',              pathFn: (id) => `/loans/${id}/edit` },
  { name: 'loan-documents',         pathFn: (id) => `/loans/${id}/documents` },
  { name: 'loan-timeline',          pathFn: (id) => `/loans/${id}/timeline` },
  { name: 'loan-transfers',         pathFn: (id) => `/loans/${id}/transfers` },
  { name: 'loan-disbursement',      pathFn: (id) => `/loans/${id}/disbursement` },
  { name: 'loan-valuation',         pathFn: (id) => `/loans/${id}/valuation` },
];

const QUOTATION_PAGES = [
  { name: 'quotation-show',         pathFn: (id) => `/quotations/${id}` },
];

const USER_PAGES = [
  { name: 'user-edit',              pathFn: (id) => `/users/${id}/edit` },
];

// Users for multi-role screenshots of loan stages
const ROLE_USERS = [
  { name: 'admin',           email: 'admin@shf.com',         password: 'Admin@123' },
  { name: 'branch-manager',  email: 'denish@shfworld.com',   password: 'password' },
  { name: 'bdh',             email: 'bdh@shfworld.com',      password: 'password' },
  { name: 'loan-advisor',    email: 'jaydeep@shfworld.com',  password: 'password' },
  { name: 'bank-employee',   email: 'hdfc@manager.cop',      password: 'password' },
  { name: 'office-employee', email: 'vipul@office.com',      password: 'password' },
];

const SCREENSHOT_DIR = path.join(__dirname);
const readline = require('readline');

// Global sequential counter for numbered filenames
let screenshotCounter = 0;

function nextNumber() {
  screenshotCounter++;
  return String(screenshotCounter).padStart(3, '0');
}

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function takeScreenshot(page, pageName, viewportName, viewportSize) {
  const dir = path.join(SCREENSHOT_DIR, viewportName);
  fs.mkdirSync(dir, { recursive: true });

  await page.setViewport({
    width: viewportSize.width,
    height: viewportSize.height,
    deviceScaleFactor: 1,
  });

  // Wait for any responsive layout reflow
  await sleep(500);

  const filePath = path.join(dir, `${pageName}.png`);

  // Mode 3: skip if file already exists
  if (screenshotMode === 3 && fs.existsSync(filePath)) {
    console.log(`  ⏭ ${viewportName}/${pageName}.png (exists, skipped)`);
    return;
  }

  await page.screenshot({
    path: filePath,
    fullPage: true,
  });

  console.log(`  ✓ ${viewportName}/${pageName}.png`);
}

/**
 * Click a tab by its data-tab attribute, wait for content to appear
 */
async function clickTab(page, dataTab) {
  await page.evaluate((tab) => {
    const btn = document.querySelector(`.shf-tab[data-tab="${tab}"]`);
    if (btn) btn.click();
  }, dataTab);
  await sleep(800); // Wait for tab content + any AJAX
}

/**
 * Capture a page with numbered prefix.
 * If it has tabs, each tab gets its own number.
 * Otherwise the page gets one number.
 */
async function capturePage(page, pg, pagePath) {
  try {
    await page.goto(`${BASE_URL}${pagePath}`, { waitUntil: 'networkidle2' });
    await sleep(1000); // Wait for DataTables/AJAX

    if (pg.tabs && pg.tabs.length > 0) {
      for (const tab of pg.tabs) {
        const num = nextNumber();
        const numberedName = `${num}-${tab.name}`;
        console.log(`\n📄 ${numberedName} (${pagePath}#${tab.dataTab})`);
        await clickTab(page, tab.dataTab);
        for (const [vpName, vpSize] of Object.entries(VIEWPORTS)) {
          await takeScreenshot(page, numberedName, vpName, vpSize);
        }
      }
    } else {
      const num = nextNumber();
      const numberedName = `${num}-${pg.name}`;
      console.log(`\n📄 ${numberedName} (${pagePath})`);
      for (const [vpName, vpSize] of Object.entries(VIEWPORTS)) {
        await takeScreenshot(page, numberedName, vpName, vpSize);
      }
    }
  } catch (err) {
    console.log(`  ✗ Error: ${err.message}`);
  }
}

/**
 * Capture a single named page with numbered prefix.
 */
async function captureNamedPage(page, name, pagePath) {
  const num = nextNumber();
  const numberedName = `${num}-${name}`;
  console.log(`\n📄 ${numberedName} (${pagePath})`);
  try {
    await page.goto(`${BASE_URL}${pagePath}`, { waitUntil: 'networkidle2' });
    await sleep(1000);
    for (const [vpName, vpSize] of Object.entries(VIEWPORTS)) {
      await takeScreenshot(page, numberedName, vpName, vpSize);
    }
  } catch (err) {
    console.log(`  ✗ Error: ${err.message}`);
  }
}

/**
 * Prompt user to select which viewports to capture.
 */
async function selectViewports() {
  console.log('\n╔══════════════════════════════════════╗');
  console.log('║   Select Viewport(s) to Capture      ║');
  console.log('╠══════════════════════════════════════╣');
  for (const [num, vp] of Object.entries(ALL_VIEWPORTS)) {
    console.log(`║   ${num}. ${vp.label.padEnd(30)} ║`);
  }
  console.log('║   8. All viewports                   ║');
  console.log('╚══════════════════════════════════════╝');
  console.log('\nEnter choice(s) — comma-separated or single number');
  console.log('Examples: 1  |  1,2,5  |  8\n');

  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  const answer = await new Promise(resolve => rl.question('Your choice: ', resolve));
  rl.close();

  const choices = answer.trim().split(/[,\s]+/).map(Number).filter(n => !isNaN(n));

  if (choices.includes(8)) {
    // All viewports
    for (const [num, vp] of Object.entries(ALL_VIEWPORTS)) {
      VIEWPORTS[vp.key] = { width: vp.width, height: vp.height };
    }
  } else {
    for (const num of choices) {
      const vp = ALL_VIEWPORTS[num];
      if (vp) {
        VIEWPORTS[vp.key] = { width: vp.width, height: vp.height };
      } else {
        console.log(`⚠ Ignoring invalid choice: ${num}`);
      }
    }
  }

  if (Object.keys(VIEWPORTS).length === 0) {
    console.log('No valid viewports selected. Exiting.');
    process.exit(1);
  }

  const selected = Object.keys(VIEWPORTS).join(', ');
  console.log(`\n✓ Selected: ${selected}`);

  // Ask whether to delete old screenshots first or keep them
  const rl2 = readline.createInterface({ input: process.stdin, output: process.stdout });
  console.log('\n╔══════════════════════════════════════╗');
  console.log('║   Existing Screenshots               ║');
  console.log('╠══════════════════════════════════════╣');
  console.log('║   1. Delete all & regenerate         ║');
  console.log('║   2. Replace (overwrite matching)    ║');
  console.log('║   3. Keep existing, add new only     ║');
  console.log('╚══════════════════════════════════════╝');
  const modeAnswer = await new Promise(resolve => rl2.question('\nYour choice (default: 2): ', resolve));
  rl2.close();

  const mode = parseInt(modeAnswer.trim()) || 2;

  if (mode === 1) {
    // Delete all PNGs in selected viewport folders
    for (const vpKey of Object.keys(VIEWPORTS)) {
      const dir = path.join(SCREENSHOT_DIR, vpKey);
      if (fs.existsSync(dir)) {
        const files = fs.readdirSync(dir).filter(f => f.endsWith('.png'));
        files.forEach(f => fs.unlinkSync(path.join(dir, f)));
      }
    }
    console.log('✓ Deleted old screenshots for selected viewports\n');
  } else if (mode === 2) {
    console.log('✓ Will overwrite existing screenshots with same name\n');
  } else {
    console.log('✓ Will skip files that already exist\n');
  }

  return mode;
}

// Global mode: 1=delete+regen, 2=replace, 3=keep existing
let screenshotMode = 2;

async function run() {
  screenshotMode = await selectViewports();

  console.log('Launching browser...');
  const browser = await puppeteer.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--ignore-certificate-errors',
      '--allow-insecure-localhost',
    ],
    ignoreHTTPSErrors: true,
  });

  // Use incognito context to avoid cache issues
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  page.setDefaultNavigationTimeout(30000);

  // ── Step 1: Login ──
  console.log('\nLogging in...');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
  await page.type('input[name="email"]', LOGIN_EMAIL);
  await page.type('input[name="password"]', LOGIN_PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }),
    page.click('button[type="submit"]'),
  ]);
  console.log('Logged in successfully.\n');

  // ── Step 2: Capture unauthenticated pages first (separate incognito session) ──
  const guestContext = await browser.createBrowserContext();
  const guestPage = await guestContext.newPage();
  guestPage.setDefaultNavigationTimeout(30000);

  const unauthPages = PAGES.filter(p => p.auth === false);
  for (const pg of unauthPages) {
    const num = nextNumber();
    const numberedName = `${num}-${pg.name}`;
    console.log(`📄 ${numberedName} (${pg.path})`);
    try {
      await guestPage.goto(`${BASE_URL}${pg.path}`, { waitUntil: 'networkidle2' });
      await sleep(500);
      for (const [vpName, vpSize] of Object.entries(VIEWPORTS)) {
        await takeScreenshot(guestPage, numberedName, vpName, vpSize);
      }
    } catch (err) {
      console.log(`  ✗ Error: ${err.message}`);
    }
  }
  await guestPage.close();
  await guestContext.close();

  // ── Step 3: Capture authenticated static pages (with tab support) ──
  const authPages = PAGES.filter(p => p.auth !== false);
  for (const pg of authPages) {
    await capturePage(page, pg, pg.path);
  }

  // ── Step 4: Load loan stage map (active loans only, ordered by stage sequence) ──
  const stageMapPath = path.join(SCREENSHOT_DIR, 'loan-stage-map.json');
  let loans = [];
  if (fs.existsSync(stageMapPath)) {
    const stageMap = JSON.parse(fs.readFileSync(stageMapPath, 'utf8'));
    // Preserve insertion order from JSON (ordered by stage sequence from seeder)
    loans = Object.entries(stageMap).map(([id, target]) => ({
      id,
      stageName: target.replace(/_/g, '-'),
    }));
    console.log(`\n✓ Loaded loan-stage-map.json (${loans.length} active loans, ordered by stage)`);
  } else {
    console.log('\n⚠ loan-stage-map.json not found — skipping loan screenshots');
    console.log('  Run: php artisan app:seed-screenshot-loans');
  }

  // ── Step 5: Capture per-loan pages (show + stages for every loan) ──
  if (loans.length > 0) {
    for (const loan of loans) {
      for (const pg of PER_LOAN_PAGES) {
        const pagePath = pg.pathFn(loan.id);
        await captureNamedPage(page, `loan-${pg.suffix}-${loan.stageName}`, pagePath);
      }
    }

    // Single-loan pages (first loan only)
    const firstLoanId = loans[0].id;
    for (const pg of SINGLE_LOAN_PAGES) {
      await captureNamedPage(page, pg.name, pg.pathFn(firstLoanId));
    }
  } else {
    console.log('\n⚠ No loans found — skipping loan pages');
  }

  // ── Step 6: Capture quotation pages ──
  await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'networkidle2' });
  await sleep(1000);
  const quotationId = await page.evaluate(() => {
    const link = document.querySelector('a[href*="/quotations/"]');
    if (link) {
      const match = link.href.match(/\/quotations\/(\d+)/);
      return match ? match[1] : null;
    }
    return null;
  });
  if (quotationId) {
    for (const pg of QUOTATION_PAGES) {
      await captureNamedPage(page, pg.name, pg.pathFn(quotationId));
    }
  } else {
    console.log('\n⚠ No quotations found — skipping quotation detail pages');
  }

  // ── Step 7: Capture user pages ──
  await page.goto(`${BASE_URL}/users`, { waitUntil: 'networkidle2' });
  await sleep(1000);
  const userId = await page.evaluate(() => {
    const link = document.querySelector('a[href*="/users/"][href*="/edit"]');
    if (link) {
      const match = link.href.match(/\/users\/(\d+)/);
      return match ? match[1] : null;
    }
    return null;
  });
  if (userId) {
    for (const pg of USER_PAGES) {
      await captureNamedPage(page, pg.name, pg.pathFn(userId));
    }
  } else {
    console.log('\n⚠ No users found — skipping user edit page');
  }

  // ── Step 8: Multi-role loan stage screenshots ──
  if (loans.length > 0) {
    for (const roleUser of ROLE_USERS) {
      if (roleUser.email === LOGIN_EMAIL) continue; // Already captured as admin

      console.log(`\n🔑 Switching to ${roleUser.name} (${roleUser.email})...`);
      const roleContext = await browser.createBrowserContext();
      const rolePage = await roleContext.newPage();
      rolePage.setDefaultNavigationTimeout(30000);

      try {
        await rolePage.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
        await rolePage.type('input[name="email"]', roleUser.email);
        await rolePage.type('input[name="password"]', roleUser.password);
        await Promise.all([
          rolePage.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }),
          rolePage.click('button[type="submit"]'),
        ]);

        for (const loan of loans) {
          const pagePath = `/loans/${loan.id}/stages`;
          await captureNamedPage(rolePage, `loan-stages-${loan.stageName}-${roleUser.name}`, pagePath);
        }
      } catch (err) {
        console.log(`  ✗ Login failed for ${roleUser.name}: ${err.message}`);
      }

      await rolePage.close();
      await roleContext.close();
    }
  }

  await browser.close();

  // ── Summary ──
  let total = 0;
  for (const vpName of Object.keys(VIEWPORTS)) {
    const dir = path.join(SCREENSHOT_DIR, vpName);
    if (fs.existsSync(dir)) {
      const files = fs.readdirSync(dir).filter(f => f.endsWith('.png'));
      total += files.length;
    }
  }
  console.log(`\n✅ Done! ${total} screenshots saved to screenshots/`);
}

run().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});
