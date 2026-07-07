/* Shreenathji Home Finance — marketing site JS */
(function () {
    'use strict';

    /* ---------- Mobile nav toggle ---------- */
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => navMenu.classList.toggle('open'));
        navMenu.querySelectorAll('a').forEach((a) => {
            if (a.closest('.nav-has-dropdown') && a.parentElement === a.closest('.nav-has-dropdown')) return;
            a.addEventListener('click', () => navMenu.classList.remove('open'));
        });
    }

    /* ---------- Services dropdown (tap/click behavior) ---------- */
    document.querySelectorAll('.nav-has-dropdown').forEach((item) => {
        const trigger = item.querySelector(':scope > a');
        if (!trigger) return;
        trigger.addEventListener('click', (e) => {
            if (window.innerWidth <= 991) {
                e.preventDefault();
                document.querySelectorAll('.nav-has-dropdown.open').forEach((other) => {
                    if (other !== item) other.classList.remove('open');
                });
                item.classList.toggle('open');
            }
        });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-has-dropdown')) {
            document.querySelectorAll('.nav-has-dropdown.open').forEach((el) => el.classList.remove('open'));
        }
    });

    /* ---------- FAQ accordion ---------- */
    document.querySelectorAll('.faq-item').forEach((item) => {
        const q = item.querySelector('.faq-q');
        if (!q) return;
        q.addEventListener('click', () => {
            const isOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item').forEach((i) => i.classList.remove('open'));
            if (!isOpen) item.classList.add('open');
        });
    });

    /* ---------- Document tabs ---------- */
    document.querySelectorAll('.doc-tabs').forEach((tabs) => {
        const btns = tabs.querySelectorAll('.tab-btn');
        const panes = tabs.querySelectorAll('.tab-pane');
        btns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;
                btns.forEach((b) => b.classList.toggle('active', b === btn));
                panes.forEach((p) => p.classList.toggle('active', p.dataset.pane === target));
            });
        });
    });

    /* ---------- Indian number formatter ---------- */
    function formatIndian(num) {
        if (num === null || num === undefined || isNaN(num)) return '0';
        const n = Math.round(Number(num));
        const s = n.toString();
        if (s.length <= 3) return s;
        const last3 = s.slice(-3);
        const rest = s.slice(0, -3);
        return rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3;
    }

    /* ---------- EMI calculator ---------- */
    const emi = document.querySelector('#emi-calculator');
    if (emi) {
        const amountEl = emi.querySelector('#emi-amount');
        const rateEl = emi.querySelector('#emi-rate');
        const tenureEl = emi.querySelector('#emi-tenure');
        const amountVal = emi.querySelector('#emi-amount-val');
        const rateVal = emi.querySelector('#emi-rate-val');
        const tenureVal = emi.querySelector('#emi-tenure-val');
        const outEmi = emi.querySelector('#emi-out-monthly');
        const outInterest = emi.querySelector('#emi-out-interest');
        const outTotal = emi.querySelector('#emi-out-total');

        function calc() {
            const P = Number(amountEl.value);
            const annual = Number(rateEl.value);
            const years = Number(tenureEl.value);
            const r = annual / 12 / 100;
            const n = years * 12;
            let emiVal = 0;
            if (r === 0) {
                emiVal = P / n;
            } else {
                emiVal = (P * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            }
            const total = emiVal * n;
            const interest = total - P;

            amountVal.textContent = '₹ ' + formatIndian(P);
            rateVal.textContent = annual.toFixed(2) + '%';
            tenureVal.textContent = years + ' Years';
            outEmi.textContent = '₹ ' + formatIndian(emiVal);
            outInterest.textContent = '₹ ' + formatIndian(interest);
            outTotal.textContent = '₹ ' + formatIndian(total);
        }
        [amountEl, rateEl, tenureEl].forEach((el) => el.addEventListener('input', calc));
        calc();
    }

    /* ---------- Contact form validation + submit ---------- */
    const form = document.querySelector('#contact-form');
    if (form) {
        const statusEl = form.querySelector('.form-status');
        const turnstileEl = form.querySelector('.cf-turnstile');

        function setInvalid(field, message) {
            field.classList.add('invalid');
            const err = field.querySelector('.form-err');
            if (err && message) err.textContent = message;
        }
        function clearInvalid(field) {
            field.classList.remove('invalid');
        }
        form.querySelectorAll('input, select, textarea').forEach((el) => {
            el.addEventListener('input', () => {
                const f = el.closest('.form-field');
                if (f) clearInvalid(f);
            });
        });

        /* Called by Turnstile when verification succeeds (data-callback="onTurnstileOk") */
        window.onTurnstileOk = function () { /* no-op — presence of token is enough */ };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            /* Guard: if form element was detached (Chrome shows "form is not connected") */
            if (!form.isConnected) return;

            let ok = true;

            const required = form.querySelectorAll('[data-required]');
            required.forEach((el) => {
                const f = el.closest('.form-field');
                if (!el.value.trim()) {
                    setInvalid(f, 'This field is required');
                    ok = false;
                }
            });

            const emailEl = form.querySelector('[name="email"]');
            if (emailEl && emailEl.value.trim()) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!re.test(emailEl.value.trim())) {
                    setInvalid(emailEl.closest('.form-field'), 'Enter a valid email address');
                    ok = false;
                }
            }

            const phoneEl = form.querySelector('[name="phone"]');
            if (phoneEl && phoneEl.value.trim()) {
                const digits = phoneEl.value.replace(/\D/g, '');
                if (digits.length < 10) {
                    setInvalid(phoneEl.closest('.form-field'), 'Enter a valid phone number');
                    ok = false;
                }
            }

            /* Turnstile readiness check — don't submit before the token exists */
            if (turnstileEl) {
                const tokenField = form.querySelector('input[name="cf-turnstile-response"]');
                if (!tokenField || !tokenField.value) {
                    statusEl.className = 'form-status show error';
                    statusEl.textContent = 'Please complete the human-verification check above, then try again.';
                    statusEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
            }

            if (!ok) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            const origText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }
            statusEl.className = 'form-status';

            try {
                const data = new FormData(form);
                const res = await fetch(form.action, { method: 'POST', body: data });
                const json = await res.json().catch(() => ({}));
                if (res.ok && json.ok) {
                    statusEl.className = 'form-status show success';
                    statusEl.textContent = json.message || 'Thanks! We\'ll reach out to you shortly.';
                    form.reset();
                } else {
                    statusEl.className = 'form-status show error';
                    statusEl.textContent = (json && json.message) || 'Something went wrong. Please try again or call us.';
                }
            } catch (err) {
                statusEl.className = 'form-status show error';
                statusEl.textContent = 'Network error. Please check your connection and try again.';
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = origText; }
                statusEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }

    /* ---------- Current year in footer ---------- */
    const yearEl = document.querySelector('#current-year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    /* ---------- Scroll-reveal animations (IntersectionObserver) ---------- */
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!prefersReducedMotion && 'IntersectionObserver' in window) {
        // Tag common blocks so CSS [data-reveal-all] rules apply site-wide
        document.body.setAttribute('data-reveal-all', '');

        const revealSelector = [
            '.reveal',
            '.section-title', '.section-lead',
            '.feature-tile', '.service-card', '.service-detail',
            '.quote-card', '.network-tile', '.process-step',
            '.stat-value', '.about-grid > *',
            '.contact-info-card', '.contact-form',
            '.reviews-card', '.faq-item'
        ].join(', ');

        const revealObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

        document.querySelectorAll(revealSelector).forEach((el, idx) => {
            // Subtle stagger inside a group (reset for each parent row)
            const parent = el.parentElement;
            const siblings = parent ? Array.from(parent.children).filter(c => c.matches(revealSelector)) : [];
            const staggerIndex = siblings.indexOf(el);
            if (staggerIndex >= 0) {
                el.style.transitionDelay = (Math.min(staggerIndex, 6) * 70) + 'ms';
            }
            revealObserver.observe(el);
        });
    }

    /* ---------- Navbar gets shadow after scroll ---------- */
    const header = document.querySelector('.site-header');
    if (header) {
        let ticking = false;
        const onScroll = () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    header.classList.toggle('is-scrolled', window.scrollY > 8);
                    ticking = false;
                });
                ticking = true;
            }
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---------- Stat number count-up animation ---------- */
    if (!prefersReducedMotion && 'IntersectionObserver' in window) {
        const parseStat = (text) => {
            const m = (text || '').match(/([\d.,]+)/);
            if (!m) return null;
            const prefix = text.slice(0, m.index);
            const num    = parseFloat(m[1].replace(/,/g, ''));
            const suffix = text.slice(m.index + m[1].length);
            return { prefix, num, suffix };
        };

        const animate = (el, target, prefix, suffix) => {
            const duration = 1400;
            const start = performance.now();
            const step = (now) => {
                const p = Math.min(1, (now - start) / duration);
                const ease = 1 - Math.pow(1 - p, 3); // cubic ease-out
                const current = target * ease;
                const displayed = Number.isInteger(target)
                    ? Math.round(current).toLocaleString('en-IN')
                    : current.toFixed(1);
                el.textContent = prefix + displayed + suffix;
                if (p < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        };

        const counterObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                if (el.dataset.counted === '1') return;
                const parsed = parseStat(el.textContent);
                if (!parsed || isNaN(parsed.num)) { obs.unobserve(el); return; }
                el.dataset.counted = '1';
                animate(el, parsed.num, parsed.prefix, parsed.suffix);
                obs.unobserve(el);
            });
        }, { threshold: 0.4 });

        document.querySelectorAll('.stat-value, .network-num').forEach((el) => {
            counterObserver.observe(el);
        });
    }

    /* ---------- Hero v2 slider (split-layout, index2.php) ---------- */
    const slider = document.querySelector('#heroSlider');
    if (slider) {
        /* Support both the new v2 classes (.hv-*) and legacy full-bleed (.hero-*) */
        const slides = Array.from(slider.querySelectorAll('.hv-slide, .hero-slide'));
        const dots   = Array.from(slider.querySelectorAll('.hv-dot, .hero-dot'));
        const prev   = slider.querySelector('.hv-prev, .hero-arrow-prev');
        const next   = slider.querySelector('.hv-next, .hero-arrow-next');
        const bar    = slider.querySelector('.hv-progress-fill, .hero-progress-fill');
        const typedItems = Array.from(slider.querySelectorAll('.typed-word .tw-item'));
        const INTERVAL_MS = 6500;
        let current = 0;
        let autoTimer = null;
        let progressStart = 0;
        let progressPaused = false;
        let progressPausedAt = 0;
        let rafId = null;

        /* Inline styles for dot states — overrides any CSS cache misbehaviour */
        const DOT_INACTIVE = 'width:10px;height:10px;padding:0;margin:0;border:0;border-radius:50%;background:rgba(58,53,54,0.25);cursor:pointer;flex-shrink:0;line-height:0;font-size:0;display:inline-block;';
        const DOT_ACTIVE   = 'width:28px;height:10px;padding:0;margin:0;border:0;border-radius:999px;background:#f15a29;cursor:pointer;flex-shrink:0;line-height:0;font-size:0;display:inline-block;box-shadow:0 2px 6px rgba(241,90,41,0.35);';

        const setDotStyle = (dotEl, isActive) => {
            if (!dotEl) return;
            /* Only style the hv-dot variant (the visual-card dots). Legacy hero-dot keeps its own CSS. */
            if (dotEl.classList.contains('hv-dot')) {
                dotEl.setAttribute('style', isActive ? DOT_ACTIVE : DOT_INACTIVE);
            }
        };

        const go = (idx) => {
            idx = (idx + slides.length) % slides.length;
            if (idx === current) return;
            slides[current].classList.remove('is-active');
            dots[current]?.classList.remove('is-active');
            setDotStyle(dots[current], false);
            if (typedItems[current]) typedItems[current].classList.remove('is-active');
            current = idx;
            slides[current].classList.add('is-active');
            dots[current]?.classList.add('is-active');
            setDotStyle(dots[current], true);
            if (typedItems[current]) typedItems[current].classList.add('is-active');
            resetProgress();
        };

        const startAuto = () => {
            stopAuto();
            autoTimer = setInterval(() => go(current + 1), INTERVAL_MS);
            resetProgress();
        };
        const stopAuto = () => {
            if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
        };

        const resetProgress = () => {
            if (!bar) return;
            progressStart = performance.now();
            progressPaused = false;
            if (rafId) cancelAnimationFrame(rafId);
            const tick = (now) => {
                if (progressPaused) { rafId = requestAnimationFrame(tick); return; }
                const elapsed = now - progressStart;
                const pct = Math.min(100, (elapsed / INTERVAL_MS) * 100);
                bar.style.width = pct + '%';
                if (pct < 100) rafId = requestAnimationFrame(tick);
            };
            bar.style.width = '0%';
            rafId = requestAnimationFrame(tick);
        };

        /* Dots */
        dots.forEach((d, i) => d.addEventListener('click', () => { go(i); startAuto(); }));

        /* Arrows */
        prev?.addEventListener('click', () => { go(current - 1); startAuto(); });
        next?.addEventListener('click', () => { go(current + 1); startAuto(); });

        /* Pause on hover (desktop) / on touchstart (mobile) */
        const pauseProgress = () => {
            progressPaused = true;
            progressPausedAt = performance.now();
            slider.classList.add('is-paused');
        };
        const resumeProgress = () => {
            if (progressPaused) {
                progressStart += (performance.now() - progressPausedAt);
                progressPaused = false;
                slider.classList.remove('is-paused');
            }
        };
        slider.addEventListener('mouseenter', () => { stopAuto(); pauseProgress(); });
        slider.addEventListener('mouseleave', () => { startAuto(); resumeProgress(); });

        /* Touch swipe */
        let touchStartX = 0;
        let touchStartY = 0;
        slider.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            stopAuto();
        }, { passive: true });
        slider.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].clientX - touchStartX;
            const dy = e.changedTouches[0].clientY - touchStartY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                go(dx < 0 ? current + 1 : current - 1);
            }
            startAuto();
        }, { passive: true });

        /* Keyboard navigation */
        slider.setAttribute('tabindex', '0');
        slider.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft')  { go(current - 1); startAuto(); }
            if (e.key === 'ArrowRight') { go(current + 1); startAuto(); }
        });

        /* Pause when tab is hidden (battery friendliness) */
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) { stopAuto(); } else { startAuto(); }
        });

        /* Start */
        if (!prefersReducedMotion) startAuto();
    }
})();
