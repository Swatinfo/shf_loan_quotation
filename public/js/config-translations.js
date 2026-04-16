// ============================================================
//  TRANSLATIONS — Bilingual labels, type labels, number words
// ============================================================
const TRANSLATIONS = {
    // Bilingual labels for PDF and UI (English / Gujarati)
    bi: {
        pdfTitle: 'LOAN PROPOSAL / લોન પ્રસ્તાવ',
        customer: 'Customer / ગ્રાહક',
        type: 'Type / પ્રકાર',
        loanAmount: 'Loan Amount / લોન રકમ',
        date: 'Date / તારીખ',
        roi: 'ROI / વ્યાજ દર',
        requiredDocs: 'Required Documents / જરૂરી દસ્તાવેજો',
        footer: 'This is a system-generated proposal. / આ સિસ્ટમ દ્વારા જનરેટ કરેલ પ્રસ્તાવ છે.',
        page: 'Page / પૃષ્ઠ',
        of: 'of / માંથી',
        tenure: 'Tenure / સમયગાળો',
        monthlyEmi: 'Monthly EMI / માસિક EMI',
        totalInterest: 'Total Interest / કુલ વ્યાજ',
        totalPayment: 'Total Payment / કુલ ચુકવણી',
        years: 'Years / વર્ષ',
        sr: 'Sr. / ક્ર.',
        documentName: 'Document Name / દસ્તાવેજ નામ',
        chargeDesc: 'Charge Description / ચાર્જ વર્ણન',
        amount: 'Amount / રકમ',
        totalCharges: 'Total Charges / કુલ ચાર્જ',
        pfCharge: 'PF Charge / PF ચાર્જ',
        adminCharges: 'Admin Charges / એડમિન ચાર્જ',
        stampDuty: 'Stamp Paper and Notary Charges / સ્ટેમ્પ પેપર અને નોટરી ચાર્જ',
        notaryCharges: 'Registration Fee / રજીસ્ટ્રેશન ફી',
        advocateFees: 'Advocate Fees / એડવોકેટ ફી',
        iomCharges: 'IOM Stamp Paper Charges / IOM સ્ટેમ્પ પેપર ચાર્જ',
        tcReport: 'TC Report Charges / TC રિપોર્ટ ચાર્જ',
        emiComparison: 'EMI Comparison / EMI સરખામણી',
        chargesComparison: 'Charges Comparison / ચાર્જ સરખામણી',
        description: 'Description / વર્ણન',
        // UI section headers
        customerInfo: 'Customer Information / ગ્રાહક માહિતી',
        customerName: 'Customer Name / ગ્રાહક નામ',
        customerType: 'Customer Type / ગ્રાહક પ્રકાર',
        loanDetails: 'Loan Details / લોન વિગતો',
        bankSelection: 'Bank Selection & EMI / બેંક પસંદગી અને EMI',
        generatePdf: 'GENERATE PDF PROPOSAL / PDF પ્રસ્તાવ જનરેટ કરો',
        chargesAndFees: 'Charges & Fees / ચાર્જ અને ફી',
        additionalCharges: 'Additional Charges (Optional) / વધારાના ચાર્જ (વૈકલ્પિક)',
        chargeName: 'Charge Name / ચાર્જ નામ',
        minRoi: 'Min ROI (%) / ઓછામાં ઓછું ROI (%)',
        maxRoi: 'Max ROI (%) / વધુમાં વધુ ROI (%)',
        tcReportAmount: 'TC Report Charges / TC રિપોર્ટ ચાર્જ',
        amountInWords: 'Amount in Words / રકમ શબ્દોમાં',
        selectType: '-- Select Type / પ્રકાર પસંદ કરો --'
    },
    // Type labels in both languages
    typeLabels: {
        en: {
            proprietor: 'Proprietor',
            partnership_llp: 'Partnership / LLP',
            pvt_ltd: 'Private Limited',
            all: 'All (Partnership/LLP + PVT LTD)'
        },
        gu: {
            proprietor: 'માલિકી',
            partnership_llp: 'ભાગીદારી / LLP',
            pvt_ltd: 'પ્રાઇવેટ લિમિટેડ',
            all: 'ભાગીદારી/LLP + પ્રાઇવેટ લિમિટેડ'
        }
    }
};

// Get bilingual type label
function getBilingualTypeLabel(type) {
    const en = TRANSLATIONS.typeLabels.en[type] || type;
    const gu = TRANSLATIONS.typeLabels.gu[type] || '';
    return gu ? en + ' / ' + gu : en;
}

