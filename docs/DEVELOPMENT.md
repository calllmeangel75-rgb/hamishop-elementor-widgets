# توسعه

راهنمای کار روی خود افزونه: ابزارها، تست‌ها، افزودن ویجت و آیکون، قراردادها و فرآیند ریلیز.

## پیش‌نیازهای محلی

| ابزار | برای |
|---|---|
| PHP ۷.۴+ (CLI) | لینت، هارنس، بررسی‌های ساختاری |
| Python ۳.۱۰+ | ساخت دارایی‌ها، POT، اسپرایت، فونت، ZIP، تولید مستند |
| `pip install csscompressor rjsmin` | فقط برای `build-assets.py` |
| Node + Playwright (اختیاری) | پروب‌های مرورگری روی سایت واقعی |

## ابزارها

| فرمان | کار |
|---|---|
| `python tools/build-assets.py` | ساخت `.min.css` / `.min.js` از سورس |
| `python tools/build-pot.py` | بازسازی `languages/hamishop-elementor-widgets.pot` |
| `python tools/build-sprite.py` | ساخت اسپرایت SVG از `tools/_icons.txt` |
| `python tools/build-font.py` | زیرمجموعه‌سازی فونت وزیرمتن (نیازمند فایل کامل) |
| `python tools/build-release.py` | ساخت ZIP نصبی با نسخهٔ هدر |
| `php tools/ci-checks.php` | ۱۶ بررسی ساختاری (نسخه، دامنهٔ ترجمه، دیباگ، دارایی، کاتالوگ) |
| `php tools/dump-catalog.php` | چاپ کاتالوگ ویجت‌ها به JSON |
| `python tools/gen-widget-docs.py` | بازتولید `docs/WIDGETS.md` از کد |

> **قاعده:** فایل‌های `.min` را هرگز دستی ویرایش نکنید. CI تازه‌بودنشان را نسبت به سورس چک می‌کند و ویرایش دستی در اولین build از بین می‌رود.

## تست

```bash
# لینت همهٔ فایل‌ها
find . -name '*.php' -not -path './.git/*' -exec php -l {} \; | grep -v "No syntax errors"

# هارنس: همهٔ فایل‌ها به ترتیب وردپرس لود می‌شوند (fatalهای سطح declaration)
php tests/harness.php

# بررسی‌های ساختاری
php tools/ci-checks.php

# شبیه‌سازی آپدیت پس‌زمینه (بدون وردپرس)
php tests/qa/upgrader-tick-sim.php
```

**پروب‌های مرورگری** (`tests/*.js`) به یک وردپرس زنده با ووکامرس و المنتور نیاز دارند و در CI اجرا نمی‌شوند. هر پروب یک قابلیت را با عدد اثبات می‌کند (فیلتر آرشیو، مسیر تسویه، فاکتور، کیف پول، هدر موبایل، آبشار پالت).

### CI

`.github/workflows/ci.yml` روی هر push به `main` و هر PR چهار گیت را اجرا می‌کند:

| Job | چه می‌سنجد | چرا |
|---|---|---|
| `lint` | `php -l` روی PHP ۷.۴ تا ۸.۳ | ما «PHP ≥ ۷.۴» ادعا می‌کنیم؛ `str_contains` یا `match` در ۷.۴ می‌شکند |
| `harness` | لود همهٔ فایل‌ها به ترتیب وردپرس | fatalهای سطح declaration که lint نمی‌بیند |
| `checks` | `ci-checks.php` | نسخه‌ها هم‌خوان، دامنهٔ ترجمه درست، بدون `var_dump`، هر ویجت در کاتالوگ |
| `generated` | `build-assets.py` + `build-pot.py` و `git status` خالی | `.min` و POT کهنه نباید merge شوند |

## افزودن یک ویجت

1. فایل را در `widgets/class-hsw-widget-<name>.php` بسازید و از پایهٔ مناسب ارث ببرید (`HSW_Widget_Base`، `HSW_Product_Base`، `HSW_Cart_Base`، …).
2. `get_name()` باید `hsw-<name>` برگرداند — این slug بخشی از قرارداد عمومی است.
3. `get_style_depends()` / `get_script_depends()` را دقیق پر کنید؛ enqueue سراسری ممنوع.
4. کنترل‌ها را در `register_controls()` بسازید. برای رنگ از `a_color()` استفاده کنید، نه رنگ هاردکد.
5. در `render()` از `effective_settings()` و `open_root()` استفاده کنید.
6. ثبت در سه جا: `HSW_Widget_Manager::catalog()`، `HSW_Plugin::register_widgets()`، و در صورت نیاز یک مهاجرت یک‌باره برای نصب‌های موجود.
7. `python tools/gen-widget-docs.py` را اجرا کنید تا `docs/WIDGETS.md` به‌روز شود.
8. `php tools/ci-checks.php` باید پاس شود (چک «هر فایل ویجت در کاتالوگ هست»).

