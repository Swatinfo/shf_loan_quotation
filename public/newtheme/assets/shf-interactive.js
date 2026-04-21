/* SHF Redesign — INTERACTIVE layer
   Separate JS file for new behavior. Do not edit assets/shared.js or assets/page.js.

   Features:
   - Toast notifications
   - Modal system (data-modal="id" opens .modal#id)
   - Conditional fields (data-cond-when='{"field":"loan_type","equals":"home"}')
   - Button click -> navigate or toast
   - Toggle switches
   - Segmented controls
   - Stepper navigation
   - Tabs (non-link tabs)
   - Stage rail click to jump
   - Delegation-friendly, mounts after DOMContentLoaded.
*/

(function() {
  'use strict';

  // ---------------- Toast ----------------
  function ensureToastHost() {
    let host = document.querySelector('.toast-host');
    if (!host) {
      host = document.createElement('div');
      host.className = 'toast-host';
      document.body.appendChild(host);
    }
    return host;
  }
  window.shfToast = function(msg, opts) {
    opts = opts || {};
    const host = ensureToastHost();
    const el = document.createElement('div');
    el.className = 'toast-msg';
    el.innerHTML = '<span class="icon">' + (opts.icon || '✓') + '</span><span>' + msg + '</span>';
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 220);
    }, opts.duration || 2400);
  };

  // ---------------- Modal ----------------
  window.shfOpenModal = function(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
  };
  window.shfCloseModal = function(el) {
    const backdrop = el.closest('.modal-backdrop');
    if (backdrop) backdrop.classList.remove('open');
  };

  // ---------------- Conditional fields ----------------
  function evalCondition(cond, formEl) {
    try {
      const c = typeof cond === 'string' ? JSON.parse(cond) : cond;
      const input = formEl.querySelector('[name="' + c.field + '"]:checked, [name="' + c.field + '"]');
      if (!input) return false;
      let v;
      if (input.type === 'checkbox') v = input.checked;
      else v = input.value;
      if ('equals' in c) return String(v) === String(c.equals);
      if ('in' in c) return c.in.map(String).includes(String(v));
      if ('not' in c) return String(v) !== String(c.not);
      if ('truthy' in c) return !!v;
      return false;
    } catch (e) { return false; }
  }

  function updateConditions(scope) {
    scope = scope || document;
    scope.querySelectorAll('[data-cond-when]').forEach(el => {
      const form = el.closest('form') || el.closest('[data-form]') || document;
      const ok = evalCondition(el.dataset.condWhen, form);
      el.classList.toggle('hide', !ok);
    });
  }
  window.shfUpdateConditions = updateConditions;

  // ---------------- Toggle switch ----------------
  function initToggles(scope) {
    (scope || document).querySelectorAll('.toggle').forEach(t => {
      if (t.__bound) return;
      t.__bound = true;
      t.addEventListener('click', () => {
        t.classList.toggle('on');
        const input = t.querySelector('input[type=hidden]');
        if (input) input.value = t.classList.contains('on') ? '1' : '0';
        t.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { on: t.classList.contains('on') } }));
      });
    });
  }

  // ---------------- Segmented ----------------
  function initSegmented(scope) {
    (scope || document).querySelectorAll('.segmented').forEach(seg => {
      if (seg.__bound) return;
      seg.__bound = true;
      seg.addEventListener('click', e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        seg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        seg.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { value: btn.dataset.value || btn.textContent.trim() } }));
      });
    });
  }

  // ---------------- Tab switcher ----------------
  // Handles 3 kinds of tab bars:
  //  1. data-tabs + data-panel / data-panel-id -> switch panels (explicit)
  //  2. data-tab + data-tab-group (handled in page.js)
  //  3. Plain .tabs with plain .tab anchors -> toggle active + filter following .tbl by label
  function tabLabel(el) {
    const clone = el.cloneNode(true);
    clone.querySelectorAll('.count').forEach(n => n.remove());
    return clone.textContent.trim().toLowerCase();
  }
  function filterTableByLabel(tabs, label) {
    // Find the nearest following table in page order
    let host = tabs.closest('header, .page-header, section, main') || document;
    const tbl = host.querySelector('.tbl, table');
    const outerTbl = !tbl ? document.querySelector('main .tbl, main table') : tbl;
    const target = tbl || outerTbl;
    if (!target) return;
    const rows = target.querySelectorAll('tbody tr');
    if (!rows.length) return;
    const l = label.toLowerCase().trim();
    const matchAll = ['all', 'my tasks', 'my dvr', 'overview', 'business', 'account', 'workflow', 'roles'].includes(l);

    // Build a predicate per tab label. Each predicate takes (rowText, badgeTexts[]) -> bool
    const pred = (() => {
      if (matchAll) return () => true;
      // loans.html
      if (l === 'active') return (rt, bs) => !bs.some(b => /complete|closed|otc|disburs/.test(b));
      if (l === 'disbursed') return (rt, bs) => bs.some(b => /disburs/.test(b));
      if (l === 'otc pending' || l === 'otc') return (rt, bs) => bs.some(b => /otc/.test(b));
      if (l === 'closed') return (rt, bs) => bs.some(b => /complete|closed/.test(b));
      // quotations.html
      if (l === 'draft') return (rt, bs) => bs.some(b => /draft/.test(b));
      if (l === 'sent') return (rt, bs) => bs.some(b => /sent|shared/.test(b));
      if (l === 'converted') return (rt, bs) => bs.some(b => /convert|won/.test(b));
      if (l === 'expired') return (rt, bs) => bs.some(b => /expir|lost/.test(b));
      // customers.html
      if (l === 'leads') return (rt, bs) => bs.some(b => /lead/.test(b));
      if (l === 'pre-qualified') return (rt, bs) => bs.some(b => /pre.?qual/.test(b));
      if (l === 'alumni') return (rt, bs) => bs.some(b => /alum|closed|past/.test(b));
      // tasks
      if (l === 'team') return rt => /team|anita|neha|vipul|bharat|saurabh|harsh|dipti/.test(rt);
      if (l === 'overdue') return (rt, bs) => bs.some(b => /overdue|late/.test(b)) || /overdue/.test(rt);
      if (l === 'completed') return (rt, bs) => bs.some(b => /complete|done/.test(b));
      // dvr
      if (l === 'team dvr') return rt => /anita|neha|vipul|bharat|saurabh|harsh|dipti/.test(rt);
      if (l === 'route map' || l === 'summary') return () => true;
      // reports
      if (['funnel', 'bank-wise', 'advisor', 'operations'].includes(l)) return () => true;
      // notifications
      if (l === 'mentions') return rt => /@|mention/.test(rt);
      if (l === 'queries') return (rt, bs) => bs.some(b => /query/.test(b)) || /query|question/.test(rt);
      // fallback: substring match on stemmed label
      const stem = l.replace(/(ed|ing|s|ment)$/i, '').slice(0, Math.max(4, l.length - 2));
      return (rt, bs) => bs.some(b => b.includes(stem)) || rt.includes(stem);
    })();

    let visible = 0;
    rows.forEach(row => {
      const rt = row.textContent.toLowerCase();
      const bs = [...row.querySelectorAll('.badge, .status, .chip, .pill')].map(b => b.textContent.toLowerCase());
      const show = pred(rt, bs);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const counter = host.querySelector('[data-row-count]');
    if (counter) counter.textContent = visible;
  }
  function initTabs(scope) {
    // Tabs are now handled by page.js (single source of truth). Keep only
    // the label-based table filter behavior for plain tab bars.
    (scope || document).querySelectorAll('.tabs').forEach(tabs => {
      if (tabs.__filterBound) return;
      tabs.__filterBound = true;
      tabs.addEventListener('click', e => {
        const tab = e.target.closest('.tab');
        if (!tab) return;
        // Skip if this tab has panel behavior (page.js handles it)
        if (tab.hasAttribute('data-panel') || tab.hasAttribute('data-tab')) return;
        // Skip cross-page nav tabs
        const href = tab.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('#')) return;
        // Filter a following table if present
        const label = tabLabel(tab);
        filterTableByLabel(tabs, label);
      });
    });
    // Legacy code retained below but disabled
    return;
    (scope || document).querySelectorAll('.tabs').forEach(tabs => {
      if (tabs.__bound) return;
      tabs.__bound = true;
      tabs.addEventListener('click', e => {
        const tab = e.target.closest('.tab');
        if (!tab) return;
        // Let link-based tabs (with real href to another page) navigate naturally
        if (tab.tagName === 'A' && tab.getAttribute('href') && !tab.getAttribute('href').startsWith('#') && !tab.classList.contains('active') && !tab.dataset.panel && !tab.dataset.tab) {
          return; // real navigation
        }
        // data-tab-group handled by page.js — don't double-handle panels
        if (tab.dataset.tab && tab.dataset.tabGroup) {
          // If this tab has an href, let it navigate
          if (tab.tagName === 'A' && tab.getAttribute('href')) return;
          // Otherwise: toggle active, attempt to find panel, else toast a friendly message
          const grp = tab.dataset.tabGroup;
          const target = tab.dataset.tab;
          document.querySelectorAll(`[data-tab-group="${grp}"]`).forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          // Try section-based filtering: find a panel group that contains [data-section]
          const groups = document.querySelectorAll(`[data-tab-panel-group="${grp}"]`);
          let sections = null;
          for (const g of groups) {
            const found = g.querySelectorAll('[data-section]');
            if (found.length) { sections = found; break; }
          }
          if (sections && sections.length) {
            sections.forEach(sec => {
              const keys = sec.getAttribute('data-section').split(/\s+/).filter(Boolean);
              sec.style.display = (target === 'overview' || keys.includes(target)) ? '' : 'none';
            });
            e.preventDefault();
            return;
          }
          // Fallback: explicit panel switching
          const panel = document.querySelector(`[data-tab-panel="${target}"]`);
          if (panel) {
            document.querySelectorAll(`[data-tab-panel-group="${grp}"] > [data-tab-panel]`).forEach(p => p.style.display = 'none');
            panel.style.display = '';
          }
          e.preventDefault();
          return;
        }
        e.preventDefault();
        // Toggle active among siblings
        tabs.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        // Explicit panel switching
        if (tab.dataset.panel) {
          document.querySelectorAll('[data-panel-id]').forEach(p => {
            p.style.display = p.dataset.panelId === tab.dataset.panel ? '' : 'none';
          });
          return;
        }
        // Fallback: filter table by label
        const label = tabLabel(tab);
        filterTableByLabel(tabs, label);
      });
    });
  }

  // ---------------- Stepper ----------------
  function initStepper(scope) {
    (scope || document).querySelectorAll('[data-stepper]').forEach(st => {
      if (st.__bound) return;
      st.__bound = true;
      st.addEventListener('click', e => {
        const step = e.target.closest('.step');
        if (!step || !step.dataset.step) return;
        const idx = parseInt(step.dataset.step);
        st.querySelectorAll('.step').forEach((s, i) => {
          s.classList.remove('current');
          if (i < idx) s.classList.add('done');
          else s.classList.remove('done');
          if (i === idx) s.classList.add('current');
        });
        document.querySelectorAll('[data-step-panel]').forEach(p => {
          p.style.display = parseInt(p.dataset.stepPanel) === idx ? '' : 'none';
        });
      });
    });
  }

  // ---------------- Stage rail (clickable) ----------------
  function initStageRail(scope) {
    (scope || document).querySelectorAll('.stage-rail[data-clickable]').forEach(rail => {
      if (rail.__bound) return;
      rail.__bound = true;
      rail.addEventListener('click', e => {
        const stg = e.target.closest('.stg');
        if (!stg) return;
        const n = stg.dataset.stage;
        if (n) window.shfToast('Jumped to stage ' + n);
      });
    });
  }

  // ---------------- Data-action buttons ----------------
  function initActions(scope) {
    (scope || document).addEventListener('click', e => {
      const el = e.target.closest('[data-action]');
      if (!el) return;
      const action = el.dataset.action;
      const arg = el.dataset.arg;

      if (action === 'toast') {
        e.preventDefault();
        window.shfToast(arg || 'Done');
      } else if (action === 'modal-open') {
        e.preventDefault();
        window.shfOpenModal(arg);
      } else if (action === 'modal-close') {
        e.preventDefault();
        window.shfCloseModal(el);
      } else if (action === 'goto' || action === 'link') {
        e.preventDefault();
        location.href = arg;
      } else if (action === 'back') {
        e.preventDefault();
        history.back();
      } else if (action === 'save') {
        e.preventDefault();
        window.shfToast(arg || 'Saved');
      } else if (action === 'submit') {
        e.preventDefault();
        window.shfToast(arg || 'Submitted');
        if (el.dataset.next) setTimeout(() => location.href = el.dataset.next, 700);
      } else if (action === 'delete') {
        e.preventDefault();
        if (confirm('Are you sure? This cannot be undone.')) {
          window.shfToast(arg || 'Deleted');
        }
      } else if (action === 'confirm') {
        e.preventDefault();
        if (confirm(el.dataset.msg || 'Confirm action?')) {
          window.shfToast(arg || 'Confirmed');
          if (el.dataset.next) setTimeout(() => location.href = el.dataset.next, 500);
        }
      } else if (action === 'print') {
        e.preventDefault();
        window.print();
      }
    });
  }

  // ---------------- Form live update for conditions ----------------
  function initLiveForms(scope) {
    (scope || document).querySelectorAll('[data-form]').forEach(form => {
      if (form.__bound) return;
      form.__bound = true;
      form.addEventListener('change', () => updateConditions(form));
      form.addEventListener('input', () => updateConditions(form));
      updateConditions(form);
    });
  }

  // ---------------- Modal backdrop click ----------------
  function initModals(scope) {
    (scope || document).querySelectorAll('.modal-backdrop').forEach(bd => {
      if (bd.__bound) return;
      bd.__bound = true;
      bd.addEventListener('click', e => {
        if (e.target === bd) bd.classList.remove('open');
      });
    });
  }

  // ---------------- EMI calc helper ----------------
  window.shfEMI = function(principal, rateAnnualPct, years) {
    const r = (rateAnnualPct / 100) / 12;
    const n = years * 12;
    if (r === 0) return principal / n;
    return (principal * r * Math.pow(1+r, n)) / (Math.pow(1+r, n) - 1);
  };
  window.shfFormatINR = function(n) {
    if (n == null || isNaN(n)) return '—';
    return '₹ ' + Math.round(n).toLocaleString('en-IN');
  };

  // ---------------- Keyboard shortcuts ----------------
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.open').forEach(b => b.classList.remove('open'));
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      const s = document.querySelector('.top-search input, .top-search');
      if (s) { e.preventDefault(); s.focus && s.focus(); window.shfToast('Global search', { icon: '🔍' }); }
    }
  });

  // ---------------- Dropdowns (any button ending with ▾) ----------------
  const DROPDOWN_OPTIONS = {
    'bank': ['HDFC Bank', 'SBI', 'ICICI Bank', 'Axis Bank', 'Kotak Mahindra', 'Bank of Baroda', 'PNB', 'Bajaj Housing', 'LIC HFL'],
    'loan type': ['Home Loan', 'LAP', 'Balance Transfer', 'Construction', 'Plot + Construction', 'Top-up', 'Home Improvement'],
    'advisor': ['Rahul Jadeja', 'Anita Patel', 'Neha Soni', 'Vipul Mehta', 'Bharat Mehta', 'Dipti Kaul', 'Saurabh Gandhi', 'Harsh Doshi'],
    'date range': ['Today', 'This week', 'This month', 'Last month', 'QTD', 'FY', 'Custom range…'],
    'stage': ['1 · Inquiry', '2 · Doc selection', '3 · Doc collection', '4 · Parallel proc.', '5 · Rate & PF', '6 · Sanction letter', '7 · Docket login', '8 · KFS', '9 · E-Sign', '10 · Disbursement', '11 · OTC clearance', '12 · Completed'],
    'amount': ['Under ₹10L', '₹10L – ₹25L', '₹25L – ₹50L', '₹50L – ₹1Cr', '₹1Cr – ₹2Cr', 'Above ₹2Cr'],
    'aging': ['0–3 days', '4–7 days', '1–2 weeks', '2–4 weeks', 'Over a month', 'Stale (60d+)'],
    'employment': ['Salaried', 'Self-employed (prof.)', 'Self-employed (biz)', 'Proprietor', 'Partnership', 'Pvt Ltd Director', 'Pensioner', 'NRI'],
    'cibil': ['800+', '750–799', '700–749', '650–699', 'Below 650', 'No score'],
    'income band': ['Under ₹50k', '₹50k – ₹1L', '₹1L – ₹2L', '₹2L – ₹5L', 'Above ₹5L'],
    'source': ['Walk-in', 'Referral', 'DSA', 'Ad · Facebook', 'Ad · Google', 'Alumni', 'Website'],
    'filter by advisor': ['All advisors', 'Rahul Jadeja', 'Anita Patel', 'Vipul Mehta', 'Bharat Mehta'],
    'sort: lowest emi': ['Lowest EMI', 'Highest EMI', 'Lowest rate', 'Highest tenure', 'Highest eligibility'],
    'default': ['Option A', 'Option B', 'Option C']
  };

  let openDropdown = null;
  function closeDropdown() {
    if (openDropdown) {
      openDropdown.menu.remove();
      openDropdown.button.classList.remove('dd-open');
      openDropdown = null;
    }
  }
  function openDropdownFor(btn) {
    closeDropdown();
    const raw = btn.textContent.replace(/▾/g, '').trim().toLowerCase();
    // Strip "Sort:" prefix etc.
    const key = raw.replace(/^(sort:|filter:)\s*/, '').replace(/^filter by\s+/, 'filter by ');
    let options = DROPDOWN_OPTIONS[key] || DROPDOWN_OPTIONS[key.replace(/\s+▾$/, '')] || null;
    // Try prefix match
    if (!options) {
      for (const k of Object.keys(DROPDOWN_OPTIONS)) {
        if (key.startsWith(k) || k.startsWith(key)) { options = DROPDOWN_OPTIONS[k]; break; }
      }
    }
    if (!options) options = DROPDOWN_OPTIONS.default;
    const menu = document.createElement('div');
    menu.className = 'shf-dropdown';
    options.forEach(opt => {
      const item = document.createElement('div');
      item.className = 'shf-dropdown-item';
      item.textContent = opt;
      item.addEventListener('click', ev => {
        ev.stopPropagation();
        // Update button label (keep ▾)
        const origLabel = btn.dataset.origLabel || btn.textContent.replace(/▾/g, '').trim();
        if (!btn.dataset.origLabel) btn.dataset.origLabel = origLabel;
        btn.innerHTML = opt + ' <span style="opacity:.5">▾</span>';
        btn.classList.add('dd-selected');
        closeDropdown();
        if (window.shfToast) window.shfToast('Filter: ' + opt);
      });
      menu.appendChild(item);
    });
    // Add "Clear" row if button has been set before
    if (btn.dataset.origLabel) {
      const clr = document.createElement('div');
      clr.className = 'shf-dropdown-item shf-dropdown-clear';
      clr.textContent = 'Clear filter';
      clr.addEventListener('click', ev => {
        ev.stopPropagation();
        btn.innerHTML = btn.dataset.origLabel + ' ▾';
        btn.classList.remove('dd-selected');
        delete btn.dataset.origLabel;
        closeDropdown();
      });
      menu.appendChild(clr);
    }
    document.body.appendChild(menu);
    const r = btn.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.top = (r.bottom + 4) + 'px';
    menu.style.left = r.left + 'px';
    menu.style.minWidth = Math.max(r.width, 180) + 'px';
    menu.style.zIndex = 9999;
    btn.classList.add('dd-open');
    openDropdown = { menu, button: btn };
  }
  function initDropdowns() {
    document.addEventListener('click', e => {
      const btn = e.target.closest('button.btn, .btn');
      if (btn && /▾/.test(btn.textContent) && !btn.dataset.action) {
        e.preventDefault();
        e.stopPropagation();
        if (openDropdown && openDropdown.button === btn) {
          closeDropdown();
        } else {
          openDropdownFor(btn);
        }
        return;
      }
      // Click outside -> close
      if (openDropdown && !e.target.closest('.shf-dropdown')) {
        closeDropdown();
      }
    }, true);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeDropdown();
    });
  }

  // Hamburger removed — mobile navigation is served by the fixed bottom-nav (.m-bottomnav).
  function initMobileNav() { /* no-op */ }

  // ---------------- Row Action Menus (...) ----------------
  // Uses capture phase so the handler fires BEFORE any stopPropagation() on an
  // ancestor (e.g., <td onclick="event.stopPropagation()"> that prevents row
  // navigation on action cells).
  function initRowActions() {
    document.addEventListener('click', e => {
      const trigger = e.target.closest('[data-row-actions]');
      if (trigger) {
        e.preventDefault();
        e.stopPropagation();
        // Close any other open
        document.querySelectorAll('.row-actions.open').forEach(el => {
          if (el !== trigger.closest('.row-actions')) el.classList.remove('open');
        });
        const wrap = trigger.closest('.row-actions');
        if (wrap) wrap.classList.toggle('open');
        return;
      }
      // Click outside closes all (unless it's inside the menu itself — let the link fire)
      if (!e.target.closest('.row-actions-menu')) {
        document.querySelectorAll('.row-actions.open').forEach(el => el.classList.remove('open'));
      }
    }, true);

    // Delete confirmation
    document.addEventListener('click', e => {
      const del = e.target.closest('[data-confirm-delete]');
      if (!del) return;
      e.preventDefault();
      const label = del.getAttribute('data-confirm-delete') || 'this item';
      if (confirm('Delete ' + label + '? This cannot be undone.')) {
        const row = del.closest('tr');
        if (row) {
          row.style.transition = 'opacity 0.2s';
          row.style.opacity = '0';
          setTimeout(() => row.remove(), 200);
        }
        window.shfToast && window.shfToast('Deleted ' + label, {icon:'🗑'});
      }
    });
  }

  // ---------------- Collapsible Stage Cards ----------------
  function initCollapsibles() {
    document.addEventListener('click', e => {
      const head = e.target.closest('[data-collapse]');
      if (!head) return;
      const card = head.closest('.stage-card, .panel');
      if (!card) return;
      const body = card.querySelector('.sb, .panel-body');
      if (!body) return;
      body.classList.toggle('collapsed');
      head.classList.toggle('collapsed');
    });
  }

  // ---------------- Date inputs: click anywhere to open picker ----------------
  function initDateInputs() {
    // Click on date/time input (anywhere) opens native picker
    document.addEventListener('click', e => {
      const inp = e.target.closest('input[type="date"], input[type="time"], input[type="datetime-local"]');
      if (!inp) return;
      if (inp.disabled || inp.readOnly) return;
      if (typeof inp.showPicker === 'function') {
        try { inp.showPicker(); } catch(_) {}
      }
    });
    // Focus also triggers picker
    document.addEventListener('focus', e => {
      const inp = e.target;
      if (!inp.matches || !inp.matches('input[type="date"], input[type="time"], input[type="datetime-local"]')) return;
      if (inp.disabled || inp.readOnly) return;
      if (typeof inp.showPicker === 'function') {
        try { inp.showPicker(); } catch(_) {}
      }
    }, true);
  }

  // ---------------- Settings nav dropdown (enhancement) ----------------
  // If Settings nav item is rendered as a plain <a>, wrap it in a .nav-dd-wrap
  // with a submenu. Safe to run multiple times.
  function initSettingsDropdown() {
    const SUBMENU = [
      { label: 'All settings',        href: 'settings-hub.html' },
      { label: 'Quotation Settings',  href: 'settings.html' },
      { label: 'Loan Settings',       href: 'loan-settings.html' },
      { label: 'Permissions',         href: 'permissions.html' },
      { label: 'Roles',               href: 'roles.html' },
      { label: 'Activity Log',        href: 'activity-log.html' },
    ];
    function wrap() {
      const nav = document.querySelector('.nav-primary');
      if (!nav) return;
      if (nav.querySelector('.nav-dd-wrap')) return; // already wrapped
      const links = [...nav.querySelectorAll('a.nav-item')];
      const settings = links.find(a => {
        const sp = a.querySelector('span');
        return sp && sp.textContent.trim() === 'Settings';
      });
      if (!settings) return;

      const wrapEl = document.createElement('div');
      wrapEl.className = 'nav-dd-wrap';
      settings.parentNode.insertBefore(wrapEl, settings);
      wrapEl.appendChild(settings);

      // Ensure chevron present
      if (!settings.querySelector('.nav-chev')) {
        const chev = document.createElement('span');
        chev.className = 'nav-chev';
        chev.style.cssText = 'margin-left:4px;opacity:0.7;font-size:10px;';
        chev.textContent = '▾';
        settings.appendChild(chev);
      }

      const menu = document.createElement('div');
      menu.className = 'nav-dd';
      menu.innerHTML = SUBMENU.map(d =>
        `<a class="nav-dd-item" href="${d.href}">${d.label}</a>`
      ).join('');
      wrapEl.appendChild(menu);

      // Click toggle (touch devices), click outside closes
      settings.addEventListener('click', (e) => {
        // Mobile drawer: dropdown is already expanded inline, let link navigate
        if (window.matchMedia('(max-width: 768px)').matches) return;
        if (window.matchMedia('(hover: none)').matches) {
          e.preventDefault();
          wrapEl.classList.toggle('open');
        }
      });
    }
    // Run now, and retry shortly in case topbar mounts async
    wrap();
    setTimeout(wrap, 50);
    setTimeout(wrap, 200);
    setTimeout(wrap, 600);

    document.addEventListener('click', (e) => {
      if (!e.target.closest('.nav-dd-wrap')) {
        document.querySelectorAll('.nav-dd-wrap.open').forEach(w => w.classList.remove('open'));
      }
    });
  }

  // ---------------- Auto-label table cells (mobile card mode) ----------------
  function autoLabelTables(root) {
    const scope = root || document;
    const tables = scope.querySelectorAll('table.tbl:not(.matrix)');
    tables.forEach(tbl => {
      if (tbl.dataset.labeled === '1') return;
      const headers = [...tbl.querySelectorAll('thead th')].map(th => th.textContent.trim());
      if (!headers.length) return;
      tbl.querySelectorAll('tbody tr').forEach(tr => {
        [...tr.children].forEach((td, i) => {
          if (headers[i] && !td.hasAttribute('data-label')) {
            td.setAttribute('data-label', headers[i]);
          }
        });
      });
      tbl.dataset.labeled = '1';
    });
  }
  // Observe DOM for late-rendered tables
  function initTableLabels() {
    autoLabelTables();
    const obs = new MutationObserver(muts => {
      let needs = false;
      for (const m of muts) {
        for (const n of m.addedNodes) {
          if (n.nodeType === 1 && (n.matches?.('table.tbl') || n.querySelector?.('table.tbl'))) {
            needs = true; break;
          }
        }
        if (needs) break;
      }
      if (needs) autoLabelTables();
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  function boot() {
    initToggles();
    initSegmented();
    initTabs();
    initStepper();
    initStageRail();
    initActions();
    initLiveForms();
    initModals();
    initDropdowns();
    initMobileNav();
    initRowActions();
    initCollapsibles();
    initDateInputs();
    initSettingsDropdown();
    initTableLabels();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Re-run on dynamic inserts (exposed helper)
  window.shfRescan = boot;
})();
