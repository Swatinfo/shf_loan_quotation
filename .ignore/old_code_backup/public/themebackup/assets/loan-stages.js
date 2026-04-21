// Per-stage body renderer for loan-show.html
// Switches #stageBody content based on clicked stage in #stageRail

(function () {
  'use strict';

  const S = {
    1: {
      title: 'Inquiry',
      status: { label: 'Completed · Mar 28', cls: 'green dot' },
      body: `
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>Source of inquiry</label><select class="select"><option>Walk-in</option><option selected>Referral — existing customer</option><option>Advisor connect</option><option>Digital lead</option></select></div>
          <div class="field"><label>Referred by</label><input class="input" value="Mitul Shah · SHF-2024-0221"></div>
          <div class="field"><label>Loan purpose</label><select class="select"><option>Home — Purchase</option><option selected>Home — Construction</option><option>Home — Extension</option><option>Plot + Construction</option></select></div>
          <div class="field"><label>Indicative amount</label><div class="input-affix"><span class="pre">₹</span><input class="input" value="55,00,000"></div></div>
          <div class="field"><label>Indicative tenure</label><div class="input-affix"><input class="input" value="20"><span class="post">yr</span></div></div>
          <div class="field"><label>First discussion</label><input type="date" class="input" value="2026-03-28"></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div class="field"><label>Customer background &amp; need</label><textarea class="input" rows="3">Construction of ground + 1 residential unit on plot owned since 2019. Partnership income. Own contribution 23% ready.</textarea></div>
      `,
      prev: null,
      next: 'Doc Selection',
    },
    2: {
      title: 'Document Selection',
      status: { label: 'Completed · Mar 29', cls: 'green dot' },
      body: `
        <p class="text-muted" style="margin-top:0;">Checklist picked based on loan type &amp; borrower profile. 21 items total.</p>
        <div class="grid c2" style="gap:16px;">
          <div class="field"><label>Applicant type</label><select class="select"><option selected>Self-employed · Partnership</option><option>Salaried</option><option>Professional</option><option>NRI</option></select></div>
          <div class="field"><label>Property type</label><select class="select"><option selected>Plot + construction</option><option>Ready apartment</option><option>Under-construction</option><option>Re-sale</option></select></div>
          <div class="field"><label>Co-applicant</label><select class="select"><option selected>None</option><option>Spouse</option><option>Parent</option></select></div>
          <div class="field"><label>Checklist template</label><select class="select"><option selected>HDFC — SE Construction v3.2</option><option>Custom</option></select></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <div class="stat-cell"><div class="l">KYC</div><div class="v">4 docs</div></div>
          <div class="stat-cell"><div class="l">Income</div><div class="v">5 docs</div></div>
          <div class="stat-cell"><div class="l">Property</div><div class="v">7 docs</div></div>
          <div class="stat-cell"><div class="l">Fees</div><div class="v">1 doc</div></div>
          <div class="stat-cell"><div class="l">Security</div><div class="v">1 doc</div></div>
          <div class="stat-cell"><div class="l">Post-disb.</div><div class="v">3 docs</div></div>
        </div>
      `,
      prev: 'Inquiry',
      next: 'Doc Collection',
    },
    3: {
      title: 'Document Collection',
      status: { label: 'Completed · Apr 02', cls: 'green dot' },
      body: `
        <div class="grid c4" style="gap:14px;margin-bottom:14px;">
          <div class="stat-cell"><div class="l">Collected</div><div class="v tnum">14 / 21</div></div>
          <div class="stat-cell"><div class="l">Verified</div><div class="v tnum">12 / 14</div></div>
          <div class="stat-cell"><div class="l">Pending</div><div class="v tnum">4</div></div>
          <div class="stat-cell"><div class="l">Overdue</div><div class="v tnum">3</div></div>
        </div>
        <table class="tbl">
          <thead><tr><th>Category</th><th>Progress</th><th class="num">Items</th><th>Owner</th></tr></thead>
          <tbody>
            <tr><td>KYC</td><td><span class="badge green sq">Complete</span></td><td class="num">4/4</td><td>Neha S.</td></tr>
            <tr><td>Income</td><td><span class="badge amber sq">1 pending</span></td><td class="num">4/5</td><td>Rahul J.</td></tr>
            <tr><td>Property</td><td><span class="badge amber sq">2 pending</span></td><td class="num">5/7</td><td>Rahul J.</td></tr>
            <tr><td>Fees</td><td><span class="badge green sq">Complete</span></td><td class="num">1/1</td><td>Neha S.</td></tr>
            <tr><td>Security (PDCs)</td><td><span class="badge sq" style="background:var(--paper-2)">Not yet</span></td><td class="num">0/1</td><td>Neha S.</td></tr>
          </tbody>
        </table>
        <div class="callout mt-4">
          <svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <div><strong>Go to Documents tab</strong> to see the full 21-document checklist and chase missing items.</div>
        </div>
      `,
      prev: 'Doc Selection',
      next: 'Parallel Processing',
    },
    4: {
      title: 'Parallel Processing',
      status: { label: 'Completed · Apr 06', cls: 'green dot' },
      body: `
        <p class="text-muted" style="margin-top:0;">Five parallel checks run together — legal, technical, CIBIL, FI, RCU.</p>
        <div class="parallel" style="border:1px solid var(--line);border-radius:8px;">
          <div class="pp-row"><div class="pp-l"><span class="dot green"></span>Legal opinion</div><div class="pp-r tnum">₹ 3,500 · Mehul Adv.</div><div class="pp-s"><span class="badge green sq">Clear</span></div></div>
          <div class="pp-row"><div class="pp-l"><span class="dot green"></span>Technical / valuation</div><div class="pp-r tnum">₹ 65,00,000 · Est Value Co.</div><div class="pp-s"><span class="badge green sq">Report in</span></div></div>
          <div class="pp-row"><div class="pp-l"><span class="dot green"></span>CIBIL / bureau check</div><div class="pp-r">Score 748</div><div class="pp-s"><span class="badge green sq">Pass</span></div></div>
          <div class="pp-row"><div class="pp-l"><span class="dot green"></span>Field investigation</div><div class="pp-r">Residence + Office</div><div class="pp-s"><span class="badge green sq">Positive</span></div></div>
          <div class="pp-row"><div class="pp-l"><span class="dot amber"></span>RCU (fraud check)</div><div class="pp-r">All docs</div><div class="pp-s"><span class="badge amber sq">1 referral</span></div></div>
        </div>
        <div class="callout amber mt-4">
          <svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z"/></svg>
          <div><strong>Valuation variance</strong> — Technical report ₹65L vs Bank's internal estimate ₹63L. Noted; does not block sanction.</div>
        </div>
      `,
      prev: 'Doc Collection',
      next: 'Rate & PF',
    },
    5: null, // already inlined as the default view — handled specially
    6: {
      title: 'Sanction Letter',
      status: { label: 'Pending · bank action', cls: 'amber dot' },
      body: `
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>Expected sanction date</label><input type="date" class="input" value="2026-04-18"></div>
          <div class="field"><label>Bank reference</label><input class="input" value="HDFC-AHM-0879421"></div>
          <div class="field"><label>Sanctioned amount</label><div class="input-affix"><span class="pre">₹</span><input class="input" placeholder="Awaiting letter"></div></div>
          <div class="field"><label>Validity</label><select class="select"><option>3 months</option><option selected>6 months</option></select></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div class="field"><label>Conditions precedent (from bank)</label><textarea class="input" rows="4" placeholder="Will be filled once letter is received…"></textarea></div>
        <div class="callout mt-4"><svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg><div>Upload the sanction letter PDF here once received. It unlocks Docket Login (Stage 7).</div></div>
        <div class="mt-3"><button class="btn primary">Upload sanction letter</button></div>
      `,
      prev: 'Rate & PF',
      next: 'Docket Login',
    },
    7: {
      title: 'Docket Login',
      status: { label: 'Not started', cls: 'sq' },
      body: `
        <p class="text-muted" style="margin-top:0;">Submit the physical docket (all originals + sanction letter copy) to the bank. Track acknowledgement ID.</p>
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>Docket number</label><input class="input" placeholder="Auto-generated on login"></div>
          <div class="field"><label>Submitted on</label><input type="date" class="input"></div>
          <div class="field"><label>Submitted to</label><input class="input" value="Saurabh Gandhi · HDFC RM"></div>
          <div class="field"><label>Acknowledgement ref</label><input class="input" placeholder="Bank ack #"></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <label class="label">Docket contents checklist</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;margin-top:8px;">
          <label class="flex items-center gap-2"><input type="checkbox"> Original sale deed chain</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Index-II (original)</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Sanctioned plan</label>
          <label class="flex items-center gap-2"><input type="checkbox"> NA order</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Property tax receipt</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Loan agreement (signed)</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Security PDCs · 6</label>
          <label class="flex items-center gap-2"><input type="checkbox"> Declarations &amp; undertakings</label>
        </div>
      `,
      prev: 'Sanction Letter',
      next: 'KFS',
    },
    8: {
      title: 'KFS — Key Facts Statement',
      status: { label: 'Not started', cls: 'sq' },
      body: `
        <p class="text-muted" style="margin-top:0;">Regulatory 1-pager required by RBI before execution. Must be acknowledged by customer.</p>
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>APR (Annual Percentage Rate)</label><div class="input-affix"><input class="input" value="8.72"><span class="post">%</span></div><div class="text-xs text-muted mt-1">Includes PF + fees</div></div>
          <div class="field"><label>Total repayment</label><div class="input-affix"><span class="pre">₹</span><input class="input" value="1,13,49,360"></div></div>
          <div class="field"><label>Customer ack mode</label><select class="select"><option>Physical signature</option><option selected>Aadhaar eSign</option><option>OTP (digital)</option></select></div>
          <div class="field"><label>KFS sent on</label><input type="date" class="input"></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div class="callout"><svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v2m0-2h2m-2 0h-2m9-5a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div>Customer must acknowledge KFS <strong>before</strong> E-Sign step. Mandatory cool-off: 3 business days.</div></div>
        <div class="mt-3" style="display:flex;gap:8px;"><button class="btn">Generate KFS PDF</button><button class="btn primary">Send to customer</button></div>
      `,
      prev: 'Docket Login',
      next: 'E-Sign & eNACH',
    },
    9: {
      title: 'E-Sign &amp; eNACH',
      status: { label: 'Not started', cls: 'sq' },
      body: `
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>Loan agreement — e-Sign</label><select class="select"><option selected>Aadhaar OTP</option><option>DSC</option><option>Physical signature</option></select></div>
          <div class="field"><label>eNACH bank</label><select class="select"><option selected>HDFC Bank · SB ****4421</option><option>Add new account</option></select></div>
          <div class="field"><label>EMI amount</label><div class="input-affix"><span class="pre">₹</span><input class="input" value="47,289"></div></div>
          <div class="field"><label>EMI date</label><select class="select"><option>1st of each month</option><option selected>5th of each month</option><option>10th</option></select></div>
          <div class="field"><label>Mandate start</label><input type="date" class="input" value="2026-05-05"></div>
          <div class="field"><label>Mandate end</label><input type="date" class="input" value="2046-04-05"></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <div class="stat-cell"><div class="l">Loan agmt</div><div class="v"><span class="badge sq" style="background:var(--paper-2)">Draft</span></div></div>
          <div class="stat-cell"><div class="l">eSign status</div><div class="v"><span class="badge sq" style="background:var(--paper-2)">Not sent</span></div></div>
          <div class="stat-cell"><div class="l">eNACH</div><div class="v"><span class="badge sq" style="background:var(--paper-2)">Not registered</span></div></div>
        </div>
      `,
      prev: 'KFS',
      next: 'Disbursement',
    },
    10: {
      title: 'Disbursement',
      status: { label: 'Not started', cls: 'sq' },
      body: `
        <div class="grid c2" style="gap:18px;">
          <div class="field"><label>Mode</label><select class="select"><option>Single shot</option><option selected>Phased (construction)</option></select></div>
          <div class="field"><label>Disbursement account</label><select class="select"><option>Vendor · Builder A/C</option><option selected>Applicant's A/C — HDFC SB ****4421</option></select></div>
        </div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <table class="tbl">
          <thead><tr><th>Phase</th><th>Trigger</th><th class="num">Amount</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>1 · Plinth</td><td>On e-sign</td><td class="num tnum">₹ 18,00,000</td><td><span class="badge sq" style="background:var(--paper-2)">Pending</span></td></tr>
            <tr><td>2 · Slab</td><td>On architect certificate</td><td class="num tnum">₹ 18,00,000</td><td><span class="badge sq" style="background:var(--paper-2)">Pending</span></td></tr>
            <tr><td>3 · Finishing</td><td>On 70% complete</td><td class="num tnum">₹ 14,00,000</td><td><span class="badge sq" style="background:var(--paper-2)">Pending</span></td></tr>
            <tr><td>4 · Possession</td><td>On completion</td><td class="num tnum">₹ 5,00,000</td><td><span class="badge sq" style="background:var(--paper-2)">Pending</span></td></tr>
          </tbody>
        </table>
      `,
      prev: 'E-Sign & eNACH',
      next: 'OTC Clearance',
    },
    11: {
      title: 'OTC Clearance',
      status: { label: 'Not started', cls: 'sq' },
      body: `
        <p class="text-muted" style="margin-top:0;">Post-disbursement items still pending. Typically: original NOC, insurance, completion certificate.</p>
        <table class="tbl">
          <thead><tr><th>Item</th><th>Deadline</th><th>Owner</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>Vendor NOC (original)</td><td>30 days post-disb.</td><td>Rahul J.</td><td><span class="badge sq" style="background:var(--paper-2)">Not yet</span></td></tr>
            <tr><td>Property insurance</td><td>With 1st EMI</td><td>Neha S.</td><td><span class="badge sq" style="background:var(--paper-2)">Not yet</span></td></tr>
            <tr><td>Completion certificate</td><td>On handover</td><td>Rahul J.</td><td><span class="badge sq" style="background:var(--paper-2)">Not yet</span></td></tr>
          </tbody>
        </table>
      `,
      prev: 'Disbursement',
      next: 'Completed',
    },
    12: {
      title: 'Completed',
      status: { label: 'Pending', cls: 'sq' },
      body: `
        <div class="callout"><svg class="i" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg><div>All OTC items cleared, mandate active, first EMI debited on time. File is ready to be closed.</div></div>
        <hr style="border:none;border-top:1px dashed var(--line);margin:18px 0;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
          <div class="stat-cell"><div class="l">Total elapsed</div><div class="v">—</div></div>
          <div class="stat-cell"><div class="l">EMIs paid</div><div class="v">—</div></div>
          <div class="stat-cell"><div class="l">Commission</div><div class="v">—</div></div>
          <div class="stat-cell"><div class="l">NPS given</div><div class="v">—</div></div>
        </div>
        <div class="mt-4"><button class="btn primary">Mark file closed</button></div>
      `,
      prev: 'OTC Clearance',
      next: null,
    },
  };

  function cardTemplate(n, data) {
    const footerPrev = data.prev ? `<button class="btn ghost">← Back: ${data.prev}</button>` : '<span></span>';
    const footerNext = data.next
      ? `<button class="btn primary" data-action="toast" data-arg="Mark complete → ${data.next}">Mark complete → ${data.next}</button>`
      : '';
    return `
      <div class="card" data-stage-body="${n}">
        <div class="card-hd">
          <div class="t"><span class="num">${n}</span>${data.title}</div>
          <div class="actions"><span class="badge ${data.status.cls}">${data.status.label}</span></div>
        </div>
        <div class="card-bd">${data.body}</div>
        <div class="card-ft">
          ${footerPrev}
          <div style="margin-left:auto;display:flex;gap:8px;">
            <button class="btn" data-action="toast" data-arg="Saved">Save progress</button>
            ${footerNext}
          </div>
        </div>
      </div>
    `;
  }

  function setActive(n) {
    const rail = document.getElementById('stageRail');
    if (!rail) return;
    rail.querySelectorAll('.stg').forEach(s => {
      s.classList.toggle('active', String(s.dataset.stage) === String(n));
    });

    const body = document.getElementById('stageBody');
    if (!body) return;

    // Keep original Rate & PF DOM intact for stage 5 (richest content)
    const original5 = body.querySelector('[data-stage-body="5"]');

    if (String(n) === '5' && original5) {
      body.innerHTML = '';
      body.appendChild(original5);
      return;
    }

    const data = S[n];
    if (!data) return;
    body.innerHTML = cardTemplate(n, data);
  }

  function init() {
    const rail = document.getElementById('stageRail');
    if (!rail) return;
    // Cache the original stage-5 card so we can restore it
    const body = document.getElementById('stageBody');
    if (body) {
      const firstCard = body.querySelector('.card');
      if (firstCard && !firstCard.hasAttribute('data-stage-body')) {
        firstCard.setAttribute('data-stage-body', '5');
      }
    }

    rail.addEventListener('click', (e) => {
      const stg = e.target.closest('.stg');
      if (!stg) return;
      e.preventDefault();
      const n = parseInt(stg.dataset.stage, 10);
      if (!n) return;
      setActive(n);
      // Smooth scroll into view
      const body = document.getElementById('stageBody');
      if (body) body.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });

    // Honor ?stage=N
    const params = new URLSearchParams(location.search);
    const q = parseInt(params.get('stage'), 10);
    if (q >= 1 && q <= 12) setActive(q);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
