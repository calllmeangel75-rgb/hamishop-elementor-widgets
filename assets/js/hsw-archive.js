/**
 * HamiShop Elementor Widgets — Archive frontend behavior.
 * Vanilla JS, no dependencies. Event delegation only.
 * Sheets, SEO toggle, collapse boxes, brand search, price slider sync,
 * memory/color chip inputs, add-to-cart AJAX, wishlist toggle.
 */
(function () {
        'use strict';

        var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
        function fa(n) { return String(n).replace(/[0-9]/g, function (d) { return FA[d]; }); }
        function fmt(n) { return fa(Number(n).toLocaleString('en-US').replace(/,/g, ',')); }

        /* ---------- Sheets & modals (bottom sheets + guide modal) ---------- */
        function openSheet(name) {
                var el = document.querySelector('[data-hsw-sheet="' + name + '"]');
                if (!el) { return; }
                el.classList.add('is-open');
                el.setAttribute('aria-hidden', 'false');
        }
        function closeSheet(sheet) {
                if (!sheet) { return; }
                sheet.classList.remove('is-open');
                sheet.setAttribute('aria-hidden', 'true');
        }
        document.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-hsw-open]');
                if (opener) {
                        e.preventDefault();
                        openSheet(opener.getAttribute('data-hsw-open'));
                        return;
                }
                var closer = e.target.closest('[data-hsw-close]');
                if (closer) {
                        closeSheet(closer.closest('.hsw-asheet, .hsw-agd__modal'));
                }
        });
        document.addEventListener('keydown', function (e) {
                if ('Escape' !== e.key) { return; }
                document.querySelectorAll('.hsw-asheet.is-open, .hsw-agd__modal.is-open').forEach(closeSheet);
        });

        /* ---------- SEO description toggle (header widget) ---------- */
        document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-hsw-seo]');
                if (!btn) { return; }
                var target = btn.parentElement ? btn.parentElement.querySelector('[data-hsw-seo-target]') : null;
                if (!target) { return; }
                var open = target.classList.toggle('is-open');
                var label = btn.querySelector('span');
                if (label) {
                        var openTxt = btn.hasAttribute('data-hsw-open-txt') ? btn.getAttribute('data-hsw-open-txt') : 'بستن';
                        var closeTxt = btn.hasAttribute('data-hsw-close-txt') ? btn.getAttribute('data-hsw-close-txt') : label.textContent;
                        label.textContent = open ? openTxt : closeTxt;
                }
                btn.classList.toggle('is-open', open);
        });

        /* ---------- Collapse boxes (desktop sidebar) ---------- */
        document.addEventListener('click', function (e) {
                var head = e.target.closest('.hsw-af__boxhead');
                if (!head) { return; }
                var box = head.closest('[data-hsw-collapse]');
                if (box) { box.classList.toggle('is-collapsed'); }
        });

        /* ---------- Brand search filter ---------- */
        document.addEventListener('input', function (e) {
                var input = e.target.closest('[data-hsw-brandsearch]');
                if (!input) { return; }
                var box = input.closest('.hsw-af__box');
                var list = box ? box.querySelector('[data-hsw-brandlist]') : null;
                if (!list) { return; }
                var q = input.value.trim().toLowerCase();
                list.querySelectorAll('.hsw-af__checkrow').forEach(function (row) {
                        var name = row.textContent.trim().toLowerCase();
                        row.style.display = (!q || -1 !== name.indexOf(q)) ? '' : 'none';
                });
        });

        /* ---------- Price slider sync (max slider ↔ تا input ↔ label) ---------- */
        function updatePriceUI(range) {
                var root = range.closest('.hsw-af__form');
                if (!root) { return; }
                var num = root.querySelector('[data-hsw-pricemaxinput]');
                var label = root.querySelector('[data-hsw-mprice]');
                if (num && !num.disabled) { num.value = range.value; }
                if (label) {
                        var cur = (window.hswArchiveData && hswArchiveData.currency) ? ' ' + hswArchiveData.currency : '';
                        label.textContent = 'تا ' + fmt(range.value) + cur;
                }
        }
        document.addEventListener('input', function (e) {
                var range = e.target.closest('[data-hsw-pricemax]');
                if (range) { updatePriceUI(range); }
        });

        /* ---------- Memory chips + color swatches → hidden filter inputs ---------- */
        function multiTaxInput(form, tax) {
                return form.querySelector('[data-hsw-meminput="' + tax + '"], [data-hsw-colorinput="' + tax + '"]');
        }
        function toggleMulti(btn, cls) {
                var form = btn.closest('.hsw-af__form');
                if (!form) { return; }
                var tax = btn.getAttribute('data-hsw-tax') || '';
                var value = btn.getAttribute(cls) || '';
                if (!tax || !value) { return; }
                var input = multiTaxInput(form, tax);
                if (!input) { return; }
                var slugs = input.value ? input.value.split(',').filter(Boolean) : [];
                var i = slugs.indexOf(value);
                if (-1 === i) { slugs.push(value); } else { slugs.splice(i, 1); }
                input.value = slugs.join(',');
                btn.classList.toggle('is-active', -1 !== slugs.indexOf(value));
                /* keep all sibling chips (desktop + mobile) in sync */
                var same = form.querySelectorAll('[' + cls.replace('data-hsw-', 'data-hsw-') + '="' + value + '"][data-hsw-tax="' + tax + '"]');
                same.forEach(function (el) { el.classList.toggle('is-active', -1 !== slugs.indexOf(value)); });
        }
        document.addEventListener('click', function (e) {
                var mem = e.target.closest('[data-hsw-mem]');
                if (mem) { toggleMulti(mem, 'data-hsw-mem'); return; }
                var col = e.target.closest('[data-hsw-color]');
                if (col) { toggleMulti(col, 'data-hsw-color'); }
        });

        /* ---------- Filter form: drop empty params on submit ---------- */
        document.addEventListener('submit', function (e) {
                var form = e.target.closest('.hsw-af__form');
                if (!form) { return; }
                form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                        if (!cb.checked) { cb.disabled = true; }
                });
                form.querySelectorAll('input[type="number"], input[type="hidden"]').forEach(function (inp) {
                        if ('' === String(inp.value).trim()) { inp.disabled = true; }
                });
                var sheet = form.querySelector('.hsw-asheet.is-open');
                if (sheet) { closeSheet(sheet); }
        });

        /* ---------- Add to cart (AJAX, same contract as the loop widgets) ---------- */
        function addToCart(btn) {
                var pid = parseInt(btn.getAttribute('data-hsw-add'), 10) || 0;
                if (!pid) { return; }
                var data = (window.hswArchiveData || {});
                var endpoint = data.wcAjax || '/?wc-ajax=add_to_cart';
                btn.classList.add('is-busy');
                btn.style.pointerEvents = 'none';
                var body = new URLSearchParams();
                body.set('product_id', String(pid));
                body.set('quantity', '1');
                fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                }).then(function () {
                        btn.classList.add('is-added');
                        if (data.addedText) {
                                var ic = btn.querySelector('.hsw-ic');
                                if (ic) { ic.textContent = 'check_circle'; }
                                btn.setAttribute('title', data.addedText);
                        }
                        /* WooCommerce cart fragments refresh (jQuery event). */
                        if (window.jQuery) { window.jQuery(document.body).trigger('wc_fragment_refresh'); }
                        setTimeout(function () {
                                btn.classList.remove('is-added', 'is-busy');
                                btn.style.pointerEvents = '';
                                var ic = btn.querySelector('.hsw-ic');
                                if (ic) { ic.textContent = btn.classList.contains('hsw-ag__cta--m') ? 'add' : 'add_shopping_cart'; }
                        }, 1800);
                }).catch(function () {
                        btn.classList.remove('is-busy');
                        btn.style.pointerEvents = '';
                });
        }
        document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-hsw-add]');
                if (!btn) { return; }
                e.preventDefault();
                addToCart(btn);
        });

        /* ---------- Wishlist (visual + localStorage) ---------- */
        var WISH_KEY = 'hsw_wishlist';
        function wishList() {
                try { return JSON.parse(localStorage.getItem(WISH_KEY) || '[]'); } catch (err) { return []; }
        }
        function saveWish(list) {
                try { localStorage.setItem(WISH_KEY, JSON.stringify(list)); } catch (err) { /* storage unavailable */ }
        }
        function initWish() {
                var list = wishList();
                document.querySelectorAll('[data-hsw-wish]').forEach(function (btn) {
                        var id = btn.getAttribute('data-hsw-wish');
                        if (id && -1 !== list.indexOf(id)) { btn.classList.add('is-active'); }
                });
        }
        document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-hsw-wish]');
                if (!btn) { return; }
                e.preventDefault();
                var id = btn.getAttribute('data-hsw-wish');
                var list = wishList();
                var i = list.indexOf(id);
                if (-1 === i) { list.push(id); } else { list.splice(i, 1); }
                saveWish(list);
                btn.classList.toggle('is-active', -1 !== list.indexOf(id));
        });
        if ('loading' !== document.readyState) { initWish(); } else { document.addEventListener('DOMContentLoaded', initWish); }
})();
