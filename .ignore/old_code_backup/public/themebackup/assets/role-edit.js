/* Role create/edit — permission checkboxes, live count, search */
(function () {
  'use strict';

  const PERMISSIONS = [
    { group: 'Quotations', items: [
      { key: 'quotation.create',   lbl: 'Create quotation',         desc: 'Draft new quotations for customers',           scope: 'branch' },
      { key: 'quotation.edit',     lbl: 'Edit quotation',           desc: 'Modify rate, tenure, and fees',                scope: 'branch' },
      { key: 'quotation.delete',   lbl: 'Delete quotation',         desc: 'Remove drafts; sent quotations are archived',  scope: 'branch' },
      { key: 'quotation.send',     lbl: 'Send to customer',         desc: 'Email/WhatsApp share and public link',          scope: 'branch' },
      { key: 'quotation.convert',  lbl: 'Convert to loan',          desc: 'Promote an accepted quotation into a loan',    scope: 'branch' },
      { key: 'quotation.rate-card',lbl: 'Edit rate card',           desc: 'Bank-wise interest + processing-fee config',   scope: 'org'    },
    ]},
    { group: 'Loans', items: [
      { key: 'loan.create',        lbl: 'Create loan',              desc: 'Open a new loan file',                         scope: 'branch' },
      { key: 'loan.view-own',      lbl: 'View own loans',           desc: 'Read loans where user is assigned',            scope: 'own'    },
      { key: 'loan.view-branch',   lbl: 'View branch loans',        desc: 'Read all loans in user\'s branch',             scope: 'branch' },
      { key: 'loan.view-all',      lbl: 'View all loans',           desc: 'Read every loan across the organization',      scope: 'org'    },
      { key: 'loan.edit',          lbl: 'Edit loan details',        desc: 'Amount, tenure, bank, applicants',             scope: 'branch' },
      { key: 'loan.advance-stage', lbl: 'Advance stage',            desc: 'Progress the 12-stage workflow',               scope: 'branch' },
      { key: 'loan.assign',        lbl: 'Assign team members',      desc: 'Advisor, processor, valuer, legal',            scope: 'branch' },
      { key: 'loan.disburse',      lbl: 'Approve disbursement',     desc: 'Final sign-off on cheque/NEFT release',        scope: 'org'    },
      { key: 'loan.delete',        lbl: 'Delete loan',              desc: 'Hard-delete loan files (auditable)',           scope: 'org'    },
    ]},
    { group: 'Customers', items: [
      { key: 'customer.create',    lbl: 'Create customer',          desc: 'New customer profile',                          scope: 'branch' },
      { key: 'customer.edit',      lbl: 'Edit customer',            desc: 'Basic info, contacts, employment',              scope: 'branch' },
      { key: 'customer.kyc',       lbl: 'Edit KYC documents',       desc: 'Upload/update PAN, Aadhaar, proof of address',  scope: 'branch' },
      { key: 'customer.verify',    lbl: 'Verify KYC',               desc: 'Approve KYC as verified',                       scope: 'branch' },
      { key: 'customer.merge',     lbl: 'Merge duplicates',         desc: 'Combine duplicate customer profiles',           scope: 'org'    },
      { key: 'customer.delete',    lbl: 'Delete customer',          desc: 'Remove customer (only if no loans)',            scope: 'org'    },
    ]},
    { group: 'Documents & DVR', items: [
      { key: 'document.upload',    lbl: 'Upload documents',         desc: 'Attach files to loans',                         scope: 'branch' },
      { key: 'document.verify',    lbl: 'Verify document',          desc: 'Mark documents as verified',                    scope: 'branch' },
      { key: 'document.delete',    lbl: 'Delete document',          desc: 'Remove uploaded files',                         scope: 'branch' },
      { key: 'dvr.log',            lbl: 'Log visit (DVR)',          desc: 'Submit field visit reports',                    scope: 'own'    },
      { key: 'dvr.view-all',       lbl: 'View all DVRs',            desc: 'See visits from entire team',                   scope: 'branch' },
      { key: 'dvr.edit',           lbl: 'Edit any DVR',             desc: 'Correct visit entries after submission',        scope: 'branch' },
    ]},
    { group: 'Tasks & Communication', items: [
      { key: 'task.create',        lbl: 'Create task',              desc: 'Assign follow-ups to self or others',           scope: 'branch' },
      { key: 'task.assign-others', lbl: 'Assign tasks to others',   desc: 'Create tasks for other team members',           scope: 'branch' },
      { key: 'notification.send',  lbl: 'Send reminders',           desc: 'WhatsApp/SMS/email reminders to customers',     scope: 'branch' },
      { key: 'comment.post',       lbl: 'Post comments',            desc: 'Internal notes on loan files',                  scope: 'branch' },
    ]},
    { group: 'Reports & Exports', items: [
      { key: 'report.view',        lbl: 'View reports',             desc: 'Funnel, MIS, bank-wise, advisor reports',       scope: 'branch' },
      { key: 'report.view-all',    lbl: 'View all-branch reports',  desc: 'Organization-wide analytics',                   scope: 'org'    },
      { key: 'report.export',      lbl: 'Export data',              desc: 'CSV/Excel export of loans, customers, DVRs',    scope: 'branch' },
    ]},
    { group: 'Administration', items: [
      { key: 'user.manage',        lbl: 'Manage users',             desc: 'Create, edit, deactivate users',                scope: 'org'    },
      { key: 'user.manage-branch', lbl: 'Manage branch users',      desc: 'Manage users within own branch only',           scope: 'branch' },
      { key: 'role.edit',          lbl: 'Edit roles & permissions', desc: 'Create and modify this very matrix',            scope: 'org'    },
      { key: 'settings.edit',      lbl: 'Edit system settings',     desc: 'Loan settings, quotation settings, branding',   scope: 'org'    },
      { key: 'audit.view',         lbl: 'View activity log',        desc: 'Full system audit trail',                       scope: 'org'    },
      { key: 'branch.manage',      lbl: 'Manage branches',          desc: 'Add/edit/close branches',                       scope: 'org'    },
    ]},
  ];

  const TOTAL = PERMISSIONS.reduce((n, g) => n + g.items.length, 0);

  function scopeBadge(s) {
    const colors = { org: '#c21e1e', branch: '#1566c0', region: '#6a1b9a', own: '#5f6368' };
    return `<span class="scope" style="color:${colors[s] || '#5f6368'};">${s}</span>`;
  }

  function render() {
    const host = document.getElementById('perm-groups');
    if (!host) return;
    host.innerHTML = PERMISSIONS.map((g, gi) => `
      <div class="perm-group" data-group="${g.group}">
        <div class="perm-group-hd" data-collapse>
          <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;flex:1;" onclick="event.stopPropagation()">
            <input type="checkbox" class="grp-check" data-group-check="${g.group}" style="width:16px;height:16px;accent-color:var(--accent);">
            <span class="t">${g.group}</span>
          </label>
          <span class="cnt"><span class="grp-n">0</span>/${g.items.length}</span>
        </div>
        <div class="perm-body">
          ${g.items.map(p => `
            <label class="perm-row" data-search="${(p.lbl + ' ' + p.key + ' ' + p.desc).toLowerCase()}">
              <input type="checkbox" class="perm-check" data-key="${p.key}" data-group="${g.group}">
              <div>
                <div class="lbl">${p.lbl}</div>
                <div class="desc">${p.desc} · <span class="font-mono" style="opacity:0.7;">${p.key}</span></div>
              </div>
              ${scopeBadge(p.scope)}
            </label>
          `).join('')}
        </div>
      </div>
    `).join('');

    document.getElementById('sel-n').textContent = '0';
    document.getElementById('perm-summary').textContent = `0 of ${TOTAL} selected`;
    document.querySelectorAll('.selected-count b')[1].textContent = TOTAL;
    wireEvents();
  }

  function updateCount() {
    const all = document.querySelectorAll('.perm-check');
    const selected = document.querySelectorAll('.perm-check:checked');
    document.getElementById('sel-n').textContent = selected.length;
    document.getElementById('perm-summary').textContent = `${selected.length} of ${all.length} selected`;
    // Per-group counts + indeterminate
    document.querySelectorAll('.perm-group').forEach(grp => {
      const checks = grp.querySelectorAll('.perm-check');
      const onCount = grp.querySelectorAll('.perm-check:checked').length;
      grp.querySelector('.grp-n').textContent = onCount;
      const grpCheck = grp.querySelector('.grp-check');
      grpCheck.checked = onCount === checks.length;
      grpCheck.indeterminate = onCount > 0 && onCount < checks.length;
    });
  }

  function wireEvents() {
    // Individual checkboxes
    document.querySelectorAll('.perm-check').forEach(c => {
      c.addEventListener('change', updateCount);
    });
    // Group master checkbox
    document.querySelectorAll('.grp-check').forEach(c => {
      c.addEventListener('change', e => {
        const grp = e.target.getAttribute('data-group-check');
        document.querySelectorAll(`.perm-check[data-group="${grp}"]`).forEach(pc => {
          pc.checked = e.target.checked;
        });
        updateCount();
      });
    });
    // Collapse header
    document.querySelectorAll('.perm-group-hd[data-collapse]').forEach(hd => {
      hd.addEventListener('click', e => {
        if (e.target.closest('input, label')) return;
        hd.closest('.perm-group').classList.toggle('collapsed');
      });
    });
  }

  function search() {
    const q = (document.getElementById('perm-search').value || '').toLowerCase().trim();
    document.querySelectorAll('.perm-row').forEach(row => {
      const match = !q || row.getAttribute('data-search').includes(q);
      row.style.display = match ? '' : 'none';
    });
    // hide empty groups
    document.querySelectorAll('.perm-group').forEach(g => {
      const anyVisible = [...g.querySelectorAll('.perm-row')].some(r => r.style.display !== 'none');
      g.style.display = anyVisible ? '' : 'none';
    });
  }

  function autoSlug() {
    const name = document.getElementById('f-name');
    const key = document.getElementById('f-key');
    name.addEventListener('input', () => {
      if (!key.dataset.touched) {
        key.value = name.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
      }
    });
    key.addEventListener('input', () => { key.dataset.touched = '1'; });
  }

  function wireActions() {
    const create = (id) => {
      const name = document.getElementById('f-name').value.trim();
      if (!name) {
        (window.shfToast || alert)('Role name is required', { icon: '⚠' });
        document.getElementById('f-name').focus();
        return;
      }
      const n = document.querySelectorAll('.perm-check:checked').length;
      if (n === 0) {
        if (!confirm('No permissions selected. Create role anyway?')) return;
      }
      (window.shfToast || alert)(`Role "${name}" created with ${n} permissions`, { icon: '✓' });
      setTimeout(() => { location.href = 'roles.html'; }, 700);
    };
    document.getElementById('btn-create').addEventListener('click', create);
    document.getElementById('btn-create-2').addEventListener('click', create);
    document.getElementById('btn-save-draft').addEventListener('click', () => {
      (window.shfToast || alert)('Draft saved', { icon: '💾' });
    });

    document.getElementById('btn-check-all').addEventListener('click', () => {
      document.querySelectorAll('.perm-check').forEach(c => c.checked = true);
      updateCount();
    });
    document.getElementById('btn-uncheck-all').addEventListener('click', () => {
      document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
      updateCount();
    });
    document.getElementById('btn-expand-all').addEventListener('click', () => {
      document.querySelectorAll('.perm-group').forEach(g => g.classList.remove('collapsed'));
    });
    document.getElementById('btn-collapse-all').addEventListener('click', () => {
      document.querySelectorAll('.perm-group').forEach(g => g.classList.add('collapsed'));
    });
    document.getElementById('perm-search').addEventListener('input', search);
  }

  function init() {
    // Prefill from URL (?role=branch_manager)
    const params = new URLSearchParams(location.search);
    const editKey = params.get('role');
    if (editKey) {
      document.getElementById('page-title').textContent = 'Edit role';
      document.getElementById('page-sub').textContent = 'Update name, description, or permissions for this role.';
      document.getElementById('btn-create').textContent = 'Save changes';
      document.getElementById('btn-create-2').textContent = 'Save changes';
      document.getElementById('f-name').value = editKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      document.getElementById('f-key').value = editKey;
      document.getElementById('f-key').dataset.touched = '1';
    }
    render();
    autoSlug();
    wireActions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
