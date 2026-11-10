// Browser regression with a representative Elementor override, no live site.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
(async () => {
  const browser = await chromium.launch({headless:true, ...(process.env.HSW_BROWSER_PATH ? {executablePath:process.env.HSW_BROWSER_PATH} : {})});
  try {
    const page = await browser.newPage();
    const css = fs.readFileSync(path.join(__dirname, '../assets/css/hsw-post.css'), 'utf8');
    const js = fs.readFileSync(path.join(__dirname, '../assets/js/hsw-post.js'), 'utf8');
    await page.route('http://example.test/', route => route.fulfill({contentType:'text/html', body:'<!doctype html><title>Fixture</title>'}));
    await page.goto('http://example.test/');
    await page.setContent(`<style>${css}</style><style>.elementor-element-test .hsw-pp__body,.elementor-element-test .hsw-pp__body p { color: rgb(12,34,56); }</style>
      <div id="target">outside article</div><div class="elementor-element-test"><div class="hsw-pp" data-hsw-pp data-post-id="123"><button data-hsw-pp-like aria-pressed="false"><span class="hsw-pp__docklabel">پسند</span></button><a data-hsw-pp-toclink href="#target">heading</a><div data-hsw-pp-body class="hsw-pp__body"><p>متن</p><h2 id="target">Heading</h2></div></div></div>`);
    await page.addScriptTag({content: js});
    assert.equal(await page.locator('.hsw-pp__body p').evaluate(e => getComputedStyle(e).color), 'rgb(12, 34, 56)', 'Elementor body color overrides palette');
    await page.locator('[data-hsw-pp-like]').click();
    assert.equal(await page.locator('.hsw-pp__docklabel').textContent(), 'پسند', 'like does not display a fake count');
    assert.equal(await page.locator('[data-hsw-pp-like]').getAttribute('aria-pressed'), 'true');
    await page.evaluate(() => { window._clickedHeading = null; document.querySelectorAll('#target').forEach(e => e.scrollIntoView = () => { window._clickedHeading = e.textContent; }); });
    await page.locator('[data-hsw-pp-toclink]').click();
    assert.equal(await page.evaluate(() => window._clickedHeading), 'Heading', 'TOC stays within this article');
    console.log('PASS post body override, local like and article-scoped TOC');
  } finally { await browser.close(); }
})().catch(e => {console.error(e); process.exitCode=1;});
