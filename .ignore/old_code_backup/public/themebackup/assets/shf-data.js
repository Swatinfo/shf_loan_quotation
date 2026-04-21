/**
 * SHF Demo Data — mirror of production seed + config/app-defaults.php
 *
 * Single source of truth for demo content across all newtheme HTML pages.
 * Keep this aligned with:
 *   - config/app-defaults.php          (banks, tenures, IOM, GST, DVR vocab, reasons)
 *   - database/seeders/DefaultDataSeeder.php (stages 1–17 with sub_actions, roles)
 *   - App\Models\LoanDetail::STATUS_LABELS / CUSTOMER_TYPE_LABELS
 *   - App\Models\Role::gujaratiLabels()
 *   - App\Models\GeneralTask::PRIORITY_LABELS / STATUS_LABELS
 *
 * Expose everything under window.SHF_DATA.
 */
(function () {
  window.SHF_DATA = {

    /* ===== Company (config/app-defaults.php) ===== */
    company: {
      name: 'Shreenathji Home Finance',
      address: 'Office No 911, R K Prime, Circle, next to Silver Heights, Nehru Nagar Co-operative Society, Nana Mava, Rajkot, Gujarat 360004',
      phone: '+91 99747 89089',
      email: 'info@shf.com',
    },

    /* ===== Banks — matches config/app-defaults.php 'banks' exactly.
       bg/fg use real brand colors (public logo palette) for the bank-name chip. ===== */
    banks: [
      { id: 1, code: 'HDFC',  name: 'HDFC Bank',           letter: 'H', bg: '#004C8F', fg: '#ffffff' },
      { id: 2, code: 'ICICI', name: 'ICICI Bank',          letter: 'I', bg: '#F37E20', fg: '#ffffff' },
      { id: 3, code: 'AXIS',  name: 'Axis Bank',           letter: 'A', bg: '#97144D', fg: '#ffffff' },
      { id: 4, code: 'KOTAK', name: 'Kotak Mahindra Bank', letter: 'K', bg: '#fa1432', fg: '#ffffff' },
    ],

    /* ===== Branches (demo — real branches live in the branches table, seeded per-deployment) ===== */
    branches: [
      { id: 1, name: 'Rajkot (HQ)', code: 'RJT' },
      { id: 2, name: 'Ahmedabad',   code: 'AMD' },
      { id: 3, name: 'Surat',       code: 'SRT' },
    ],

    /* ===== Products — matches DefaultDataSeeder::seedProducts() exactly.
       Products are BANK-SPECIFIC (products.bank_id FK in DB). ===== */
    products: [
      // HDFC (bank_id = 1)
      { id: 1,  bank_id: 1, name: 'Home Loan' },
      { id: 2,  bank_id: 1, name: 'LAP' },
      // ICICI (bank_id = 2)
      { id: 3,  bank_id: 2, name: 'Home Loan' },
      { id: 4,  bank_id: 2, name: 'LAP' },
      { id: 5,  bank_id: 2, name: 'OD' },
      { id: 6,  bank_id: 2, name: 'PRATHAM' },
      // Axis (bank_id = 3)
      { id: 7,  bank_id: 3, name: 'Home Loan' },
      { id: 8,  bank_id: 3, name: 'LAP' },
      { id: 9,  bank_id: 3, name: 'ASHA' },
      // Kotak (bank_id = 4)
      { id: 10, bank_id: 4, name: 'Home Loan' },
      { id: 11, bank_id: 4, name: 'LAP' },
    ],

    // Helper: return products for a given bank_id
    productsForBank: function (bankId) {
      return this.products.filter(function (p) { return p.bank_id === bankId; });
    },

    /* ===== Customer types (LoanDetail::CUSTOMER_TYPE_LABELS) ===== */
    customerTypes: [
      { key: 'proprietor',      label_en: 'Proprietor' },
      { key: 'partnership_llp', label_en: 'Partnership / LLP' },
      { key: 'pvt_ltd',         label_en: 'Private Limited' },
      { key: 'salaried',        label_en: 'Salaried' },
    ],

    /* ===== Loan statuses (LoanDetail::STATUS_LABELS) ===== */
    loanStatuses: [
      { key: 'active',    label_en: 'Active',     color: 'green' },
      { key: 'completed', label_en: 'Completed',    color: 'gray'  },
      { key: 'rejected',  label_en: 'Rejected', color: 'red'   },
      { key: 'cancelled', label_en: 'Cancelled',      color: 'red'   },
      { key: 'on_hold',   label_en: 'On hold',   color: 'amber' },
    ],

    /* ===== Quotation statuses ===== */
    quotationStatuses: [
      { key: 'active',    label_en: 'Active',    color: 'green' },
      { key: 'converted', label_en: 'Converted', color: 'blue' },
      { key: 'on_hold',   label_en: 'On hold',   color: 'amber' },
      { key: 'cancelled', label_en: 'Cancelled',      color: 'red'   },
    ],

    /* ===== Roles (7-role system from App\Models\Role) ===== */
    roles: [
      { slug: 'super_admin',    label_en: 'Super Admin',          can_be_advisor: false, chip: 'super-admin' },
      { slug: 'admin',          label_en: 'Admin',               can_be_advisor: false, chip: 'admin' },
      { slug: 'branch_manager', label_en: 'Branch Manager',     can_be_advisor: true,  chip: 'branch-manager' },
      { slug: 'bdh',            label_en: 'BDH', can_be_advisor: true,  chip: 'bdh' },
      { slug: 'loan_advisor',   label_en: 'Loan Advisor',        can_be_advisor: true,  chip: 'loan-advisor' },
      { slug: 'bank_employee',  label_en: 'Bank Employee',       can_be_advisor: false, chip: 'bank-employee' },
      { slug: 'office_employee',label_en: 'Office Employee',      can_be_advisor: false, chip: 'office-employee' },
    ],

    /* ===== Stages 1-17 (from DefaultDataSeeder.php). Multi-phase entries have phases[]. ===== */
    stages: [
      { n: 1,  key: 'inquiry',             label_en: 'Loan Inquiry',          sequence: 1,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor'],                               phases: null },
      { n: 2,  key: 'document_selection',  label_en: 'Document Selection',     sequence: 2,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor'],                               phases: null },
      { n: 3,  key: 'document_collection', label_en: 'Document Collection',     sequence: 3,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor'],                               phases: null },
      { n: 4,  key: 'parallel_processing', label_en: 'Parallel Processing',    sequence: 4,  parent: null,                 type: 'parallel',   default_roles: [],                                                              phases: null },
      { n: 4.1,key: 'app_number',          label_en: 'Application Number',         sequence: 4,  parent: 'parallel_processing',type: 'sequential', default_roles: ['branch_manager','loan_advisor'],                               phases: null },
      { n: 4.2,key: 'bsm_osv',             label_en: 'BSM / OSV Approval',    sequence: 4,  parent: 'parallel_processing',type: 'sequential', default_roles: ['bank_employee'],                                               phases: null },
      { n: 4.3,key: 'legal_verification',  label_en: 'Legal Verification',       sequence: 4,  parent: 'parallel_processing',type: 'sequential', default_roles: ['branch_manager','loan_advisor','bank_employee'],               phases: [
        { idx: 0, label_en: 'Raise legal query', role: 'task_owner' },
        { idx: 1, label_en: 'Bank legal opinion', role: 'bank_employee' },
        { idx: 2, label_en: 'Close legal',           role: 'task_owner' },
      ]},
      { n: 4.4,key: 'property_valuation',  label_en: 'Property Valuation',    sequence: 4,  parent: 'parallel_processing',type: 'sequential', default_roles: ['branch_manager','office_employee'],                            phases: null },
      { n: 4.5,key: 'technical_valuation', label_en: 'Technical Valuation',  sequence: 4,  parent: 'parallel_processing',type: 'sequential', default_roles: ['branch_manager','office_employee'],                            phases: [
        { idx: 0, label_en: 'Request valuation',  role: 'task_owner' },
        { idx: 1, label_en: 'Valuer report', role: 'office_employee' },
      ]},
      { n: 5,  key: 'rate_pf',             label_en: 'Rate & PF Request',sequence: 5,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','bank_employee'],               phases: [
        { idx: 0, label_en: 'Request rate / PF', role: 'task_owner' },
        { idx: 1, label_en: 'Bank offer',          role: 'bank_employee' },
        { idx: 2, label_en: 'Customer acceptance', role: 'task_owner' },
      ]},
      { n: 6,  key: 'sanction',            label_en: 'Sanction Letter',         sequence: 6,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','bank_employee'],               phases: [
        { idx: 0, label_en: 'Request sanction',   role: 'task_owner' },
        { idx: 1, label_en: 'Bank issues sanction',role: 'bank_employee' },
        { idx: 2, label_en: 'Acknowledge sanction', role: 'task_owner' },
      ]},
      { n: 7,  key: 'docket',              label_en: 'Docket Login',         sequence: 7,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','office_employee'],             phases: [
        { idx: 0, label_en: 'Compile docket',role: 'task_owner' },
        { idx: 1, label_en: 'Office review',    role: 'office_employee' },
        { idx: 2, label_en: 'Docket login',        role: 'task_owner' },
      ]},
      { n: 8,  key: 'kfs',                 label_en: 'KFS Generation',          sequence: 8,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','office_employee'],             phases: null },
      { n: 9,  key: 'esign',               label_en: 'E-Sign & eNACH',   sequence: 9,  parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','bank_employee'],               phases: [
        { idx: 0, label_en: 'Schedule e-sign',   role: 'task_owner' },
        { idx: 1, label_en: 'E-sign completed',     role: 'task_owner' },
        { idx: 2, label_en: 'eNACH mandate',    role: 'bank_employee' },
        { idx: 3, label_en: 'Confirm agreement',       role: 'task_owner' },
      ]},
      { n: 10, key: 'disbursement',        label_en: 'Disbursement',              sequence: 10, parent: null,                 type: 'decision',   default_roles: ['branch_manager','loan_advisor','office_employee'],             phases: null },
      { n: 11, key: 'otc_clearance',       label_en: 'OTC Clearance',    sequence: 11, parent: null,                 type: 'sequential', default_roles: ['branch_manager','loan_advisor','office_employee'],             phases: null },
    ],

    /* ===== Feature flags ===== */
    flags: {
      OPEN_RATE_PF_PARALLEL: true, // env OPEN_RATE_PF_PARALLEL=1 in app.php
    },

    /* ===== Tenures (years) ===== */
    tenures: [5, 10, 15, 20],

    /* ===== IOM / GST ===== */
    iom: { thresholdAmount: 10000000, fixedCharge: 5500, percentageAbove: 0.35 },
    gstPercent: 18,

    /* ===== Services (ourServices from app-defaults) ===== */
    services: ['Home Loan','Mortgage Loan','Commercial Loan','Industrial Loan','Land Loan','Over Draft (OD)'],

    /* ===== Documents by customer type (EN) — from config/app-defaults.php ===== */
    documents_en: {
      proprietor:      ['PAN Card of Proprietor','Aadhaar Card of Proprietor','Business Address Proof','Bank Statement (12 months)','ITR (Last 3 years)','GST Registration Certificate','Shop & Establishment Certificate','Property Documents (if applicable)','Udyam Registration Certificate','Passport Size Photographs'],
      partnership_llp: ['Partnership Deed / LLP Agreement','PAN Card of Firm / LLP','PAN Card of All Partners','Aadhaar Card of All Partners','Bank Statement (12 months)','ITR of Firm (Last 3 years)','ITR of Partners (Last 3 years)','GST Registration Certificate','Certificate of Incorporation (LLP)','Board Resolution / Authority Letter','Business Address Proof','Passport Size Photographs of All Partners'],
      pvt_ltd:         ['Certificate of Incorporation','Memorandum of Association (MOA)','Articles of Association (AOA)','PAN Card of Company','PAN Card of All Directors','Aadhaar Card of All Directors','Board Resolution for Loan','Bank Statement (12 months)','Audited Financials (Last 3 years)','ITR of Company (Last 3 years)','ITR of Directors (Last 3 years)','GST Registration Certificate','Business Address Proof','Passport Size Photographs of All Directors'],
      salaried:        ['PAN Card','Aadhaar Card','Salary Slips (Last 3 months)','Bank Statement (12 months)','ITR (Last 2 years)','Form 16','Employment / Appointment Letter','ID Card of Company','Property Documents (if applicable)','Passport Size Photographs'],
    },
    documents_gu: {
      proprietor:      ['માલિકનું PAN કાર્ડ','માલિકનું આધાર કાર્ડ','વ્યવસાય સરનામાનો પુરાવો','બેંક સ્ટેટમેન્ટ (૧૨ મહિના)','ITR (છેલ્લા ૩ વર્ષ)','GST નોંધણી પ્રમાણપત્ર','દુકાન અને સ્થાપના પ્રમાણપત્ર','મિલકતના દસ્તાવેજો (જો લાગુ હોય)','ઉદ્યમ નોંધણી પ્રમાણપત્ર','પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
      partnership_llp: ['ભાગીદારી દસ્તાવેજ / LLP કરાર','ફર્મ / LLP નું PAN કાર્ડ','બધા ભાગીદારોનું PAN કાર્ડ','બધા ભાગીદારોનું આધાર કાર્ડ','બેંક સ્ટેટમેન્ટ (૧૨ મહિના)','ફર્મનું ITR (છેલ્લા ૩ વર્ષ)','ભાગીદારોનું ITR (છેલ્લા ૩ વર્ષ)','GST નોંધણી પ્રમાણપત્ર','ઇન્કોર્પોરેશન પ્રમાણપત્ર (LLP)','બોર્ડ ઠરાવ / અધિકૃત પત્ર','વ્યવસાય સરનામાનો પુરાવો','બધા ભાગીદારોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
      pvt_ltd:         ['ઇન્કોર્પોરેશન પ્રમાણપત્ર','મેમોરેન્ડમ ઑફ એસોસિએશન (MOA)','આર્ટિકલ્સ ઑફ એસોસિએશન (AOA)','કંપનીનું PAN કાર્ડ','બધા ડિરેક્ટરોનું PAN કાર્ડ','બધા ડિરેક્ટરોનું આધાર કાર્ડ','લોન માટે બોર્ડ ઠરાવ','બેંક સ્ટેટમેન્ટ (૧૨ મહિના)','ઑડિટેડ નાણાકીય (છેલ્લા ૩ વર્ષ)','કંપનીનું ITR (છેલ્લા ૩ વર્ષ)','ડિરેક્ટરોનું ITR (છેલ્લા ૩ વર્ષ)','GST નોંધણી પ્રમાણપત્ર','વ્યવસાય સરનામાનો પુરાવો','બધા ડિરેક્ટરોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
      salaried:        ['PAN કાર્ડ','આધાર કાર્ડ','સેલેરી સ્લિપ (છેલ્લા ૩ મહિના)','બેંક સ્ટેટમેન્ટ (૧૨ મહિના)','ITR (છેલ્લા ૨ વર્ષ)','ફોર્મ ૧૬','નોકરી / નિમણૂક પત્ર','કંપનીનું ID કાર્ડ','મિલકતના દસ્તાવેજો (જો લાગુ હોય)','પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
    },

    /* ===== DVR vocab (config/app-defaults.php) ===== */
    dvrContactTypes: [
      { key: 'existing_customer', label_en: 'Existing Customer' },
      { key: 'new_customer',      label_en: 'New Customer' },
      { key: 'ca',                label_en: 'CA' },
      { key: 'builder',           label_en: 'Builder / Developer' },
      { key: 'dsa',               label_en: 'DSA / Connector' },
      { key: 'other',             label_en: 'Other' },
    ],
    dvrPurposes: [
      { key: 'new_lead',            label_en: 'New Lead' },
      { key: 'follow_up',           label_en: 'Follow-up' },
      { key: 'document_collection', label_en: 'Document Collection' },
      { key: 'quotation_delivery',  label_en: 'Quotation Delivery' },
      { key: 'payment',             label_en: 'Payment / Disbursement' },
      { key: 'relationship',        label_en: 'Relationship' },
      { key: 'other',               label_en: 'Other' },
    ],

    /* ===== General task priorities (App\Models\GeneralTask) ===== */
    taskPriorities: [
      { key: 'low',    label_en: 'Low',   color: 'gray'  },
      { key: 'normal', label_en: 'Normal', color: 'blue'  },
      { key: 'high',   label_en: 'High',    color: 'amber' },
      { key: 'urgent', label_en: 'Urgent',  color: 'red'   },
    ],
    taskStatuses: [
      { key: 'pending',    label_en: 'Pending',   color: 'gray'  },
      { key: 'in_progress',label_en: 'In Progress',   color: 'blue'  },
      { key: 'completed',  label_en: 'Completed',  color: 'green' },
      { key: 'cancelled',  label_en: 'Cancelled',     color: 'red'   },
    ],

    /* ===== Permission groups (44 perms × 7 groups). Label-only — slugs align with config/permissions.php. ===== */
    permissionGroups: [
      { group: 'Settings',   perms: ['view_settings','edit_settings','manage_banks','manage_documents','manage_workflow_config','manage_dvr_config','manage_reasons_config','manage_company'] },
      { group: 'Quotations',   perms: ['create_quotation','view_own_quotations','view_all_quotations','edit_quotation','delete_quotation','hold_quotation','cancel_quotation','resume_quotation'] },
      { group: 'Users',perms: ['view_users','create_user','edit_user','delete_user','impersonate_user'] },
      { group: 'Loans',       perms: ['view_loans','view_all_loans','create_loan','edit_loan','delete_loan','advance_stage','transfer_stage','raise_query','resolve_query','view_documents','manage_documents','disburse_loan','manage_otc','view_timeline'] },
      { group: 'Tasks',     perms: ['view_all_tasks'] },
      { group: 'DVR',       perms: ['view_dvr','create_dvr','edit_dvr','delete_dvr','view_all_dvr'] },
      { group: 'System',   perms: ['view_activity_log','view_reports','manage_roles'] },
    ],

    /* ===== Indian first-name + last-name pools (for realistic demo rows) ===== */
    names: {
      first: ['Priya','Anil','Rashmika','Kiran','Jayesh','Dhaval','Shilpa','Nirav','Meera','Kavya','Pooja','Ritesh','Kunal','Sneha','Vipul','Rahul','Anita','Deepa','Rohan','Neha'],
      last:  ['Mehta','Patel','Shah','Desai','Trivedi','Joshi','Rao','Bhatt','Oza','Iyer','Parekh','Kapoor','Rana','Dave','Dhruv','Jani','Vora','Panchal','Modi','Chokshi'],
    },

    /* ===== Helper: localize any {label_en, label_gu} entry ===== */
    bil: function (o) { return (o.label_en || '') + ' · ' + (o.label_gu || ''); },
  };
})();
