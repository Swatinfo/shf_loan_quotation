/**
 * SHF Forms — jQuery-based form interactivity
 *
 * Mirrors public/js/shf-app.js (the live production behaviors) for the
 * newtheme demo. Same SHF.* namespace so demo code is drop-in compatible.
 *
 * Required vendors (load before this file):
 *   - jQuery 3.7
 *   - Bootstrap Datepicker (+CSS)
 *   - SweetAlert2 (+CSS)
 *   - SortableJS
 *
 * All SHF.init* helpers are defined EAGERLY (top-level) so inline page
 * scripts can call them during body parse, before doc-ready fires.
 */
(function () {
  if (typeof window.jQuery === 'undefined') {
    console.warn('[shf-forms] jQuery not loaded — form interactivity disabled.');
    return;
  }
  var $ = window.jQuery;
  window.SHF = window.SHF || {};

  /* ==================== Indian number formatting ==================== */
  SHF.formatIndianNumber = function (num) {
    if (isNaN(num) || num === 0) return '0';
    var s = Math.floor(Math.abs(num)).toString();
    if (s.length <= 3) return s;
    var last3 = s.slice(-3);
    var rest  = s.slice(0, -3);
    return rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3;
  };

  SHF.numberToWordsEn = function (num) {
    if (num === 0) return 'Zero';
    var ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    function tw(n){ if(n<20) return ones[n]; return tens[Math.floor(n/10)] + (n%10 ? ' '+ones[n%10] : ''); }
    function th(n){ if(n>=100) return ones[Math.floor(n/100)] + ' Hundred' + (n%100 ? ' '+tw(n%100) : ''); return tw(n); }
    var r = '';
    if (num >= 10000000) { r += th(Math.floor(num/10000000)) + ' Crore ';  num %= 10000000; }
    if (num >= 100000)   { r += tw(Math.floor(num/100000))   + ' Lakh ';   num %= 100000;   }
    if (num >= 1000)     { r += tw(Math.floor(num/1000))     + ' Thousand ';num %= 1000;     }
    if (num > 0) r += th(num);
    return r.trim() + ' Rupees';
  };

  SHF.numberToWordsGu = function (num) {
    if (num === 0) return 'શૂન્ય';
    var gu = [
      '','એક','બે','ત્રણ','ચાર','પાંચ','છ','સાત','આઠ','નવ',
      'દસ','અગિયાર','બાર','તેર','ચૌદ','પંદર','સોળ','સત્તર','અઢાર','ઓગણીસ',
      'વીસ','એકવીસ','બાવીસ','ત્રેવીસ','ચોવીસ','પચ્ચીસ','છવ્વીસ','સત્તાવીસ','અઠ્ઠાવીસ','ઓગણત્રીસ',
      'ત્રીસ','એકત્રીસ','બત્રીસ','તેંત્રીસ','ચોંત્રીસ','પાંત્રીસ','છત્રીસ','સાડત્રીસ','આડત્રીસ','ઓગણચાલીસ',
      'ચાલીસ','એકતાલીસ','બેતાલીસ','તેતાલીસ','ચુંમ્માલીસ','પિસ્તાલીસ','છેંતાલીસ','સુડતાલીસ','અડતાલીસ','ઓગણપચાસ',
      'પચાસ','એકાવન','બાવન','ત્રેપન','ચોપન','પંચાવન','છપ્પન','સત્તાવન','અઠ્ઠાવન','ઓગણસાઈઠ',
      'સાઈઠ','એકસઠ','બાસઠ','ત્રેસઠ','ચોસઠ','પાંસઠ','છાસઠ','સડસઠ','અડસઠ','ઓગણોસિત્તેર',
      'સિત્તેર','એકોતેર','બોંતેર','તોંતેર','ચુંમોતેર','પંચોતેર','છોંતેર','સીતોતેર','ઇઠોતેર','ઓગણએંસી',
      'એંસી','એક્યાસી','બ્યાસી','ત્યાસી','ચોરાસી','પંચાસી','છયાસી','સત્યાસી','અઠયાસી','નેવ્યાસી',
      'નેવું','એકણું','બાણું','ત્રાણું','ચોરાણું','પંચાણું','છન્નું','સતાણું','અઠ્ઠાણું','નવ્વાણું'
    ];
    function tw(n){ return gu[n] || ''; }
    function th(n){ if(n>=100) return gu[Math.floor(n/100)] + ' સો' + (n%100 ? ' '+tw(n%100) : ''); return tw(n); }
    var r = '';
    if (num >= 10000000) { r += th(Math.floor(num/10000000)) + ' કરોડ '; num %= 10000000; }
    if (num >= 100000)   { r += tw(Math.floor(num/100000))   + ' લાખ ';  num %= 100000;   }
    if (num >= 1000)     { r += tw(Math.floor(num/1000))     + ' હજાર '; num %= 1000;     }
    if (num > 0) r += th(num);
    return r.trim() + ' રૂપિયા';
  };

  SHF.bilingualAmountWords = function (num) {
    return SHF.numberToWordsEn(num) + ' / ' + SHF.numberToWordsGu(num);
  };

  /* ==================== Validation ==================== */
  SHF.validateForm = function ($form, rules) {
    $form.find('.shf-validation-error').remove();
    $form.find('.is-invalid').removeClass('is-invalid');
    var errors = [];
    $.each(rules, function (name, rule) {
      var $f = $form.find('[name="' + name + '"]');
      if (!$f.length) return;
      var val;
      if ($f.is(':radio')) val = $form.find('[name="' + name + '"]:checked').val() || '';
      else if ($f.is(':checkbox')) val = $f.is(':checked') ? $f.val() : '';
      else val = ($f.val() || '').toString().trim();
      var label = rule.label || name.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
      var err = null;
      if (rule.required && !val) err = label + ' is required.';
      else if (val) {
        if (rule.maxlength && val.length > rule.maxlength) err = label + ' must not exceed ' + rule.maxlength + ' characters.';
        else if (rule.minlength && val.length < rule.minlength) err = label + ' must be at least ' + rule.minlength + ' characters.';
        else if (rule.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) err = label + ' must be a valid email.';
        else if (rule.numeric) {
          var n = parseFloat(val.replace(/,/g, ''));
          if (isNaN(n)) err = label + ' must be a number.';
          else if (rule.min !== undefined && n < rule.min) err = label + ' must be at least ' + rule.min + '.';
          else if (rule.max !== undefined && n > rule.max) err = label + ' must not exceed ' + SHF.formatIndianNumber(rule.max) + '.';
        }
        else if (rule.pattern && !rule.pattern.test(val)) err = rule.patternMsg || (label + ' format is invalid.');
        else if (rule.dateFormat === 'd/m/Y' && !/^\d{2}\/\d{2}\/\d{4}$/.test(val)) err = label + ' must be in dd/mm/yyyy format.';
      }
      if (!err && rule.custom) err = rule.custom(val, $f, $form);
      if (err) errors.push({ field: name, message: err, $field: $f });
    });
    if (errors.length) {
      errors.forEach(function (e) {
        var $t = e.$field;
        if ($t.is(':hidden') && $t.siblings('.shf-amount-input').length) $t = $t.siblings('.shf-amount-input');
        $t.addClass('is-invalid').css({ 'border-color': '#dc3545', 'box-shadow': '0 0 0 3px rgba(220,53,69,0.15)' });
        $('<div class="shf-validation-error" style="display:block;width:100%;margin-top:4px;font-size:0.8rem;color:#dc3545;font-weight:500;">' + e.message + '</div>').insertAfter($t);
      });
      return false;
    }
    return true;
  };

  /* ==================== Datepickers ==================== */
  SHF.initDatepickers = function (scope) {
    if (!$.fn || !$.fn.datepicker) { console.warn('[shf-forms] Bootstrap Datepicker plugin not loaded.'); return; }
    var $scope = scope ? $(scope) : $(document);
    // container:'body' makes the popup float above any overflow:hidden ancestor (.app in shf.css).
    var baseOpts = { format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, clearBtn: true, orientation: 'bottom auto', container: 'body' };
    function bind($el, opts) {
      if ($el.data('shf-dp-bound')) return;
      $el.data('shf-dp-bound', true);
      $el.datepicker(opts);
      // Defensive: if the input is a plain text input with no other click handler,
      // ensure a click always triggers show().
      $el.on('click.shfdp focus.shfdp', function () { $(this).datepicker('show'); });
    }
    $scope.find('.shf-datepicker').addBack('.shf-datepicker').each(function () { bind($(this), baseOpts); });
    $scope.find('.shf-datepicker-past').addBack('.shf-datepicker-past').each(function () { bind($(this), $.extend({}, baseOpts, { endDate: '+0d' })); });
    $scope.find('.shf-datepicker-future').addBack('.shf-datepicker-future').each(function () { bind($(this), $.extend({}, baseOpts, { startDate: '+1d' })); });
  };

  /* ==================== Indian-amount inputs ==================== */
  SHF.initAmountFields = function (scope) {
    var $scope = scope ? $(scope) : $(document);
    $scope.find('.shf-amount-input').addBack('.shf-amount-input').each(function () {
      var $input = $(this);
      if ($input.data('shf-amount-bound')) return;
      $input.data('shf-amount-bound', true);
      var $wrap = $input.closest('.shf-amount-wrap, .amt-wrap');
      var $hidden = $wrap.find('.shf-amount-raw');
      var $words = $wrap.find('[data-amount-words]');
      function update() {
        var raw = parseInt(($input.val() || '').replace(/[^0-9]/g, ''), 10);
        if (isNaN(raw) || $input.val().trim() === '') {
          $input.val(''); $hidden.val(''); if ($words.length) $words.text('');
        } else {
          $input.val(raw === 0 ? '0' : SHF.formatIndianNumber(raw));
          $hidden.val(raw);
          if ($words.length) $words.text(raw > 0 ? SHF.bilingualAmountWords(raw) : '');
        }
      }
      $input.on('input', update);
      if ($input.val()) update();
    });
  };

  /* ==================== Tag inputs ==================== */
  SHF.initTagInputs = function (scope) {
    var $scope = scope ? $(scope) : $(document);
    $scope.find('.tag-input-wrap').addBack('.tag-input-wrap').each(function () {
      var $wrap = $(this);
      if ($wrap.data('shf-tag-bound')) return;
      $wrap.data('shf-tag-bound', true);
      var $input = $wrap.find('input').last();
      var $hidden = $wrap.find('input[type="hidden"]');
      function syncHidden() {
        if (!$hidden.length) return;
        var vals = $wrap.find('.tag').map(function () { return $(this).data('val') || $(this).text().replace('×', '').trim(); }).get();
        $hidden.val(vals.join(','));
      }
      function addTag(txt) {
        txt = (txt || '').trim();
        if (!txt) return;
        var $tag = $('<span class="tag"></span>').attr('data-val', txt).text(txt).append(' <span class="x">×</span>');
        $input.before($tag);
        syncHidden();
      }
      $input.on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag($input.val()); $input.val(''); }
        else if (e.key === 'Backspace' && !$input.val()) { $wrap.find('.tag').last().remove(); syncHidden(); }
      });
      $wrap.on('click', '.tag .x', function () { $(this).closest('.tag').remove(); syncHidden(); });
      var $form = $wrap.closest('form');
      if ($form.length) $form.on('submit', function () { if ($input.val().trim()) { addTag($input.val()); $input.val(''); } });
    });
  };

  /* ==================== Sortable ==================== */
  SHF.initSortables = function (scope) {
    if (typeof Sortable === 'undefined') return;
    var $scope = scope ? $(scope) : $(document);
    $scope.find('[data-sortable]').addBack('[data-sortable]').each(function () {
      if (this.dataset.shfSortableBound) return;
      this.dataset.shfSortableBound = '1';
      Sortable.create(this, {
        animation: 150,
        handle: this.dataset.sortableHandle || null,
        ghostClass: 'doc-sortable-ghost',
        onEnd: function () { if (window.shfToast) window.shfToast('Order saved'); }
      });
    });
  };

  /* ==================== Combined rescan ==================== */
  SHF.rescan = function (scope) {
    SHF.initDatepickers(scope);
    SHF.initAmountFields(scope);
    SHF.initTagInputs(scope);
    SHF.initSortables(scope);
  };

  /* ==================== Doc-ready: first sweep + global handlers ==================== */
  $(function () {
    $('form').attr('novalidate', 'novalidate');

    SHF.rescan();

    // Observer for dynamically inserted markup (tab panels, modals, ajax rows)
    if (typeof MutationObserver !== 'undefined') {
      new MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) {
          for (var j = 0; j < muts[i].addedNodes.length; j++) {
            var n = muts[i].addedNodes[j];
            if (n.nodeType !== 1) continue;
            if (n.matches && (n.matches('.shf-datepicker, .shf-datepicker-past, .shf-datepicker-future, .shf-amount-input, .tag-input-wrap, [data-sortable]'))) {
              SHF.rescan(n);
            } else if (n.querySelector && n.querySelector('.shf-datepicker, .shf-datepicker-past, .shf-datepicker-future, .shf-amount-input, .tag-input-wrap, [data-sortable]')) {
              SHF.rescan(n);
            }
          }
        }
      }).observe(document.body, { childList: true, subtree: true });
    }

    // Clear validation error on input change
    $(document).on('input change', '.is-invalid', function () {
      var $el = $(this);
      $el.removeClass('is-invalid').css({ 'border-color': '', 'box-shadow': '' });
      $el.siblings('.shf-validation-error').remove();
      $el.closest('.amt-wrap, .input-wrap').find('.shf-validation-error').remove();
    });

    // Auto-expand textareas (fallback for no field-sizing)
    if (!CSS.supports || !CSS.supports('field-sizing', 'content')) {
      var autoExpand = function (el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
      $(document).on('input', 'textarea.input, textarea.textarea', function () { autoExpand(this); });
      $('textarea.input, textarea.textarea').each(function () { autoExpand(this); });
    }

    // Password toggle
    $(document).on('click', '.shf-password-toggle', function () {
      var tid = $(this).data('target');
      var $inp = $('#' + tid);
      var pwd = $inp.attr('type') === 'password';
      $inp.attr('type', pwd ? 'text' : 'password');
      $(this).find('.shf-eye-open').toggle(!pwd);
      $(this).find('.shf-eye-closed').toggle(pwd);
    });

    // SweetAlert confirm-delete on forms
    $(document).on('submit', '.shf-confirm-delete', function (e) {
      if (typeof Swal === 'undefined') return;
      e.preventDefault();
      var form = this;
      Swal.fire({
        title: $(form).data('confirm-title') || 'Are you sure?',
        text:  $(form).data('confirm-text')  || 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc2626', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel'
      }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });

    // Click-triggered confirm-delete on links/buttons
    $(document).on('click', '[data-confirm-delete]:not(form)', function (e) {
      if (typeof Swal === 'undefined') return;
      e.preventDefault();
      var $el = $(this);
      Swal.fire({
        title: 'Delete ' + ($el.data('confirm-delete') || 'item') + '?',
        text: 'This action cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc2626', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete', cancelButtonText: 'Cancel'
      }).then(function (r) { if (r.isConfirmed && window.shfToast) window.shfToast('Deleted'); });
    });

    // Collapsible sections
    $(document).on('click', '.shf-collapsible[data-target]', function () {
      var $this = $(this);
      var $tgt = $($this.data('target'));
      if ($tgt.hasClass('shf-filter-body-collapse')) { $tgt.removeClass('shf-filter-body-collapse').hide(); }
      var open = $tgt.is(':visible');
      $tgt.slideToggle(200);
      $this.toggleClass('shf-filter-open', !open);
      var $b = $this.find('.shf-filter-count'); if ($b.length) $b.toggleClass('shf-collapse-hidden', !open);
    });

    // Chip toggle (single on/off)
    $(document).on('click', '.chip-toggle', function () {
      $(this).toggleClass('on');
      var $hidden = $(this).closest('.chip-group').siblings('input[type="hidden"][data-chip-target]');
      if ($hidden.length) {
        var vals = $(this).closest('.chip-group').find('.chip-toggle.on').map(function () { return $(this).data('val') || $(this).text().trim(); }).get();
        $hidden.val(vals.join(','));
      }
    });
  });
})();
