// ============================================================
//  PDF RENDERER — Client-side PDF template for offline generation
//  Mirrors PHP renderPdfHtml() from includes/pdf-template.php
//  Uses Blob URL + window.print() for "Save as PDF"
// ============================================================
var PdfRenderer = {

    /**
     * Render the full PDF HTML string (matching server-side template).
     */
    renderHtml: function(data, logoBase64) {
        var numBanks = data.banks.length;
        var descW = numBanks > 3 ? '25%' : '30%';
        var bankColW = numBanks > 3 ? (75 / numBanks) + '%' : (70 / numBanks) + '%';
        var fontSize = numBanks > 3 ? '10pt' : '10pt';

        // Colors (match server template)
        var primaryDarkFill = '#6b6868';
        var accent = '#f15a29';
        var accentTint = '#fef0eb';
        var bg = '#f8f8f8';
        var textColor = '#1a1a1a';
        var textMuted = '#6b7280';
        var borderColor = '#bcbec0';
        var white = '#ffffff';
        var footerMuted = '#939f9f';

        var e = function(s) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(String(s)));
            return div.innerHTML;
        };

        var T = {
            pdfTitle: 'LOAN PROPOSAL / લોન પ્રસ્તાવ',
            customer: 'Customer / ગ્રાહક',
            type: 'Type / પ્રકાર',
            loanAmount: 'Loan Amount / લોન રકમ',
            date: 'Date / તારીખ',
            roi: 'ROI / વ્યાજ દર',
            requiredDocs: 'Required Documents / જરૂરી દસ્તાવેજો',
            footer: 'This is a system-generated proposal. / આ સિસ્ટમ દ્વારા જનરેટ કરેલ પ્રસ્તાવ છે.',
            years: 'Years / વર્ષ',
            sr: 'Sr. / ક્ર.',
            documentName: 'Document Name / દસ્તાવેજ નામ',
            chargeDesc: 'Charge Description / ચાર્જ વર્ણન',
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
            monthlyEmi: 'Monthly EMI / માસિક EMI',
            totalInterest: 'Total Interest / કુલ વ્યાજ',
            totalPayment: 'Total Payment / કુલ ચુકવણી',
            additionalNotes: 'Additional Notes / વધારાની નોંધ',
            preparedBy: 'Prepared By / તૈયાર કરનાર',
            mobile: 'Mobile / મોબાઈલ'
        };

        var typeLabels = {
            en: { proprietor: 'Proprietor', partnership_llp: 'Partnership / LLP', pvt_ltd: 'Private Limited', salaried: 'Salaried', all: 'All (Partnership/LLP + PVT LTD)' },
            gu: { proprietor: 'માલિકી', partnership_llp: 'ભાગીદારી / LLP', pvt_ltd: 'પ્રાઇવેટ લિમિટેડ', salaried: 'પગારદાર', all: 'ભાગીદારી/LLP + પ્રાઇવેટ લિમિટેડ' }
        };

        var customerTypeLabel = (typeLabels.en[data.customerType] || data.customerType);
        var guType = typeLabels.gu[data.customerType] || '';
        if (guType) customerTypeLabel += ' / ' + guType;

        var amountWords = getBilingualAmountWords(parseInt(data.loanAmount));
        var loanFormatted = formatCurrency(data.loanAmount);

        // Build HTML
        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';

        // Font faces (reference URLs, served from SW cache)
        html += '@font-face { font-family: "NotoSansGujarati"; src: url("/fonts/NotoSansGujarati-Regular.ttf") format("truetype"); font-weight: normal; }';
        html += '@font-face { font-family: "NotoSansGujarati"; src: url("/fonts/NotoSansGujarati-Bold.ttf") format("truetype"); font-weight: bold; }';

        html += '@page { size: A4; margin: 0; }';
        html += '* { margin: 0; padding: 0; box-sizing: border-box; }';
        html += 'body { font-family: "NotoSansGujarati", "Noto Sans Gujarati", sans-serif; font-size: ' + fontSize + '; color: ' + textColor + '; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }';

        // Fixed header/footer visible on every page
        html += '.page-header { position: fixed; top: 0; left: 0; right: 0; }';
        html += '.page-footer { position: fixed; bottom: 0; left: 0; right: 0; }';
        // Table spacer trick: thead/tfoot reserve space for fixed header/footer on every page
        html += 'table.page-wrapper { width: 100%; border-collapse: collapse; }';
        html += 'table.page-wrapper > thead > tr > td { height: 100mm; }';
        html += 'table.page-wrapper > tfoot > tr > td { height: 18mm; }';
        html += 'table.page-wrapper > tbody > tr > td { vertical-align: top; }';

        html += '.header-bar { background: ' + primaryDarkFill + '; padding: 8mm 5mm 8mm 5mm }';
        html += '.accent-bar { background: ' + accent + '; height: 2mm; }';
        html += '.header-table { width: 100%; border-collapse: collapse; }';
        html += '.header-table td { vertical-align: middle; }';
        html += '.header-logo { height: 20mm; }';
        html += '.header-title { font-size: 13pt; font-weight: bold; color: ' + white + '; }';
        html += '.header-sub { font-size: 10pt; color: ' + borderColor + ';font-weight:600; }';
        html += '.content { padding: 0 5mm 0 5mm; }';
        html += '.customer-box { background: ' + accentTint + '; border: 1mm solid ' + accent + ';border-left: 3mm solid ' + accent + ';border-right: 3mm solid ' + accent + '; border-radius: 2mm; padding: 4mm 2mm 4mm 2mm; margin-bottom: 6mm; }';
        html += '.customer-box .label { font-size: 11pt; font-weight: bold; color: ' + textMuted + '; }';
        html += '.customer-box .value { font-size: 11pt; color: ' + textColor + '; }';
        html += '.customer-box .words { font-size: 9pt; color: ' + textMuted + '; }';
        html += '.cust-table { width: 100%; border-collapse: collapse; }';
        html += '.cust-table td { vertical-align: top; }';
        html += '.section-header { border-radius: 1.5mm; padding: 2mm 4mm; margin-bottom: 2mm; font-size: 9pt; font-weight: bold; color: ' + white + '; page-break-inside: avoid; }';
        html += '.section-header.emi { background: ' + accent + '; }';
        html += '.section-header.emi-first { }';
        html += '.section-header.charges { background: ' + primaryDarkFill + '; color: ' + accentTint + '; }';
        html += '.section-header.docs { background: ' + primaryDarkFill + '; color: ' + accentTint + '; }';
        html += 'table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; font-size: ' + fontSize + '; page-break-inside: auto; }';
        html += 'table.data-table th { background: ' + primaryDarkFill + '; color: ' + white + '; font-weight: bold; padding: 2mm; text-align: left; border: 0.2mm solid ' + borderColor + '; }';
        html += 'table.data-table th.bank-col { text-align: right; }';
        html += 'table.data-table td { padding: 2mm; border: 0.2mm solid ' + borderColor + '; }';
        html += 'table.data-table td.bank-val { text-align: right; }';
        html += 'table.data-table tr.alt { background: ' + bg + '; }';
        html += 'table.data-table tr.total-row td { font-weight: bold; }';
        html += '.additional-notes { border: 1px solid #c0392b; border-left: 3mm solid #c0392b; background: #fdf2f2; padding: 4mm 6mm; color: #c0392b; font-weight: bold; border-radius: 2mm; margin-top: 6mm; }';
        html += '.additional-notes-title { font-size: 9pt; margin-bottom: 2mm; }';
        html += '.footer-bar { background: ' + primaryDarkFill + '; padding: 2mm 18mm; }';
        html += '.footer-accent { background: ' + accent + '; height: 0.5mm; width: 100%; }';
        html += '.footer-table { width: 100%; border-collapse: collapse; }';
        html += '.footer-table td { color: ' + footerMuted + '; font-size: 9pt; vertical-align: middle;text-align:center; }';
        html += '</style></head><body>';

        // ---- FIXED HEADER ----
        html += '<div class="page-header">';
        html += '<div class="header-bar"><table class="header-table"><tr>';
        html += '<td style="width:40%;">';
        html += '<img src="' + (logoBase64 || '/images/logo3.png') + '" class="header-logo">';
        html += '</td>';
        html += '<td style="width:60%; text-align:right;">';
        html += '<div class="header-title">' + e(T.pdfTitle) + '</div>';
        html += '<div class="header-sub">' + e(T.date) + ' ' + e(data.date) + '</div>';
        html += '<div class="header-sub">' + e(data.companyPhone) + '  |  ' + e(data.companyEmail) + '</div>';
        html += '</td></tr></table></div>';
        html += '<div class="accent-bar"></div>';
        html += '<div class="customer-box" style="margin:2mm 5mm 2mm 5mm;">';
        html += '<table class="cust-table"><tr>';
        html += '<td style="width:50%;">';
        html += '<span class="label">' + e(T.customer) + '</span><br><span class="value">' + e(data.customerName) + '</span><br>';
        html += '<span class="label">' + e(T.type) + '</span><br><span class="value">' + e(customerTypeLabel) + '</span>';
        html += '</td>';
        html += '<td style="width:50%;">';
        html += '<span class="label">' + e(T.loanAmount) + '</span><br><span class="value">' + e(loanFormatted) + '</span><br>';
        html += '<span class="words">(' + e(amountWords) + ')</span>';
        html += '</td></tr>';
        if (data.preparedByName || data.preparedByMobile) {
            html += '<tr><td colspan="" style="padding-top:1mm;">';
            html += '<span class="label">' + e(T.preparedBy) + ':</span> ';
            if (data.preparedByName) html += '<span class="value">' + e(data.preparedByName) + '</span><br>';
            html += '</td>';
            html += '<td colspan="" style="padding-top:1mm;">';
            if (data.preparedByMobile) html += '  <span class="label">' + e(T.mobile) + '</span><br> <span class="value">' + e(data.preparedByMobile) + '</span>';
            html += '</td></tr>';
        }
        html += '</table></div>';
        html += '</div>'; // .page-header

        // ---- FIXED FOOTER ----
        html += '<div class="page-footer">';
        html += '<div class="footer-bar"><div class="footer-accent"></div>';
        if (data.ourServices) {
            html += '<div style="color:' + footerMuted + '; font-size:10pt; padding:1.5mm 18mm 0; text-align:center;">';
            html += '<strong>Our Services:</strong> ' + e(data.ourServices);
            html += '</div>';
        }
        html += '<table class="footer-table"><tr>';
        html += '<td>' + e(T.footer) + '</td>';
        html += '</tr></table></div>';
        html += '</div>'; // .page-footer

        // ---- CONTENT (table wrapper provides spacers for fixed header/footer) ----
        html += '<table class="page-wrapper"><thead><tr><td></td></tr></thead>';
        html += '<tfoot><tr><td></td></tr></tfoot>';
        html += '<tbody><tr><td>';
        html += '<div class="content">';

        // Documents (may span multiple pages — max 15 per page)
        var docsPerPage = 15;
        if (data.documents && data.documents.length > 0) {
            var totalDocs = data.documents.length;
            var docChunkStart = 0;
            var docChunkIdx = 0;

            while (docChunkStart < totalDocs) {
                var docChunkEnd = Math.min(docChunkStart + docsPerPage, totalDocs);

                if (docChunkIdx > 0) {
                    // Close previous page-wrapper, open new one for next batch
                    html += '</div>'; // .content
                    html += '</td></tr></tbody></table>';
                    html += '<table class="page-wrapper" style="page-break-before: always;"><thead><tr><td></td></tr></thead>';
                    html += '<tfoot><tr><td></td></tr></tfoot>';
                    html += '<tbody><tr><td>';
                    html += '<div class="content">';
                }

                // Section header only on the first chunk
                if (docChunkIdx === 0) {
                    html += '<div class="section-header docs">' + e(T.requiredDocs) + '</div>';
                }

                html += '<table class="data-table"><tr><th style="width:12mm; text-align:center;">' + e(T.sr) + '</th>';
                html += '<th>' + e(T.documentName) + '</th></tr>';
                for (var di = docChunkStart; di < docChunkEnd; di++) {
                    var dAlt = (di % 2 === 1) ? ' class="alt"' : '';
                    var biName = e(data.documents[di].en);
                    if (data.documents[di].gu) biName += ' / ' + e(data.documents[di].gu);
                    html += '<tr' + dAlt + '><td style="text-align:center;">' + (di + 1) + '</td>';
                    html += '<td>' + biName + '</td></tr>';
                }
                html += '</table>';

                docChunkStart = docChunkEnd;
                docChunkIdx++;
            }
        }

        // Close page-wrapper for Documents, open new one for EMI
        html += '</div>'; // .content
        html += '</td></tr></tbody></table>';
        html += '<table class="page-wrapper" style="page-break-before: always;"><thead><tr><td></td></tr></thead>';
        html += '<tfoot><tr><td></td></tr></tfoot>';
        html += '<tbody><tr><td>';
        html += '<div class="content">';

        // EMI Tables per tenure (max 2 per page)
        for (var ti = 0; ti < data.tenures.length; ti++) {
            // Start a new page after every 2 EMI tables
            if (ti > 0 && ti % 2 === 0) {
                html += '</div>'; // .content
                html += '</td></tr></tbody></table>';
                html += '<table class="page-wrapper" style="page-break-before: always;"><thead><tr><td></td></tr></thead>';
                html += '<tfoot><tr><td></td></tr></tfoot>';
                html += '<tbody><tr><td>';
                html += '<div class="content">';
            }
            var tenure = data.tenures[ti];
            html += '<div class="section-header emi">' + e(tenure + ' ' + T.years + ' - ' + T.emiComparison) + '</div>';
            html += '<table class="data-table"><tr><th style="width:' + descW + ';">' + e(T.description) + '</th>';
            for (var bi = 0; bi < data.banks.length; bi++) {
                html += '<th class="bank-col" style="width:' + bankColW + ';">' + e(data.banks[bi].name) + '</th>';
            }
            html += '</tr>';

            // ROI
            html += '<tr><td>' + e(T.roi) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                var bank = data.banks[bi];
                var roiStr = bank.roiMin.toFixed(2) + '%';
                if (bank.roiMax > 0) roiStr = bank.roiMin.toFixed(2) + '% - ' + bank.roiMax.toFixed(2) + '%';
                html += '<td class="bank-val">' + e(roiStr) + '</td>';
            }
            html += '</tr>';

            // EMI
            html += '<tr class="alt"><td>' + e(T.monthlyEmi) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                var emi = data.banks[bi].emiByTenure[tenure];
                html += '<td class="bank-val">' + e(emi ? formatCurrency(emi.emi) : '–') + '</td>';
            }
            html += '</tr>';

            // Total Interest
            html += '<tr><td>' + e(T.totalInterest) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                emi = data.banks[bi].emiByTenure[tenure];
                html += '<td class="bank-val">' + e(emi ? formatCurrency(emi.totalInterest) : '–') + '</td>';
            }
            html += '</tr>';

            // Total Payment
            html += '<tr class="alt"><td>' + e(T.totalPayment) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                emi = data.banks[bi].emiByTenure[tenure];
                html += '<td class="bank-val">' + e(emi ? formatCurrency(emi.totalPayment) : '–') + '</td>';
            }
            html += '</tr></table>';
        }

        // Close page-wrapper for EMI pages, open new one for Charges (always own page)
        html += '</div>'; // .content
        html += '</td></tr></tbody></table>';
        html += '<table class="page-wrapper" style="page-break-before: always;"><thead><tr><td></td></tr></thead>';
        html += '<tfoot><tr><td></td></tr></tfoot>';
        html += '<tbody><tr><td>';
        html += '<div class="content">';

        // Charges Table (separate page)
        html += '<div class="section-header charges">' + e(T.chargesComparison) + '</div>';
        html += '<table class="data-table"><tr><th style="width:' + descW + ';">' + e(T.chargeDesc) + '</th>';
        for (bi = 0; bi < data.banks.length; bi++) {
            html += '<th class="bank-col" style="width:' + bankColW + ';">' + e(data.banks[bi].name) + '</th>';
        }
        html += '</tr>';

        var chargeRows = [
            { key: 'pf', label: T.pfCharge },
            { key: 'admin', label: T.adminCharges },
            { key: 'stamp_notary', label: T.stampDuty },
            { key: 'iom', label: T.iomCharges },
            { key: 'registration_fee', label: T.notaryCharges },
            { key: 'advocate', label: T.advocateFees },
            { key: 'tc', label: T.tcReport }
        ];

        var filteredRows = [];
        for (var ci = 0; ci < chargeRows.length; ci++) {
            var hasValue = false;
            for (bi = 0; bi < data.banks.length; bi++) {
                if (data.banks[bi].charges[chargeRows[ci].key]) { hasValue = true; break; }
            }
            if (hasValue) filteredRows.push(chargeRows[ci]);
        }

        // Extra charge names
        var extraNames = [];
        for (bi = 0; bi < data.banks.length; bi++) {
            var c = data.banks[bi].charges;
            if (c.extra1Name && extraNames.indexOf(c.extra1Name) === -1) extraNames.push(c.extra1Name);
            if (c.extra2Name && extraNames.indexOf(c.extra2Name) === -1) extraNames.push(c.extra2Name);
        }

        var rowIdx = 0;
        for (ci = 0; ci < filteredRows.length; ci++) {
            var alt = (rowIdx % 2 === 1) ? ' class="alt"' : '';
            html += '<tr' + alt + '><td>' + e(filteredRows[ci].label) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                var val = data.banks[bi].charges[filteredRows[ci].key] || 0;
                html += '<td class="bank-val">' + (val ? e(formatCurrency(val)) : '–') + '</td>';
            }
            html += '</tr>';
            rowIdx++;
        }

        for (var ei = 0; ei < extraNames.length; ei++) {
            alt = (rowIdx % 2 === 1) ? ' class="alt"' : '';
            html += '<tr' + alt + '><td>' + e(extraNames[ei]) + '</td>';
            for (bi = 0; bi < data.banks.length; bi++) {
                c = data.banks[bi].charges;
                val = 0;
                if (c.extra1Name === extraNames[ei] && c.extra1Amt) val = c.extra1Amt;
                else if (c.extra2Name === extraNames[ei] && c.extra2Amt) val = c.extra2Amt;
                html += '<td class="bank-val">' + (val ? e(formatCurrency(val)) : '–') + '</td>';
            }
            html += '</tr>';
            rowIdx++;
        }

        // Total row
        html += '<tr class="total-row"><td><strong>' + e(T.totalCharges) + '</strong></td>';
        for (bi = 0; bi < data.banks.length; bi++) {
            html += '<td class="bank-val"><strong>' + e(formatCurrency(data.banks[bi].charges.total)) + '</strong></td>';
        }
        html += '</tr></table>';

        // Additional Notes
        if (data.additionalNotes) {
            html += '<div class="additional-notes">';
            html += '<div class="additional-notes-title">' + e(T.additionalNotes) + '</div>';
            html += '<div>' + e(data.additionalNotes) + '</div>';
            html += '</div>';
        }

        html += '</div>'; // .content
        html += '</td></tr></tbody></table>';
        html += '</body></html>';
        return html;
    },

    /**
     * Generate offline PDF via Blob URL + window.print().
     * Desktop/Android: opens new tab and triggers print dialog.
     * iOS/PWA: downloads HTML file (iOS doesn't support window.print).
     * Returns 'print' or 'download' so caller can show the right message.
     */
    generateOfflinePdf: function(payload, config, logoBase64) {
        // Build template data
        var today = new Date();
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var dateStr = today.getDate() + ' ' + months[today.getMonth()] + ' ' + today.getFullYear();

        var templateData = {
            customerName: payload.customerName,
            customerType: payload.customerType,
            loanAmount: payload.loanAmount,
            date: dateStr,
            companyPhone: config.companyPhone || '+91 XXXXX XXXXX',
            companyEmail: config.companyEmail || 'info@shf.com',
            tenures: payload.selectedTenures || config.tenures || [5, 10, 15, 20],
            banks: payload.banks,
            documents: payload.documents || [],
            additionalNotes: payload.additionalNotes || '',
            ourServices: payload.ourServices || config.ourServices || '',
            preparedByName: payload.preparedByName || '',
            preparedByMobile: payload.preparedByMobile || ''
        };

        var html = this.renderHtml(templateData, logoBase64);
        var blob = new Blob([html], { type: 'text/html' });

        // Detect iOS (iPhone, iPad, iPod, or iPad pretending to be Mac)
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        // iOS doesn't support window.print() at all — download the file instead
        if (isIOS) {
            this._downloadHtmlFile(blob, payload.customerName);
            return 'download';
        }

        // Desktop and Android: open new tab + trigger print dialog
        var url = URL.createObjectURL(blob);
        var win = window.open(url, '_blank');
        if (win) {
            setTimeout(function() {
                try {
                    win.focus();
                    win.print();
                } catch(e) {
                    console.warn('Auto-print failed:', e);
                }
            }, 1500);
            return 'print';
        } else {
            // Popup blocked — download as HTML file instead
            URL.revokeObjectURL(url);
            this._downloadHtmlFile(blob, payload.customerName);
            return 'download';
        }
    },

    /**
     * Download the rendered HTML as a file.
     * User can open it in any browser and use Print > Save as PDF.
     */
    _downloadHtmlFile: function(blob, customerName) {
        var safeName = (customerName || 'Draft').replace(/[^a-zA-Z0-9઀-૿ ]/g, '_').trim();
        var now = new Date();
        var dateStr = now.getFullYear() + '-'
            + String(now.getMonth() + 1).padStart(2, '0') + '-'
            + String(now.getDate()).padStart(2, '0') + '_'
            + String(now.getHours()).padStart(2, '0') + '_'
            + String(now.getMinutes()).padStart(2, '0') + '_'
            + String(now.getSeconds()).padStart(2, '0');
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Loan_Proposal_' + safeName + '_' + dateStr + '.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 5000);
    }
};