```php
class HSW_Widget_My_Thing extends HSW_Product_Base {
	public function get_name() { return 'hsw-my-thing'; }
	public function get_title() { return __( '…', 'hamishop-elementor-widgets' ); }
	public function get_style_depends()  { return array( 'hsw-product' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$this->start_controls_section( 'sec_x', array( 'label' => __( '…', 'hamishop-elementor-widgets' ) ) );
		$this->a_color( 'x_bg', __( 'پس‌زمینه', 'hamishop-elementor-widgets' ), '{{WRAPPER}} .hsw-x', 'background-color' );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->effective_settings();
		echo $this->open_root( 'thing', $s );
		echo '<div class="hsw-x">' . esc_html( $s['title'] ?? '' ) . '</div>';
		echo '</div>';
	}
}
```

## افزودن یک آیکون

1. نام آیکون را در `tools/_icons.txt` اضافه کنید.
2. مسیر SVG را در `includes/class-hsw-icon-paths.php` بگذارید.
3. `python tools/build-sprite.py` را اجرا کنید.
4. اگر آیکون در CSS استفاده می‌شود، `python tools/dedupe-icon-css.py` را هم اجرا کنید.

آیکون ناشناخته در زمان اجرا با یک نقطهٔ جایگزین رندر می‌شود و در `error_log` ثبت می‌شود — صفحه نمی‌شکند.

## قراردادهای نام‌گذاری

| مورد | قرارداد | مثال |
|---|---|---|
| کلاس | `HSW_` + CamelCase | `HSW_Palette` |
| فایل کلاس | `class-hsw-<kebab>.php` | `class-hsw-palette.php` |
| trait | `trait-hsw-<kebab>.php` | `trait-hsw-style-kit.php` |
| ویجت | `hsw-<kebab>` | `hsw-product-gallery` |
| کنترل المنتور | `<prefix>_<snake>` | `cta_bg`, `mgs_head_bg` |
| آپشن | `hsw_<snake>` | `hsw_palette` |
| توکن CSS | `--hsw-<kebab>` | `--hsw-primary` |
| کلاس CSS | `hsw-<family>__<element>` | `hsw-hd__searchbtn` |
| فیلتر/اکشن | `hsw_<snake>` | `hsw_font_choices` |

**نام‌هایی که تغییرشان صفحه‌های کاربران را می‌شکند:** slug ویجت، شناسهٔ کنترل‌ها، نام آپشن‌ها، کلاس‌های CSS، مسیرهای REST. این‌ها قرارداد عمومی‌اند.

## فرآیند ریلیز

```
[ ] نسخه در hamishop-elementor-widgets.php (هدر + HSW_VERSION)
[ ] Stable tag در readme.txt
[ ] ورودی جدید در CHANGELOG.md (و ۵ نسخهٔ آخر readme.txt)
[ ] RELEASE-NOTES-<ver>.md (اگر تغییر بزرگ است)
[ ] python tools/build-assets.py
[ ] python tools/build-pot.py
[ ] php tools/ci-checks.php      → 16 checks, all passed
[ ] php tests/harness.php        → ALL CLEAN
[ ] پروب‌های مرتبط روی سایت تست
[ ] git commit + tag v<ver> + push
[ ] python tools/build-release.py → ZIP
[ ] GitHub Release با متن CHANGELOG و ZIP به‌عنوان asset
```

`tools/build-release.py` با **لیست سفید** کار می‌کند: فقط `hamishop-elementor-widgets.php`، `uninstall.php`، `readme.txt`، `README.md`، `LICENSE`، یادداشت همان نسخه و پوشه‌های `assets/`، `includes/`، `widgets/`، `templates/`، `languages/` وارد ZIP می‌شوند. `docs/`، `tests/` و `tools/` هرگز داخل بستهٔ نصبی نمی‌روند.

## گیت

- شاخهٔ اصلی `main` است؛ کار روی شاخهٔ موضوعی و merge با PR.
- پیام کامیت به سبک Conventional Commits: `fix(header): …`، `feat(palette): …`، `docs: …`، `build: …`.
- تگ نسخه: `v<major>.<minor>.<patch>`.
