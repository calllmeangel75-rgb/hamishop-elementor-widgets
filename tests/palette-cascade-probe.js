// Proves the style cascade: dashboard palette (default) → per-widget preset /
// style control (priority for that widget).
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://localhost/sks';
const EXPECTED = process.argv[3] || ''; // accent the palette step wrote, e.g. #00a86b
let failures = 0;

const check = (label, ok, detail) => {
  if (!ok) { failures++; }
  console.log((ok ? 'ok   ' : 'FAIL ') + label + (detail ? ' — ' + detail : ''));
};

const hexToRgb = (hex) => {
  const h = String(hex).replace('#', '');
  const n = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  return 'rgb(' + [0, 2, 4].map((i) => parseInt(n.slice(i, i + 2), 16)).join(', ') + ')';
};

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') { errors.push(m.text()); } });
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(BASE + '/hsw-loop-test/', { waitUntil: 'load' });
  await page.waitForTimeout(800);

  /* 1. the palette publishes every token the loop family consumes */
  const css = await page.evaluate(() => (document.querySelector('#hsw-palette') || {}).textContent || '');
  const needed = ['--hsw-accent', '--hsw-pal-surface', '--hsw-pal-on-accent', '--hsw-pal-star', '--hsw-pal-border-strong', '--hsw-pal-ink-soft'];
  check('palette publishes every loop token', needed.every((t) => css.includes(t + ':')),
    needed.filter((t) => !css.includes(t + ':')).join(',') || 'all present');

  const state = await page.evaluate(() => {
    const pick = (root) => ({
      cls: root.className.replace(/\s+/g, ' '),
      preset: /hsw-loop--preset-/.test(root.className),
      accent: getComputedStyle(root).getPropertyValue('--hsw-accent').trim(),
      loopAccent: getComputedStyle(root).getPropertyValue('--hsw-loop-accent').trim(),
      price: (() => { const e = root.querySelector('.hsw-loop__price'); return e ? getComputedStyle(e).color : ''; })(),
      card: (() => { const e = root.querySelector('.hsw-loop__card'); return e ? getComputedStyle(e).backgroundColor : ''; })(),
      font: getComputedStyle(root).fontFamily,
    });
    const roots = [...document.querySelectorAll('.hsw-loop')].map(pick);
    return {
      paletteAccent: getComputedStyle(document.documentElement).getPropertyValue('--hsw-accent').trim(),
      onAccent: getComputedStyle(document.documentElement).getPropertyValue('--hsw-pal-on-accent').trim(),
      plain: roots.find((r) => !r.preset) || null,
      preset: roots.find((r) => r.preset) || null,
      count: roots.length,
    };
  });

  check('the test page has a preset widget and a plain one', !!state.plain && (!!state.preset || state.count > 0),
    'plain=' + (state.plain ? 'yes' : 'no') + ' preset=' + (state.preset ? 'yes' : 'no'));

  /* 2. palette → widget (plain widgets follow the dashboard colour) */
  if (EXPECTED && state.plain) {
    check('dashboard accent reaches a plain widget', state.plain.price === hexToRgb(EXPECTED),
      state.plain.price + ' vs ' + hexToRgb(EXPECTED));
  } else if (state.plain) {
    check('plain widget inherits the palette accent token', state.plain.loopAccent === state.paletteAccent || state.plain.accent === state.paletteAccent,
      'palette=' + state.paletteAccent + ' widget=' + state.plain.loopAccent);
  }

  /* 3. a per-widget preset keeps priority over the palette */
  if (state.preset && EXPECTED) {
    check('widget preset wins over the palette', state.preset.price !== hexToRgb(EXPECTED),
      'preset paints ' + state.preset.price + ' while the palette is ' + hexToRgb(EXPECTED));
  }

  /* 4. a per-element style control still wins (same declaration Elementor writes) */
  const overridden = await page.evaluate(() => {
    const root = [...document.querySelectorAll('.hsw-loop')].find((r) => !/hsw-loop--preset-/.test(r.className))
      || document.querySelector('.hsw-loop');
    root.style.setProperty('--hsw-loop-accent', '#7c3aed');
    root.style.setProperty('--hsw-loop-bg', '#f3f0ff');
    const price = root.querySelector('.hsw-loop__price');
    const card = root.querySelector('.hsw-loop__card');
    const out = {
      price: price ? getComputedStyle(price).color : '',
      card: card ? getComputedStyle(card).backgroundColor : '',
    };
    root.style.removeProperty('--hsw-loop-accent');
    root.style.removeProperty('--hsw-loop-bg');
    return out;
  });
  check('widget-level accent wins over the palette', overridden.price === 'rgb(124, 58, 237)', overridden.price);
  check('widget-level surface wins over the palette', overridden.card === 'rgb(243, 240, 255)', overridden.card);

  /* 5. changing a palette token repaints the plain widget (surface + accent) */
  const rootDriven = await page.evaluate(() => {
    const root = [...document.querySelectorAll('.hsw-loop')].find((r) => !/hsw-loop--preset-/.test(r.className));
    if (!root) { return null; }
    document.documentElement.style.setProperty('--hsw-accent', '#00875a');
    document.documentElement.style.setProperty('--hsw-pal-surface', '#f0fff8');
    const price = root.querySelector('.hsw-loop__price');
    const card = root.querySelector('.hsw-loop__card');
    const out = {
      price: price ? getComputedStyle(price).color : '',
      card: card ? getComputedStyle(card).backgroundColor : '',
    };
    document.documentElement.style.removeProperty('--hsw-accent');
    document.documentElement.style.removeProperty('--hsw-pal-surface');
    return out;
  });
  check('changing palette tokens repaints the widget',
    !!rootDriven && rootDriven.price === 'rgb(0, 135, 90)' && rootDriven.card === 'rgb(240, 255, 248)',
    JSON.stringify(rootDriven));

  /* 6. font: palette default, then the widget typography control */
  const fonts = await page.evaluate(() => {
    const root = document.querySelector('.hsw-loop');
    document.documentElement.style.setProperty('--hsw-font', '"Courier New", monospace');
    const fromPalette = getComputedStyle(root).fontFamily;
    root.style.fontFamily = '"Arial", sans-serif';
    const fromWidget = getComputedStyle(root).fontFamily;
    root.style.removeProperty('font-family');
    document.documentElement.style.removeProperty('--hsw-font');
    return { fromPalette, fromWidget };
  });
  check('palette font drives the widget', /Courier New/.test(fonts.fromPalette), fonts.fromPalette);
  check('widget typography wins over the palette font', /Arial/.test(fonts.fromWidget), fonts.fromWidget);

  /* 7. readable on-accent token is published (white for the default accents) */
  check('on-accent text token is published', ['#ffffff', 'rgb(255, 255, 255)', '#111827', 'rgb(17, 24, 39)'].includes(state.onAccent), state.onAccent);

  check('no console errors', errors.length === 0, errors.slice(0, 3).join(' | '));

  await browser.close();
  console.log(failures ? 'RESULT: ' + failures + ' problem(s)' : 'RESULT: clean');
  process.exit(failures ? 1 : 0);
})();
