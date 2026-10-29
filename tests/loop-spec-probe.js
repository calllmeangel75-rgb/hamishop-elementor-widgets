// Verifies the loop-widget brief items: preload, dynamic sizes, added_to_cart,
// timer end states, dark palette and above-the-fold metrics.
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const URL = BASE + '/hsw-loop-test/';
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
  page.on('pageerror', (e) => errors.push(e.message));

  // metrics collector must be installed before the page scripts run
  await page.addInitScript(() => {
    window.__hswMetrics = { cls: 0, lcp: 0 };
    new PerformanceObserver((list) => {
      list.getEntries().forEach((e) => {
        if (!e.hadRecentInput) { window.__hswMetrics.cls += e.value; }
      });
    }).observe({ type: 'layout-shift', buffered: true });
    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      window.__hswMetrics.lcp = entries[entries.length - 1].startTime;
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  });

  await page.goto(URL, { waitUntil: 'load' });
  await page.waitForTimeout(1200);

  /* ---------- markup ---------- */
  const markup = await page.evaluate(() => {
    const preloads = [...document.querySelectorAll('link[rel="preload"][as="image"]')];
    const imgs = [...document.querySelectorAll('.hsw-loop__img, .hsw-loop__placeholder')];
    const roots = [...document.querySelectorAll('.hsw-loop')];
    return {
      preloadCount: preloads.length,
      preloadHasSrcset: preloads.every((l) => l.hasAttribute('imagesrcset') || !l.hasAttribute('href')),
      sizes: document.querySelector('.hsw-loop__img') ? document.querySelector('.hsw-loop__img').getAttribute('sizes') : '',
      eager: imgs.filter((i) => i.tagName === 'IMG' && i.getAttribute('loading') === 'eager').length,
      restLazy: imgs.filter((i) => i.tagName === 'IMG' && i.getAttribute('loading') !== 'eager')
        .every((i) => i.getAttribute('loading') === 'lazy'),
      firstEager: imgs[0] && imgs[0].tagName === 'IMG' ? imgs[0].getAttribute('loading') : 'n/a',
      themes: roots.map((r) => r.getAttribute('data-hsw-theme')),
      subtitle: (() => {
        const s = document.querySelector('.hsw-loop--deal .hsw-loop__subtitle');
        return s ? getComputedStyle(s).color : '';
      })(),
      cardContain: (() => {
        const c = document.querySelector('.hsw-loop__card');
        return c ? getComputedStyle(c).contain : '';
      })(),
      metrics: window.__hswMetrics,
    };
  });

  check('one image preload per page', markup.preloadCount === 1, String(markup.preloadCount));
  check('preload carries a srcset', markup.preloadHasSrcset);
  check('sizes follows cards-per-view', /calc\(100vw \/ 2\.2\)/.test(markup.sizes), markup.sizes);
  check('later images stay lazy', markup.restLazy && markup.eager >= 1, 'eager=' + markup.eager);
  check('theme attribute on every widget', markup.themes.length === 3 && markup.themes.every((t) => t === 'light'), markup.themes.join(','));
  check('deal subtitle is opaque white', markup.subtitle === 'rgb(255, 255, 255)', markup.subtitle);
  check('card keeps paint containment', /layout|content|strict/.test(markup.cardContain || ''), markup.cardContain);

  /* ---------- above-the-fold metrics (read once, right after load) ---------- */
  const metrics = await page.evaluate(() => ({
    cls: Math.round((window.__hswMetrics.cls || 0) * 10000) / 10000,
    lcp: Math.round(window.__hswMetrics.lcp || 0),
  }));
  check('CLS below 0.02 on mobile', metrics.cls < 0.02, JSON.stringify(metrics));

  /* ---------- added_to_cart ---------- */
  await page.evaluate(() => {
    window.__hswAdded = 0;
    window.__hswAddedHash = '';
    if (window.jQuery) {
      window.jQuery(document.body).on('added_to_cart', (e, fragments, hash) => {
        window.__hswAdded += 1;
        window.__hswAddedHash = String(hash || '');
      });
    }
  });
  const btn = await page.$('.hsw-loop--best .hsw-loop__btn[data-product_id]');
  if (btn) {
    await btn.click({ force: true });
    await page.waitForTimeout(2200);
    const fired = await page.evaluate(() => ({ n: window.__hswAdded, hash: window.__hswAddedHash }));
    check('added_to_cart fires from a loop card', fired.n === 1, JSON.stringify(fired));
  } else {
    check('added_to_cart fires from a loop card', false, 'no add button');
  }

  /* ---------- dark palette ---------- */
  const dark = await page.evaluate(() => {
    const root = document.querySelector('.hsw-loop');
    root.setAttribute('data-hsw-theme', 'dark');
    const bg = getComputedStyle(root).getPropertyValue('--hsw-loop-bg').trim();
    const card = getComputedStyle(root.querySelector('.hsw-loop__card') || root).backgroundColor;
    root.setAttribute('data-hsw-theme', 'light');
    return { bg, card };
  });
  check('dark theme swaps the surface tokens', dark.bg === '#14161c', JSON.stringify(dark));

  /* ---------- timer end states ---------- */
  const timerStates = await page.evaluate(async () => {
    const root = document.querySelector('.hsw-loop');
    const make = (action, cls) => {
      const el = document.createElement('div');
      el.className = 'hsw-loop__timer ' + cls;
      el.setAttribute('data-hsw-end', String(Math.floor(Date.now() / 1000) - 5));
      el.setAttribute('data-hsw-end-action', action);
      el.setAttribute('data-hsw-end-text', 'پایان یافت');
      el.innerHTML = '<span data-hsw-t="h">00</span>';
      root.appendChild(el);
      return el;
    };
    const hideTimer = make('hide_timer', 't-hide');
    const hideBlock = make('hide_block', 't-block');
    const endedNote = make('ended_note', 't-note');

    document.dispatchEvent(new Event('visibilitychange'));
    await new Promise((r) => setTimeout(r, 1400));

    const out = {
      hideTimer: hideTimer.hidden,
      blockHidden: root.hasAttribute('hidden') || root.classList.contains('is-ended-hidden'),
      note: (endedNote.innerText || '').replace(/\s+/g, ' ').trim(),
    };

    // undo so the page stays usable for the metric read
    hideTimer.remove();
    hideBlock.remove();
    endedNote.remove();
    root.removeAttribute('hidden');
    root.classList.remove('is-ended-hidden');
    return out;
  });

  check('hide_timer hides only the timer', timerStates.hideTimer === true, JSON.stringify(timerStates));
  check('hide_block hides the widget', timerStates.blockHidden === true);
  check('ended_note swaps in the text', /پایان یافت/.test(timerStates.note), timerStates.note);

  check('no console errors', errors.length === 0, errors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
