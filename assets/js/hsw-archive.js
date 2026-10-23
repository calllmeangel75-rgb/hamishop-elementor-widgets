/**
 * HamiShop Elementor Widgets — Archive frontend behavior (v1.9.0).
 * Vanilla JS, no dependencies, event delegation only.
 *
 * Sheets/modals, SEO description toggle, filter box collapse, in-list search,
 * price slider sync, chips/swatches → one hidden input per taxonomy,
 * desktop auto-submit (with a no-JS submit fallback), checkbox mirroring
 * between the desktop sidebar and the mobile sheet, add-to-cart, wishlist.
 */
(function () {
        'use strict';

        /* Marks the document as JS-capable so no-JS fallbacks can hide. */
        document.documentElement.classList.add('hsw-js');

        var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
        function fa(n) { return String(n).replace(/[0-9]/g, function (d) { return FA[d]; }); }
        function fmt(n) { return fa(Number(n).toLocaleString('en-US').replace(/,/g, ',')); }

        /* ======================= SHEETS & MODALS ======================= */

        var lastFocus = null;

        function openSheet(name) {
                var el = document.querySelector('[data-hsw-sheet="' + name + '"]');
                if (!el) { return; }
                lastFocus = document.activeElement;
                el.classList.add('is-open');
                el.setAttribute('aria-hidden', 'false');
                document.body.classList.add('hsw-noscroll');
                var first = el.querySelector('button, a, input, [tabindex]');
                if (first) { first.focus({ preventScroll: true }); }
        }

        function closeSheet(sheet) {
                if (!sheet) { return; }
                sheet.classList.remove('is-open');
                sheet.setAttribute('aria-hidden', 'true');
                if (!document.querySelector('.hsw-asheet.is-open, .hsw-agd__modal.is-open')) {
                        document.body.classList.remove('hsw-noscroll');
                }
                if (lastFocus && typeof lastFocus.focus === 'function') {
                        lastFocus.focus({ preventScroll: true });
                        lastFocus = null;
                }
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
                        e.preventDefault();
                        closeSheet(closer.closest('.hsw-asheet, .hsw-agd__modal'));
                }
        });

        document.addEventListener('keydown', function (e) {
                if ('Escape' !== e.key) { return; }
                document.querySelectorAll('.hsw-asheet.is-open, .hsw-agd__modal.is-open').forEach(closeSheet);
        });

        /* ======================= SEO DESCRIPTION TOGGLE ======================= */

        document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-hsw-seo]');
                if (!btn) { return; }
                var box = btn.parentElement;
                var target = box ? box.querySelector('[data-hsw-seo-target]') : null;
                if (!target) { return; }
                var open = target.classList.toggle('is-open');
                target.classList.toggle('is-clamped', !open);
                var label = btn.querySelector('span');
                if (label) {
                        var closeTxt = btn.getAttribute('data-hsw-close-txt') || 'بستن';
                        if (!btn.hasAttribute('data-hsw-label')) {
                                btn.setAttribute('data-hsw-label', label.textContent);
                        }
                        label.textContent = open ? closeTxt : btn.getAttribute('data-hsw-label');
                }
                btn.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        /* ======================= FILTER BOX COLLAPSE ======================= */

        document.addEventListener('click', function (e) {
                var head = e.target.closest('.hsw-af__boxhead');
                if (!head) { return; }
                var box = head.closest('[data-hsw-collapse]');
                if (!box) { return; }
                var collapsed = box.classList.toggle('is-collapsed');
                head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });

        /* ======================= CATEGORY TREE ======================= */

        function setCatNodeOpen(li, open) {
                if (!li) { return; }
                var branch = li.querySelector('[data-hsw-catbranch]');
                var toggle = li.querySelector('[data-hsw-cattoggle]');
                li.classList.toggle('is-open', open);
                if (branch) { branch.hidden = !open; }
                if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        }

        function filterCatTree(input) {
                var box = input.closest('.hsw-af__box');
                var tree = box ? box.querySelector('[data-hsw-cattree]') : null;
                if (!tree) { return; }
                var empty = box.querySelector('[data-hsw-catempty]');
                var q = input.value.trim().toLowerCase();
                var nodes = tree.querySelectorAll('.hsw-af__catnode');
                var links = tree.querySelectorAll('[data-hsw-catname]');
                var showAll = tree.getAttribute('data-hsw-showall') === '1';

                if (!q) {
                        nodes.forEach(function (node) {
                                node.hidden = false;
                                node.classList.remove('is-search-hit');
                        });
                        if (!showAll) {
                                tree.querySelectorAll('.hsw-af__catnode.is-extra').forEach(function (node) {
                                        node.hidden = true;
                                });
                        }
                        nodes.forEach(function (node) {
                                var branch = node.querySelector('[data-hsw-catbranch]');
                                if (branch) { branch.hidden = !node.classList.contains('is-open'); }
                        });
                        if (empty) { empty.hidden = true; }
                        return;
                }

                nodes.forEach(function (node) {
                        node.hidden = true;
                        node.classList.remove('is-search-hit');
                });

                var hits = 0;
                links.forEach(function (link) {
                        var name = link.getAttribute('data-hsw-catname') || '';
                        if (-1 === name.indexOf(q)) { return; }
                        hits++;
                        var li = link.closest('.hsw-af__catnode');
                        while (li) {
                                li.hidden = false;
                                li.classList.add('is-search-hit');
                                var parentUl = li.parentElement;
                                if (!parentUl || !parentUl.hasAttribute('data-hsw-catbranch')) {
                                        li = null;
                                        continue;
                                }
                                parentUl.hidden = false;
                                var parentLi = parentUl.parentElement ? parentUl.parentElement.closest('.hsw-af__catnode') : null;
                                setCatNodeOpen(parentLi, true);
                                li = parentLi;
                        }
                });

                if (empty) { empty.hidden = hits > 0; }
        }

        document.addEventListener('click', function (e) {
                var toggle = e.target.closest('[data-hsw-cattoggle]');
                if (toggle) {
                        var node = toggle.closest('.hsw-af__catnode');
                        if (node) {
                                setCatNodeOpen(node, !node.classList.contains('is-open'));
                        }
                        return;
                }
                var more = e.target.closest('[data-hsw-catmore]');
                if (!more) { return; }
                var box = more.closest('.hsw-af__box');
                var tree = box ? box.querySelector('[data-hsw-cattree]') : null;
                if (!tree) { return; }
                tree.querySelectorAll('.hsw-af__catnode.is-extra').forEach(function (extra) {
                        extra.hidden = false;
                });
                tree.setAttribute('data-hsw-showall', '1');
                more.hidden = true;
        });

        document.addEventListener('input', function (e) {
                var catInput = e.target.closest('[data-hsw-catsearch]');
                if (catInput) {
                        filterCatTree(catInput);
                }
        });

        /* ======================= IN-LIST SEARCH ======================= */

        document.addEventListener('input', function (e) {
                if (e.target.closest('[data-hsw-catsearch]')) { return; }
                var input = e.target.closest('[data-hsw-search]');
                if (!input) { return; }
                var box = input.closest('.hsw-af__box');
                var list = box ? box.querySelector('[data-hsw-termlist]') : null;
                if (!list) { return; }
                var q = input.value.trim().toLowerCase();
                list.querySelectorAll('.hsw-af__checkrow').forEach(function (row) {
                        var name = (row.textContent || '').trim().toLowerCase();
                        row.style.display = (!q || -1 !== name.indexOf(q)) ? '' : 'none';
                });
        });

        /* ======================= PRICE SLIDER ======================= */

        function priceRoot(el) {
                return el.closest('.hsw-af__form');
        }

        function syncRange(form) {
                var range = form.querySelector('[data-hsw-pricemax]');
                var num = form.querySelector('[data-hsw-pricemaxinput]');
                if (!range || !num) { return; }
                /* At the slider ceiling the value is the bound, not a filter. */
                num.value = Number(range.value) >= Number(range.max) ? '' : range.value;
        }

        function syncNumber(el) {
                var form = priceRoot(el);
                if (!form) { return; }
                var range = form.querySelector('[data-hsw-pricemax]');
                var num = form.querySelector('[data-hsw-pricemaxinput]');
                if (!range || !num) { return; }
                var value = String(num.value).trim();
                if ('' === value) {
                        range.value = range.max;
                        return;
                }
                if (Number(value) >= Number(range.min) && Number(value) <= Number(range.max)) {
                        range.value = value;
                }
        }

        document.addEventListener('input', function (e) {
                if (e.target.closest('[data-hsw-pricemax]')) {
                        syncRange(priceRoot(e.target));
                        queueAutoSubmit(e.target);
                        return;
                }
                if (e.target.closest('[data-hsw-pricemaxinput]')) {
                        syncNumber(e.target);
                }
        });
        document.addEventListener('change', function (e) {
                if (e.target.closest('[data-hsw-pricemaxinput]')) {
                        queueAutoSubmit(e.target);
                }
        });

        /* ======================= CHIPS / SWATCHES → HIDDEN INPUT ======================= */

        function taxInput(form, tax) {
                return form.querySelector('[data-hsw-taxinput="' + tax + '"]');
        }

        function paintChips(form, tax, slugs) {
                form.querySelectorAll('[data-hsw-tax="' + tax + '"]').forEach(function (el) {
                        el.classList.toggle('is-active', -1 !== slugs.indexOf(el.getAttribute('data-hsw-value')));
                });
        }

        document.addEventListener('click', function (e) {
                var chip = e.target.closest('[data-hsw-tax][data-hsw-value]');
                if (!chip) { return; }
                var form = chip.closest('.hsw-af__form');
                if (!form) { return; }
                var tax = chip.getAttribute('data-hsw-tax');
                var value = chip.getAttribute('data-hsw-value');
                var input = taxInput(form, tax);
                if (!input) { return; }
                var slugs = input.value ? input.value.split(',').filter(Boolean) : [];
                var i = slugs.indexOf(value);
                if (-1 === i) { slugs.push(value); } else { slugs.splice(i, 1); }
                input.value = slugs.join(',');
                paintChips(form, tax, slugs);
                if (!chip.closest('.hsw-asheet')) { queueAutoSubmit(chip); }
        });

        /* ======================= CHECKBOX MIRRORING ======================= */

        function mirror(form, cb) {
                var name = cb.getAttribute('name');
                var value = cb.getAttribute('value');
                if (!name) { return; }
                form.querySelectorAll('input[type="checkbox"][name="' + name + '"][value="' + value + '"]').forEach(function (other) {
                        if (other !== cb) { other.checked = cb.checked; }
                });
        }

        /* ======================= AUTO-SUBMIT (desktop) ======================= */

        var autoTimer = null;
        var autoForm = null;

        function queueAutoSubmit(el) {
                if (el.closest('.hsw-asheet')) { return; }
                var form = el.closest('.hsw-af__form');
                if (!form) { return; }
                autoForm = form;
                if (autoTimer) { clearTimeout(autoTimer); }
                autoTimer = setTimeout(function () {
                        if (!autoForm) { return; }
                        var f = autoForm;
                        autoForm = null;
                        submitForm(f);
                }, 650);
        }

        function submitForm(form) {
                if (form.requestSubmit) {
                        form.requestSubmit();
                } else {
                        form.submit();
                }
        }

        document.addEventListener('change', function (e) {
                var cb = e.target.closest('.hsw-af__check, .hsw-af__switchinput');
                if (!cb) { return; }
                var form = cb.closest('.hsw-af__form');
                if (form) { mirror(form, cb); }
                if (!cb.closest('.hsw-asheet')) { queueAutoSubmit(cb); }
        });

        /* ======================= SUBMIT: drop empty params ======================= */

        document.addEventListener('submit', function (e) {
                var form = e.target.closest('.hsw-af__form');
                if (!form) { return; }
                if (autoTimer) { clearTimeout(autoTimer); }

                /* Price fields equal to the slider bounds are not a filter. */
                var range = form.querySelector('[data-hsw-pricemax]');
                if (range) {
                        var rmin = Number(range.min);
                        var rmax = Number(range.max);
                        form.querySelectorAll('input[name="min_price"]').forEach(function (inp) {
                                if ('' === String(inp.value).trim() || Number(inp.value) <= rmin) { inp.value = ''; }
                        });
                        form.querySelectorAll('input[name="max_price"]').forEach(function (inp) {
                                if ('' === String(inp.value).trim() || Number(inp.value) >= rmax) { inp.value = ''; }
                        });
                }

                /* Duplicate checkbox names → single CSV field (WooCommerce contract). */
                var groups = {};
                form.querySelectorAll('input[type="checkbox"][name^="filter_"]').forEach(function (cb) {
                        var name = cb.getAttribute('name');
                        if (!groups[name]) { groups[name] = []; }
                        if (cb.checked && -1 === groups[name].indexOf(cb.value)) { groups[name].push(cb.value); }
                });
                Object.keys(groups).forEach(function (name) {
                        form.querySelectorAll('input[type="checkbox"][name="' + name.replace(/"/g, '\\"') + '"]').forEach(function (cb) {
                                cb.disabled = true;
                        });
                        if (!groups[name].length) { return; }
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = name.replace(/\[\]$/, '');
                        hidden.value = groups[name].join(',');
                        form.appendChild(hidden);
                });

                form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                        cb.disabled = !cb.checked;
                });
                form.querySelectorAll('input[type="number"], input[type="range"], input[type="hidden"]').forEach(function (inp) {
                        if ('' === String(inp.value).trim()) { inp.disabled = true; }
                });
                var sheet = form.querySelector('.hsw-asheet.is-open');
                if (sheet) { closeSheet(sheet); }
        });

        /* ======================= STORE INPUTS (template → form) ======================= */

        function initStore() {
                document.querySelectorAll('.hsw-af__form template[data-hsw-store]').forEach(function (tpl) {
                        var form = tpl.parentElement;
                        if (!form) { return; }
                        var wrap = document.createElement('div');
                        wrap.className = 'hsw-af__store';
                        wrap.hidden = true;
                        wrap.innerHTML = tpl.innerHTML;
                        form.insertBefore(wrap, form.firstChild);
                        tpl.remove();
                });
        }
        if ('loading' !== document.readyState) { initStore(); } else { document.addEventListener('DOMContentLoaded', initStore); }

        /* ======================= ADD TO CART (AJAX) ======================= */

        /* Shared toast — the archive had none, so a click produced no feedback. */
        var toastEl = null;
        function toast(msg, tone) {
                if (!msg) { return; }
                if (!toastEl) {
                        toastEl = document.createElement('div');
                        toastEl.className = 'hsw-toast';
                        document.body.appendChild(toastEl);
                }
                toastEl.textContent = msg;
                toastEl.classList.toggle('is-error', tone === 'error');
                toastEl.classList.add('is-show');
                clearTimeout(toastEl._t);
                toastEl._t = setTimeout(function () { toastEl.classList.remove('is-show'); }, 2600);
        }

        function resetBtn(btn) {
                btn.classList.remove('is-added', 'is-busy');
                btn.style.pointerEvents = '';
                var ic = btn.querySelector('.hsw-ic');
                if (ic) { ic.textContent = btn.classList.contains('hsw-ag__cta--m') ? 'add' : 'add_shopping_cart'; }
        }

        function addToCart(btn) {
                var pid = parseInt(btn.getAttribute('data-hsw-add'), 10) || 0;
                if (!pid || btn.classList.contains('is-busy')) { return; }
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
                }).then(function (r) {
                        return r.json();
                }).then(function (res) {
                        /*
                         * Variable products (and anything that cannot be added
                         * from a listing) come back as {error:true, product_url}.
                         * The old handler threw the response away, so the button
                         * flashed a tick and nothing else happened — the customer
                         * had to work out on their own that they needed the
                         * product page.
                         */
                        if (res && res.error && res.product_url) {
                                window.location.href = res.product_url;
                                return;
                        }
                        if (res && res.error) {
                                resetBtn(btn);
                                toast(data.errorText || 'افزودن به سبد خرید ممکن نشد.', 'error');
                                return;
                        }

                        btn.classList.add('is-added');
                        var ic = btn.querySelector('.hsw-ic');
                        if (ic) { ic.textContent = 'check_circle'; }
                        btn.setAttribute('title', data.addedText || '');

                        /*
                         * One of the two, never both: the fragments reply already
                         * carries the fresh cart markup, so reusing it avoids a
                         * second round-trip to rebuild the header badge.
                         */
                        if (window.jQuery && res && res.fragments) {
                                window.jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash]);
                        } else {
                                document.dispatchEvent(new CustomEvent('hsw_added_to_cart', { detail: { productId: pid } }));
                        }

                        toast(data.addedText);
                        setTimeout(function () { resetBtn(btn); }, 1800);
                }).catch(function () {
                        resetBtn(btn);
                        toast(data.errorText || 'خطا در ارتباط با سرور.', 'error');
                });
        }

        document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-hsw-add]');
                if (!btn) { return; }
                e.preventDefault();
                addToCart(btn);
        });

        /* ======================= WISHLIST ======================= */

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
