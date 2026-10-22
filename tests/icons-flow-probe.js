// Cart -> checkout -> payment flow probe: fills the cart through the real
// WooCommerce AJAX endpoint, then measures every .hsw-ic on each step.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const PRODUCT = process.argv[2];
const STEPS = ['/cart/', '/checkout/', '/payment/'];

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  let bad = 0;
  let total = 0;

  // Seed the cart through WooCommerce's own add-to-cart handler so the
  // checkout / payment steps are reachable.
  await page.goto(BASE + '/?add-to-cart=' + PRODUCT, { waitUntil: 'networkidle', timeout: 60000 });
  const seeded = await page.evaluate(async () => {
    const r = await fetch('/sks/?wc-ajax=get_refreshed_fragments', { credentials: 'include' });
    const j = await r.json();
    const frag = j.fragments ? Object.keys(j.fragments).join(',') : 'none';
    return frag;
  });
  console.log('cart seeded, fragments:', seeded);

  for (const step of STEPS) {
    await page.goto(BASE + step, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(700);

    const report = await page.evaluate(async () => {
      if (document.fonts) {
        await document.fonts.ready;
        try { await document.fonts.load('24px "Material Symbols Outlined"'); } catch (e) {}
      }
      const out = [];
      document.querySelectorAll('.hsw-ic').forEach((el) => {
        const cs = getComputedStyle(el);
        const fs = parseFloat(cs.fontSize) || 16;
        const r = el.getBoundingClientRect();
        out.push({
          text: (el.textContent || '').trim(),
          w: Math.round(r.width * 10) / 10,
          fs: Math.round(fs * 10) / 10,
          ratio: Math.round((r.width / fs) * 100) / 100,
          family: cs.fontFamily,
          visible: r.width > 0 && r.height > 0,
        });
      });
      return { url: location.pathname, items: out };
    });

    const broken = report.items.filter((x) => x.visible && (x.ratio > 1.6 || !/Material Symbols/.test(x.family)));
    total += report.items.length;
    bad += broken.length;
    console.log(`\n=== ${step}  (landed on ${report.url}) ===  icons:${report.items.length}  suspicious:${broken.length}`);
    const seen = new Set();
    for (const b of broken) {
      const k = b.text + '|' + b.ratio;
      if (seen.has(k)) continue;
      seen.add(k);
      console.log(`   BAD  "${b.text}"  width=${b.w}  font-size=${b.fs}  ratio=${b.ratio}  family=${b.family}`);
    }
  }

  console.log(`\nTOTAL icons: ${total}   suspicious: ${bad}`);
  await browser.close();
  process.exit(bad ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
