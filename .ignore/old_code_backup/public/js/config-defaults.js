// ============================================================
//  CONFIG DEFAULTS — Shared between index.php and config.php
// ============================================================
const CONFIG_DEFAULTS = {
    companyName: "Shreenathji Home Finance",
    companyAddress: "Your Company Address",
    companyPhone: "+91 XXXXX XXXXX",
    companyEmail: "info@shf.com",
    banks: [
        "HDFC Bank",
        "ICICI Bank",
        "Axis Bank",
        "Kotak Mahindra Bank"
    ],
    iomCharges: { thresholdAmount: 10000000, fixedCharge: 5500, percentageAbove: 0.35 },
    gstPercent: 18,
    ourServices: "Home Loan | LAP (Loan Against Property) | Balance Transfer | Top-Up Loan",
    tenures: [5, 10, 15,20],
    documents_en: {
        proprietor: [
            "PAN Card of Proprietor", "Aadhaar Card of Proprietor", "Business Address Proof",
            "Bank Statement (12 months)", "ITR (Last 3 years)", "GST Registration Certificate",
            "Shop & Establishment Certificate", "Property Documents (if applicable)",
            "Udyam Registration Certificate", "Passport Size Photographs"
        ],
        partnership_llp: [
            "Partnership Deed / LLP Agreement", "PAN Card of Firm / LLP",
            "PAN Card of All Partners", "Aadhaar Card of All Partners",
            "Bank Statement (12 months)", "ITR of Firm (Last 3 years)",
            "ITR of Partners (Last 3 years)", "GST Registration Certificate",
            "Certificate of Incorporation (LLP)", "Board Resolution / Authority Letter",
            "Business Address Proof", "Passport Size Photographs of All Partners"
        ],
        pvt_ltd: [
            "Certificate of Incorporation", "Memorandum of Association (MOA)",
            "Articles of Association (AOA)", "PAN Card of Company",
            "PAN Card of All Directors", "Aadhaar Card of All Directors",
            "Board Resolution for Loan", "Bank Statement (12 months)",
            "Audited Financials (Last 3 years)", "ITR of Company (Last 3 years)",
            "ITR of Directors (Last 3 years)", "GST Registration Certificate",
            "Business Address Proof", "Passport Size Photographs of All Directors"
        ],
        salaried: [
            "PAN Card", "Aadhaar Card", "Salary Slips (Last 3 months)",
            "Bank Statement (12 months)", "ITR (Last 2 years)", "Form 16",
            "Employment / Appointment Letter", "ID Card of Company",
            "Property Documents (if applicable)", "Passport Size Photographs"
        ]
    },
    documents_gu: {
        proprietor: [
            "માલિકનું PAN કાર્ડ",
            "માલિકનું આધાર કાર્ડ",
            "વ્યવસાય સરનામાનો પુરાવો",
            "બેંક સ્ટેટમેન્ટ (૧૨ મહિના)",
            "ITR (છેલ્લા ૩ વર્ષ)",
            "GST નોંધણી પ્રમાણપત્ર",
            "દુકાન અને સ્થાપના પ્રમાણપત્ર",
            "મિલકતના દસ્તાવેજો (જો લાગુ હોય)",
            "ઉદ્યમ નોંધણી પ્રમાણપત્ર",
            "પાસપોર્ટ સાઇઝ ફોટોગ્રાફ"
        ],
        partnership_llp: [
            "ભાગીદારી દસ્તાવેજ / LLP કરાર",
            "ફર્મ / LLP નું PAN કાર્ડ",
            "બધા ભાગીદારોનું PAN કાર્ડ",
            "બધા ભાગીદારોનું આધાર કાર્ડ",
            "બેંક સ્ટેટમેન્ટ (૧૨ મહિના)",
            "ફર્મનું ITR (છેલ્લા ૩ વર્ષ)",
            "ભાગીદારોનું ITR (છેલ્લા ૩ વર્ષ)",
            "GST નોંધણી પ્રમાણપત્ર",
            "ઇન્કોર્પોરેશન પ્રમાણપત્ર (LLP)",
            "બોર્ડ ઠરાવ / અધિકૃત પત્ર",
            "વ્યવસાય સરનામાનો પુરાવો",
            "બધા ભાગીદારોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ"
        ],
        pvt_ltd: [
            "ઇન્કોર્પોરેશન પ્રમાણપત્ર",
            "મેમોરેન્ડમ ઑફ એસોસિએશન (MOA)",
            "આર્ટિકલ્સ ઑફ એસોસિએશન (AOA)",
            "કંપનીનું PAN કાર્ડ",
            "બધા ડિરેક્ટરોનું PAN કાર્ડ",
            "બધા ડિરેક્ટરોનું આધાર કાર્ડ",
            "લોન માટે બોર્ડ ઠરાવ",
            "બેંક સ્ટેટમેન્ટ (૧૨ મહિના)",
            "ઑડિટેડ નાણાકીય (છેલ્લા ૩ વર્ષ)",
            "કંપનીનું ITR (છેલ્લા ૩ વર્ષ)",
            "ડિરેક્ટરોનું ITR (છેલ્લા ૩ વર્ષ)",
            "GST નોંધણી પ્રમાણપત્ર",
            "વ્યવસાય સરનામાનો પુરાવો",
            "બધા ડિરેક્ટરોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ"
        ],
        salaried: [
            "PAN કાર્ડ",
            "આધાર કાર્ડ",
            "સેલેરી સ્લિપ (છેલ્લા ૩ મહિના)",
            "બેંક સ્ટેટમેન્ટ (૧૨ મહિના)",
            "ITR (છેલ્લા ૨ વર્ષ)",
            "ફોર્મ ૧૬",
            "નોકરી / નિમણૂક પત્ર",
            "કંપનીનું ID કાર્ડ",
            "મિલકતના દસ્તાવેજો (જો લાગુ હોય)",
            "પાસપોર્ટ સાઇઝ ફોટોગ્રાફ"
        ]
    }
};