// Get bilingual document name from parallel arrays
function getBilingualDocName(enName, docType, config) {
    const enDocs = config.documents_en || CONFIG_DEFAULTS.documents_en;
    const guDocs = config.documents_gu || CONFIG_DEFAULTS.documents_gu;
    // Search across all types for the English name
    const types = ['proprietor', 'partnership_llp', 'pvt_ltd'];
    for (const t of types) {
        const idx = (enDocs[t] || []).indexOf(enName);
        if (idx !== -1 && guDocs[t] && guDocs[t][idx]) {
            return enName + ' / ' + guDocs[t][idx];
        }
    }
    return enName;
}


// ============================================================
//  NUMBER TO WORDS - English (Indian numbering system)
// ============================================================
function numberToWordsIndian(num) {
    if (num === 0) return 'Zero';
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function twoDigits(n) {
        if (n < 20) return ones[n];
        return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '');
    }
    function threeDigits(n) {
        if (n >= 100) return ones[Math.floor(n / 100)] + ' Hundred' + (n % 100 ? ' ' + twoDigits(n % 100) : '');
        return twoDigits(n);
    }

    let result = '';
    if (num >= 10000000) {
        result += threeDigits(Math.floor(num / 10000000)) + ' Crore ';
        num %= 10000000;
    }
    if (num >= 100000) {
        result += twoDigits(Math.floor(num / 100000)) + ' Lakh ';
        num %= 100000;
    }
    if (num >= 1000) {
        result += twoDigits(Math.floor(num / 1000)) + ' Thousand ';
        num %= 1000;
    }
    if (num > 0) {
        result += threeDigits(num);
    }
    return result.trim() + ' Rupees';
}


// ============================================================
//  NUMBER TO WORDS - Gujarati (Indian numbering system)
// ============================================================
function numberToWordsGujarati(num) {
    if (num === 0) return 'શૂન્ય';
    const gu = [
        '','એક','બે','ત્રણ','ચાર','પાંચ','છ','સાત','આઠ','નવ',
        'દસ','અગિયાર','બાર','તેર','ચૌદ','પંદર','સોળ','સત્તર','અઢાર','ઓગણીસ',
        'વીસ','એકવીસ','બાવીસ','ત્રેવીસ','ચોવીસ','પચ્ચીસ','છવ્વીસ','સત્તાવીસ','અઠ્ઠાવીસ','ઓગણત્રીસ',
        'ત્રીસ','એકત્રીસ','બત્રીસ','તેત્રીસ','ચોત્રીસ','પાંત્રીસ','છત્રીસ','સાડત્રીસ','આડત્રીસ','ઓગણચાલીસ',
        'ચાલીસ','એકતાલીસ','બેતાલીસ','ત્રેતાલીસ','ચુંમાલીસ','પિસ્તાલીસ','છેતાલીસ','સુડતાલીસ','અડતાલીસ','ઓગણપચાસ',
        'પચાસ','એકાવન','બાવન','ત્રેપન','ચોપન','પંચાવન','છપ્પન','સત્તાવન','અઠ્ઠાવન','ઓગણસાઈઐ',
        'સાઈઐ','એકસઠ','બાસઠ','ત્રેસઠ','ચોસઠ','પાંસઠ','છાસઠ','સડસઠ','અડસઠ','ઓગણસિત્તેર',
        'સિત્તેર','એકોતેર','બોતેર','તોતેર','ચુમોતેર','પંચોતેર','છોતેર','સિત્યોતેર','ઇઠ્યોતેર','ઓગણાએંસી',
        'એંસી','એક્યાસી','બ્યાસી','ત્યાસી','ચોર્યાસી','પંચાસી','છ્યાસી','સત્યાસી','અઠ્ઠ્યાસી','નેવ્યાસી',
        'નેવું','એકાણું','બાણું','ત્રાણું','ચોરાણું','પંચાણું','છન્નું','સત્તાણું','અઠ્ઠાણું','નવ્વાણું'
    ];

    function twoDigitsGu(n) { return gu[n] || ''; }
    function threeDigitsGu(n) {
        if (n >= 100) {
            const h = gu[Math.floor(n / 100)];
            const rem = n % 100;
            return h + ' સો' + (rem ? ' ' + twoDigitsGu(rem) : '');
        }
        return twoDigitsGu(n);
    }

    let result = '';
    if (num >= 10000000) {
        result += threeDigitsGu(Math.floor(num / 10000000)) + ' કરોડ ';
        num %= 10000000;
    }
    if (num >= 100000) {
        result += twoDigitsGu(Math.floor(num / 100000)) + ' લાખ ';
        num %= 100000;
    }
    if (num >= 1000) {
        result += twoDigitsGu(Math.floor(num / 1000)) + ' હજાર ';
        num %= 1000;
    }
    if (num > 0) {
        result += threeDigitsGu(num);
    }
    return result.trim() + ' રૂપિયા';
}

// Get bilingual amount in words
function getBilingualAmountWords(num) {
    const en = numberToWordsIndian(num);
    const gu = numberToWordsGujarati(num);
    return en + ' / ' + gu;
}
