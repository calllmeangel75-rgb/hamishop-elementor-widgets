// Icon regression probe: every .hsw-ic on a page must render as a single glyph.
// A ligature that failed to resolve paints the raw name, so the element gets
// much wider than its own font-size. That is the signature we hunt for.
const { chromium } = require('playwright');

const BASE = 'http://localhost/sks';
const PAGES = process.argv.slice(2);

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  let bad = 0;
  let total = 0;

  for (const p of PAGES) {
    await page.goto(BASE + p, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(600);

    const report = await page.evaluate(async () => {
      // Make sure the icon font is actually decoded before measuring.
      if (document.fonts) {
        await document.fonts.ready;
        try { await document.fonts.load('24px "Material Symbols Outlined"'); } catch (e) {}
      }
      const out = [];
      document.querySelectorAll('.hsw-ic').forEach((el) => {
        const cs = getComputedStyle(el);
        const fs = parseFloat(cs.fontSize) || 16;
        const r = el.getBoundingClientRect();
        const text = (el.textContent || '').trim();
        out.push({
          text,
          w: Math.round(r.width * 10) / 10,
          fs: Math.round(fs * 10) / 10,
          ratio: Math.round((r.width / fs) * 100) / 100,
          family: cs.fontFamily,
          visible: r.width > 0 && r.height > 0,
        });
      });
      return out;
    });

    const broken = report.filter((x) => x.visible && (x.ratio > 1.6 || !/Material Symbols/.test(x.family)));
    total += report.length;
    bad += broken.length;

    console.log(`\n=== ${p} ===  icons:${report.length}  suspicious:${broken.length}`);
    const seen = new Set();
    for (const b of broken) {
      const k = b.text + '|' + b.ratio;
      if (seen.has(k)) continue;
      seen.add(k);
      console.log(`   BAD  "${b.text}"  width=${b.w}  font-size=${b.fs}  ratio=${b.ratio}  family=${b.family}`);
    }
    if (!report.length) console.log('   (no .hsw-ic on this page)');
  }

  console.log(`\nTOTAL icons: ${total}   suspicious: ${bad}`);
  await browser.close();
  process.exit(bad ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
