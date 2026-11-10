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
    await page.setContent(`<style>${css}</style><style>.elementor-element-test .hsw-pp__body,.elementor-element-test .hsw-pp__body p { color: rgb(12,34,56); font-size: 20px; }</style>
      <div id="target">outside article</div><div class="elementor-element-test"><div class="hsw-pp" data-hsw-pp data-post-id="123" data-post-url="http://example.test/article/"><button data-hsw-pp-font>Font</button><button data-hsw-pp-bookmark><svg class="hsw-ic"><use href="#icon"></use></svg></button><button data-hsw-pp-share>Share</button><button data-hsw-pp-like aria-pressed="false"><span class="hsw-pp__docklabel">پسند</span></button><a data-hsw-pp-toclink href="#target">heading</a><div data-hsw-pp-body class="hsw-pp__body"><p>متن</p><h2 id="target">Heading</h2></div></div></div>`);
    await page.addScriptTag({content: js});
    assert.equal(await page.locator('.hsw-pp__body p').evaluate(e => getComputedStyle(e).color), 'rgb(12, 34, 56)', 'Elementor body color overrides palette');
    const para = page.locator('.hsw-pp__body p');
    await page.locator('[data-hsw-pp-font]').click();
    assert.equal(await para.evaluate(e => getComputedStyle(e).fontSize), '23px', 'reader toggle scales explicit Elementor font size');
    await page.locator('[data-hsw-pp-font]').click();
    assert.equal(await para.evaluate(e => getComputedStyle(e).fontSize), '20px', 'reader toggle restores explicit Elementor font size');
    await page.locator('[data-hsw-pp-bookmark]').click();
    assert.equal(await page.locator('[data-hsw-pp-bookmark]').getAttribute('aria-pressed'), 'true', 'bookmark works even if icon helper is absent');
    await page.evaluate(() => {
      Object.defineProperty(navigator, 'share', {configurable:true, value:undefined});
      Object.defineProperty(navigator, 'clipboard', {configurable:true, value:{writeText: url => {window._copied = url; return Promise.resolve();}}});
    });
    await page.locator('[data-hsw-pp-share]').click();
    assert.equal(await page.evaluate(() => window._copied), 'http://example.test/article/', 'share uses actual article URL');
    assert.match(await page.locator('[data-hsw-pp-share-status]').textContent(), /کپی شد/);
    await page.locator('[data-hsw-pp-like]').click();
    assert.equal(await page.locator('.hsw-pp__docklabel').textContent(), 'پسند', 'like does not display a fake count');
    assert.equal(await page.locator('[data-hsw-pp-like]').getAttribute('aria-pressed'), 'true');
    await page.evaluate(() => { window._clickedHeading = null; document.querySelectorAll('#target').forEach(e => e.scrollIntoView = () => { window._clickedHeading = e.textContent; }); });
    await page.locator('[data-hsw-pp-toclink]').click();
    assert.equal(await page.evaluate(() => window._clickedHeading), 'Heading', 'TOC stays within this article');
    console.log('PASS post body override, local like and article-scoped TOC');
  } finally { await browser.close(); }
})().catch(e => {console.error(e); process.exitCode=1;});
