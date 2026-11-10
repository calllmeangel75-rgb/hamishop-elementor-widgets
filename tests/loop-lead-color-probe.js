// Standalone browser regression: node tests/loop-lead-color-probe.js
// Requires Playwright and its Chromium browser; no live site or database.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const css = fs.readFileSync(path.join(__dirname, '../assets/css/hsw-loop.css'), 'utf8');
    await page.setContent(`<style>${css}</style><style>
      .site a { color: black; } .site a span { color: white; }
      .site a svg { color: white; fill: white; }
      .elementor-element-test .hsw-loop__lead { --hsw-loop-lead-btn-c: rgb(12, 34, 56); --hsw-loop-lead-btn-c-h: rgb(210, 120, 30); }
    </style><div class="site elementor-element-test"><section class="hsw-loop hsw-loop--hsw-wonder-deals"><div class="hsw-loop__lead"><span class="hsw-loop__lead-title">title</span><a href="#" class="hsw-loop__lead-btn"><span>مشاهده همه</span><svg class="hsw-ic"><path d="M0 0h10v10H0z"></path></svg></a></div></section></div>`);
    const read = () => page.locator('.hsw-loop__lead-btn').evaluate(el => ({
      link: getComputedStyle(el).color,
      text: getComputedStyle(el.querySelector('span')).color,
      icon: getComputedStyle(el.querySelector('svg')).color,
      fill: getComputedStyle(el.querySelector('svg')).fill,
      title: getComputedStyle(el.parentElement.querySelector('.hsw-loop__lead-title')).color,
    }));
    const normal = await read();
    for (const key of ['link', 'text', 'icon', 'fill']) assert.equal(normal[key], 'rgb(12, 34, 56)', `normal ${key}`);
    await page.locator('.hsw-loop__lead-btn').hover();
    await page.waitForTimeout(250); // transition completes before reading computed colours
    const hover = await read();
    for (const key of ['link', 'text', 'icon', 'fill']) assert.equal(hover[key], 'rgb(210, 120, 30)', `hover ${key}`);
    assert.equal(hover.title, normal.title, 'banner title unaffected');
    console.log('PASS lead button label, sprite and hover follow their independent controls');
  } finally { await browser.close(); }
})().catch(err => { console.error(err); process.exitCode = 1; });
