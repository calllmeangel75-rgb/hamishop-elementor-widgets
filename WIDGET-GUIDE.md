# WIDGET GUIDE — HamiShop Product Page Widgets (فارسی + قراردادها)

این سند قرارداد دقیق ساخت ویجت‌های «صفحه محصول» است. منبع حقیقت:
1. HTML اصلی کاربر: `/home/z/my-project/upload/Pasted Content_1792239853532.txt` (دسکتاپ: خطوط 133–540، موبایل: 544–1022)
2. CSS پیاده‌سازی‌شده: `assets/css/hsw-product.css` — **کلاس‌های markup باید دقیقاً همان‌ها باشند.**
3. JS تعاملی: `assets/js/hsw-product.js` — attributeهای data را عیناً رعایت کن.
4. کلاس پایه: `includes/class-hsw-product-base.php` (extends HSW_Widget_Base)
5. ویجت نمونه کامل: `widgets/class-hsw-widget-product-breadcrumb.php`

## قوانین کلی (غیرقابل تغییر)
- PHP 7.4 سازگار: بدون `match`، بدون `fn()`، بدون `?->`، بدون `readonly`. فقط syntax سازگار.
- همه فایل‌ها با guard `defined( 'ABSPATH' ) || exit;` و docblock شروع شوند؛ فرمت‌بندی عین ویجت نمونه (فاصله‌گذاری ۸تایی، docblock هر متد).
- escapes: `esc_html`, `esc_attr`, `esc_url`, `esc_attr_e/esc_html_e` با textdomain `hamishop-elementor-widgets`.
- آیکون‌ها همیشه: `<span class="hsw-ic [کلاس‌ها]">icon_name</span>` (برای filled: کلاس `hsw-ic--fill`). تولید با `HSW_Helpers::icon( $name, $class, $fill )` و echo با `// phpcs:ignore`.
- رندر: اول `list( $product, $demo ) = $this->product_or_demo();` و `$s = $this->get_settings_for_display();`
- ریشه همه ویجت‌ها: `class="<?php echo esc_attr( $this->root_class( 'slug' ) ); ?>"` که `slug` بدون پیشوند hsw- است (مثلاً `'pgal'`).
- سوئیچ‌ها: `'yes' === ( $s['show_x'] ?? 'yes' )`
- URL کنترل: `$s['x_url']['url'] ?? ''`
- در حالت demo، داده‌ها از `$this->demo()` استفاده شود؛ هیچ‌وقت خارج از ادیتور demo رندر نشود.
- اعداد فارسی: `HSW_Helpers::fa_num()` / قیمت: `HSW_Helpers::price_num()`
- وقتی داده محصول خالی است و demo نیستیم، رندر `return;` (چیزی چاپ نشود) مگر سند بگوید خلافش.
- **هیچ فایل دیگری جز فایل ویجت خودت را ویرایش نکن.**

## API مشترک کلاس پایه
- `product_or_demo()` → `[?WC_Product, bool $demo]`
- `display_prices($product)` → `['sale'=>float,'regular'=>float,'discount'=>int]`
- `variations_payload($product)` → آرایه JSON-able (attrs با label نمایشی)
- `attribute_terms($product, $taxonomy)` → `[['name','slug'],...]`
- `specs_rows($product)` → `[['k','v'],...]`
- `reviews_list($product, $limit)` → `[['name','initials','rating','text','verified'],...]`
- `gallery_ids($product)` → `[attachment_id,...]`
- `demo()` → آرایه Samsung mock (name, name_en, sku, rating, reviews, recommend, sale, regular, save, discount, colors[[name,hex]], storages[], features_d[], features_m[[icon,label,value]], specs[[k,v]], reviews_l[[name,initials,rating,text,verified]], gallery[])
- کنترل‌ها: `ctrl_visibility()`, `ctrl_box($id,$selector,$opts)`, `ctrl_text($id,$selector,$opts)` (opts: hover, separator), `ctrl_icon($id,$selector)`, `product_icon_options()`
- `root_class($slug)` → `hsw-p hsw-{slug} hsw-vis-{mode}`

## data-attributeهای قراردادی JS (عیناً)
- خرید/نوار: ریشه `data-hsw-product="{ID}"` (در demo: `data-hsw-product="0"`).
- ارزش تعداد: `data-hsw-qval` روی span تعداد؛ دکمه‌ها `data-hsw-qty="1"` و `data-hsw-qty="-1"`.
- افزودن به سبد: `data-hsw-add` روی دکمه؛ خرید فوری: `data-hsw-buy`.
- فیلدهای قیمت پویا: `data-hsw-price` (قیمت اصلی)، `data-hsw-old` (قیمت قبلی)، `data-hsw-save` (چیپ «X تومان تخفیف» دسکتاپ)، `data-hsw-off` (قرص «٪X-» موبایل).
- توست موبایل: `data-hsw-toast` روی `.hsw-psticky__toast` + لینک «مشاهده سبد» به cart url.
- وضعیت ناموجود: JS کلاس `is-oos` را روی ریشه می‌گذارد — دکمه‌های cart قواعد CSS دارند.
- گالری: ریشه `data-hsw-product` ندارد؛ به‌جایش هر thumb/dot `data-hsw-src="{url}"` دارد و تصویر اصلی کلاس `.hsw-pgal__img`.
- زوم: دکمه `data-hsw-zoom`.
- علاقه‌مندی (ویژوال): `data-hsw-wish` روی دکمه.
- انتخاب رنگ/حافظه: ریشه `.hsw-pvar` → `data-hsw-variants="{ID}"` + `data-hsw-variations='{JSON}'` (فقط وقتی محصول variable است) + `data-hsw-attrs='{"color":"pa_color","storage":"pa_memory"}'` (اسلاگ taxانتری از کنترل‌ها). دکمه‌ها: `.hsw-pvar__swatch/.hsw-pvar__mswatch` با `data-hsw-color="{label}"` و `.hsw-pvar__chip/.hsw-pvar__mchip` با `data-hsw-storage="{label}"`. برچسب انتخاب‌شده: `data-hsw-selname="color"` و `data-hsw-selname="storage"`.

---

# 1) Widget: Product Gallery — `widgets/class-hsw-widget-product-gallery.php`
کلاس `HSW_Widget_Product_Gallery`، name `hsw-product-gallery`، icon `eicon-product-images`، keywords: گالری، تصویر محصول، hamishop.
دسکتاپ (hsw-d): کارت با badge تخفیف + دکمه زوم + تصویر اصلی + ردیف بندانگشت‌ها. موبایل (hsw-m): کارت با دو badge (تخفیف + اصالت) + تصویر اصلی + دات‌ها.

### Markup (دقیقاً)
```html
<section class="{root_class('pgal')}">
  <div class="hsw-pgal__card">
    <!-- desktop badge + zoom -->
    <span class="hsw-pgal__badge hsw-d">٪۶ تخفیف</span>
    <button type="button" class="hsw-pgal__zoom hsw-d" data-hsw-zoom aria-label="بزرگنمایی تصویر"><span class="hsw-ic">zoom_in</span></button>
    <div class="hsw-pgal__frame">
      <img class="hsw-pgal__img" src="{اولین تصویر - سایز woocommerce_single}" alt="{نام محصول}" fetchpriority="high" decoding="async">
    </div>
    <div class="hsw-pgal__thumbs hsw-d">
      <button type="button" class="hsw-pgal__thumb is-active" data-hsw-src="{url}" aria-label="تصویر ۱"><img src="{thumbnail}" alt="" loading="lazy" decoding="async"></button>
      <!-- بقیه thumbs بدون is-active -->
    </div>
    <!-- mobile badges -->
    <div class="hsw-pgal__mbadges hsw-m">
      <span class="hsw-pgal__badge">٪۱۲ تخفیف</span>
      <span class="hsw-pgal__auth"><span class="hsw-ic hsw-ic--fill">verified</span>اصالت کالا</span>
    </div>
    <div class="hsw-pgal__dots hsw-m">
      <button type="button" class="hsw-pgal__dot is-active" data-hsw-src="{url}" aria-label="تصویر ۱"></button>
      <!-- بقیه dots -->
    </div>
  </div>
</section>
```
- تصاویر: از `gallery_ids($product)`، سایز اصلی `woocommerce_single`، thumb همان URL اصلی (بندانگشت فقط کوچک نمایش داده می‌شود؛ از همان URL استفاده کن تا JS ساده بماند). تصویر اصلی `loading="eager"`.
- badge دسکتاپ/موبایل: متن `٪{discount} تخفیف` با fa_num؛ اگر discount==0 نشان نده. در demo: دسکتاپ «٪۶ تخفیف»، موبایل «٪۱۲ تخفیف» (مطابق طرح).
- بج اصالت موبایل فقط اگر `show_auth` روشن.
- اگر محصول تصویر ندارد: `assets/img/placeholder.svg` (با `HSW_URL`).
- حداکثر دات/بندانگشت: همه تصاویر گالری (معمولاً < 10).

### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ `show_zoom` (SWITCHER yes، دسکتاپ)؛ `show_auth` (SWITCHER yes، بج اصالت موبایل)؛ `auth_text` (TEXT default «اصالت کالا»)؛ `thumbs_count` (NUMBER default 0 = همه)؛ `spacing_bottom` (SLIDER responsive، فاصله پایین کارت؛ selector `{{WRAPPER}} .hsw-pgal__card` → `margin-bottom`؛ default دسکتاپ 0).
- STYLE: `ctrl_box('gal','{{WRAPPER}} .hsw-pgal__card')`؛ رنگ بج: `{{WRAPPER}} .hsw-pgal__badge` → `--hsw-badge-bg` و `--hsw-badge-c`؛ رنگ قاب داخلی: `{{WRAPPER}} .hsw-pgal__frame` → `background-color`؛ رنگ بج اصالت: `{{WRAPPER}} .hsw-pgal__auth` → `--hsw-auth-bg`,`--hsw-auth-c`؛ رنگ دات فعال: `{{WRAPPER}} .hsw-pgal__dot.is-active` → `--hsw-dot-bg`؛ اندازه بندانگشت: SLIDER روی `{{WRAPPER}} .hsw-pgal__thumb` → width+height (64 پیش‌فرض)؛ `ctrl_icon('gal_zoom','{{WRAPPER}} .hsw-pgal__zoom .hsw-ic')`.

---

# 2) Widget: Product Trust — `widgets/class-hsw-widget-product-trust.php`
کلاس `HSW_Widget_Product_Trust`، name `hsw-product-trust`، icon `eicon-check`. پنل اعتماد دسکتاپ (سه‌ستونه). در طرح موبایل وجود ندارد → ریشه `hsw-d` را همیشه بده (جز وقتی vis=mobile).

### Markup
```html
<div class="{root_class('ptrust', 'hsw-d')}">
  <div class="hsw-ptrust__item">
    <span class="hsw-ic hsw-ptrust__ic">verified</span>
    <span class="hsw-ptrust__t">اصالت ۱۰۰٪ کالا</span>
    <span class="hsw-ptrust__s">ضمانت رسمی</span>
  </div>
  <!-- item2: local_shipping / ارسال فوری / تحویل اکسپرس -->
  <!-- item3: assignment_return / ۷ روز بازگشت / بی قید و شرط -->
</div>
```
### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ Repeater `trust_items` با فیلدهای `icon` (SELECT از product_icon_options، default verified)، `title` (TEXT)، `sub` (TEXT)؛ آیتم‌های پیش‌فرض همان سه آیتم طرح (رندر پیش‌فرض PHP وقتی repeater خالی: همان سه آیتم hard-code با متن طرح).
- STYLE: `ctrl_box('tr','{{WRAPPER}} .hsw-ptrust')`؛ `ctrl_icon('tr_ic','{{WRAPPER}} .hsw-ptrust__ic')`؛ `ctrl_text('tr_t','{{WRAPPER}} .hsw-ptrust__t')`؛ `ctrl_text('tr_s','{{WRAPPER}} .hsw-ptrust__s')`.

---

# 3) Widget: Product Title — `widgets/class-hsw-widget-product-title.php`
کلاس `HSW_Widget_Product_Title`، name `hsw-product-title`، icon `eicon-product-title`. دسکتاپ: متای EN+SKU، H1، نوار امتیاز. موبایل: قرص موجودی + امتیاز، H2، زیرنویس EN.

### Markup
```html
<div class="{root_class('ptitle')}">
  <div class="hsw-d">
    <div class="hsw-ptitle__meta">
      <span class="hsw-ptitle__en" dir="ltr">{name_en}</span>
      <span class="hsw-ptitle__sku">کد کالا: {sku}</span>
    </div>
    <h1 class="hsw-ptitle__h1">{name}</h1>
    <div class="hsw-ptitle__ratebar">
      <span class="hsw-ptitle__score">
        <span class="hsw-ic hsw-ic--fill hsw-ptitle__star">star</span>
        <b>{rating}</b><i>(از ۵)</i>
      </span>
      <span class="hsw-ptitle__dot" aria-hidden="true"></span>
      <a class="hsw-ptitle__revlink" href="{#reviews یا ctl_rev_url}">{reviews} دیدگاه ثبت شده کاربران</a>
      <span class="hsw-ptitle__dot" aria-hidden="true"></span>
      <span class="hsw-ptitle__rec">{recommend}٪ خریداران پیشنهاد کرده‌اند</span>
    </div>
  </div>
  <div class="hsw-m">
    <div class="hsw-ptitle__mrow">
      <span class="hsw-ptitle__stock"><i class="hsw-ptitle__pulse" aria-hidden="true"></i>موجود در انبار</span>
      <span class="hsw-ptitle__mscore">
        <span class="hsw-ptitle__mcnt">({reviews} نظر)</span>
        <b>{rating}</b>
        <span class="hsw-ic hsw-ic--fill">star</span>
      </span>
    </div>
    <h2 class="hsw-ptitle__h2">{name}</h2>
    <p class="hsw-ptitle__men" dir="ltr">{name_en}</p>
  </div>
</div>
```
- دیتا: name = `$product->get_name()`؛ name_en = متن کنترل `title_en` (fallback: خالی)؛ sku = `$product->get_sku()`؛ rating = `number_format($product->get_average_rating(),1)` با fa_num؛ reviews = `$product->get_review_count()` با fa_num؛ recommend = کنترل TEXT default «۸۹» (فقط دسکتاپ).
- موجود در انبار: اگر `$product->is_in_stock()` متن «موجود در انبار» وگرنه «ناموجود» (کلاس is-oos روی stock span → رنگ قرمز: inline style یا کلاس؛ فقط متن کافی است).
- tag عنوان دسکتاپ: SELECT `title_tag` h1/h2 (default h1)؛ tag موبایل ثابت h2.
- demo: از `$this->demo()`.

### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ `title_en` (TEXT)؛ `recommend` (TEXT default ۸۹)؛ `show_stock` (SWITCHER yes)؛ `title_tag` (SELECT h1/h2)؛ `rev_url` (URL کنترل، خالی = `#reviews`)؛ `spacing_bottom` (SLIDER → `{{WRAPPER}} .hsw-ptitle` margin-bottom؛ default 0).
- STYLE: `ctrl_text('tt','{{WRAPPER}} .hsw-ptitle__h1, {{WRAPPER}} .hsw-ptitle__h2')` (تایپوگرافی مشترک عنوان)؛ رنگ ستاره: `{{WRAPPER}} .hsw-ptitle__star` → `--hsw-rate-star`؛ رنگ لینک دیدگاه: `{{WRAPPER}} .hsw-ptitle__revlink` → `--hsw-rate-link`؛ `ctrl_text('tt_en','{{WRAPPER}} .hsw-ptitle__en')`؛ `ctrl_text('tt_sku','{{WRAPPER}} .hsw-ptitle__sku')`؛ رنگ قرص موجودی: `{{WRAPPER}} .hsw-ptitle__stock` → `--hsw-stock-bg` (+ color ساده).

---

# 4) Widget: Product Variants — `widgets/class-hsw-widget-product-variants.php`
کلاس `HSW_Widget_Product_Variants`، name `hsw-product-variants`، icon `eicon-basket-medium`. موتور انتخاب رنگ/حافظه + JSON variations. **قلب تعاملات** — data-attrها دقیق باشد.

### Markup
```html
<div class="{root_class('pvar')}" data-hsw-variants="{ID یا 0}" {اگر variable: data-hsw-variations='{wp_json_encode($payload)}' data-hsw-attrs='{wp_json_encode($attrsMap)}'}>
  <div class="hsw-d">
    <div class="hsw-pvar__group">
      <div class="hsw-pvar__head">
        <span class="hsw-pvar__label">رنگ: <strong class="hsw-pvar__selname" data-hsw-selname="color">{رنگ اول}</strong></span>
      </div>
      <div class="hsw-pvar__swatches">
        <button type="button" class="hsw-pvar__swatch is-active" data-hsw-color="{name}" style="--sw:{hex}" aria-label="{name}"><span class="hsw-pvar__swdot"></span></button>
        <!-- بقیه بدون is-active -->
      </div>
    </div>
    <div class="hsw-pvar__group">
      <div class="hsw-pvar__head"><span class="hsw-pvar__label">حافظه داخلی و رم:</span></div>
      <div class="hsw-pvar__chips">
        <button type="button" class="hsw-pvar__chip is-active" data-hsw-storage="{label}">{label}</button>
        <!-- بقیه -->
      </div>
    </div>
  </div>
  <div class="hsw-m">
    <div class="hsw-pvar__mcard">
      <div class="hsw-pvar__head">
        <span class="hsw-pvar__label">انتخاب رنگ:</span>
        <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="color">{رنگ اول}</span>
      </div>
      <div class="hsw-pvar__swatches">
        <button type="button" class="hsw-pvar__mswatch is-active" data-hsw-color="{name}" style="--sw:{hex}" aria-label="{name}"><span class="hsw-pvar__swdot"></span></button>
      </div>
    </div>
    <div class="hsw-pvar__mcard">
      <div class="hsw-pvar__head">
        <span class="hsw-pvar__label">حافظه داخلی:</span>
        <span class="hsw-pvar__label hsw-pvar__selname" data-hsw-selname="storage">{اولین}</span>
      </div>
      <div class="hsw-pvar__mchips">
        <button type="button" class="hsw-pvar__mchip is-active" data-hsw-storage="{label}">{label}</button>
      </div>
    </div>
  </div>
</div>
```
- رنگ‌ها: `attribute_terms($product, $s['attr_color'])`؛ hex از repeater `color_map` (فیلدها `name`,`hex`) — اگر term در map نبود hex پیش‌فرض `#cccccc`. اگر attribute رنگ موجود نیست: از demo() رنگ‌ها.
- حافظه: `attribute_terms($product, $s['attr_storage'])`؛ demo: storages.
- انتخاب اولین به‌صورت is-active (دسکتاپ و موبایل هم‌زمان).
- `label_color` و `label_storage` کنترل TEXT برای متن برچسب‌ها (default «رنگ:»/«حافظه داخلی و رم:»/«انتخاب رنگ:»/«حافظه داخلی:»).
- wp_json_encode با SINGLE QUOTES در attribute: `data-hsw-variations='<?php echo esc_attr( wp_json_encode( $payload ) ); ?>'`.

### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ `attr_color` (TEXT default `pa_color`)؛ `attr_storage` (TEXT default `pa_memory`)؛ `label_color_d`/`label_color_m`/`label_storage_d`/`label_storage_m` (TEXT با پیش‌فرض‌های بالا)؛ Repeater `color_map` (`name` TEXT، `hex` COLOR بدون alpha)؛ `demo_colors`/`demo_storages` لازم نیست (همیشه از demo()).
- STYLE: `ctrl_text('var_l','{{WRAPPER}} .hsw-pvar__label')`؛ رنگ نام انتخابی: `{{WRAPPER}} .hsw-pvar__selname` → `--hsw-var-sel`؛ رنگ حلقه فعال: `{{WRAPPER}} .hsw-pvar__swatch.is-active` → `--hsw-var-ring`؛ چیپ: `{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip` → `--hsw-chip-bg`,`--hsw-chip-c`,`--hsw-chip-bc` و حالت فعال `--hsw-chip-abc`,`--hsw-chip-ac`؛ `ctrl_text('var_chip','{{WRAPPER}} .hsw-pvar__chip, {{WRAPPER}} .hsw-pvar__mchip')`.

---

# 5) Widget: Product Features — `widgets/class-hsw-widget-product-features.php`
کلاس `HSW_Widget_Product_Features`، name `hsw-product-features`، icon `eicon-bullet-list`. دسکتاپ: لیست تیک‌دار داخل باکس. موبایل: بنتو گرید ۲×۲.

### Markup
```html
<div class="{root_class('pfeat')}">
  <div class="hsw-d">
    <div class="hsw-pfeat__box">
      <span class="hsw-pfeat__title">{عنوان دسکتاپ}</span>
      <ul class="hsw-pfeat__list">
        <li class="hsw-pfeat__item"><span class="hsw-ic hsw-pfeat__ic">check_circle</span><span>{متن}</span></li>
        <!-- ... -->
      </ul>
    </div>
  </div>
  <div class="hsw-m">
    <h3 class="hsw-pfeat__mtitle">{عنوان موبایل}</h3>
    <div class="hsw-pfeat__grid">
      <div class="hsw-pfeat__cell">
        <span class="hsw-pfeat__mic"><span class="hsw-ic">{icon}</span></span>
        <div>
          <div class="hsw-pfeat__mlabel">{label}</div>
          <div class="hsw-pfeat__mval">{value}</div>
        </div>
      </div>
      <!-- ... -->
    </div>
  </div>
</div>
```
- منبع دسکتاپ: SWITCHER `desk_auto` (default no). خاموش → Repeater `desk_items` (فیلد `text`؛ ۴ آیتم پیش‌فرض متن‌های demo features_d). روشن → از attribute مشخص‌شده؟ نه — ساده: روشن = ۴ مقدار اول `specs_rows($product)` به شکل «k: v».
- منبع موبایل: SWITCHER `mob_auto` (default no). خاموش → Repeater `mob_items` (فیلدها `icon` SELECT، `label`، `value`؛ ۴ آیتم پیش‌فرض demo features_m). روشن → ۴ اول specs (icon ثابت tune، label=k، value=v).
- عناوین: `desk_title` (default «ویژگی‌های شاخص کالا:») و `mob_title` (default «ویژگی‌های کلیدی»).
- demo: اگر demo → همان repeater پیش‌فرض‌ها کفایت می‌کند (پس وقتی demo، repeater خامش را رندر کن).

### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ `desk_title`,`mob_title` (TEXT)؛ `desk_auto`/`mob_auto` (SWITCHER)؛ `desk_items` (REPEATER text)؛ `mob_items` (REPEATER icon/label/value).
- STYLE: `ctrl_box('ft','{{WRAPPER}} .hsw-pfeat__box')`؛ `ctrl_text('ft_t','{{WRAPPER}} .hsw-pfeat__title')`؛ `ctrl_text('ft_i','{{WRAPPER}} .hsw-pfeat__list')`؛ `ctrl_icon('ft_ic','{{WRAPPER}} .hsw-pfeat__ic')`؛ موبایل: `ctrl_box('ftm','{{WRAPPER}} .hsw-pfeat__cell')`؛ رنگ قاب آیکون: `{{WRAPPER}} .hsw-pfeat__mic` → `--hsw-ft-mic-bg`,`--hsw-ft-mic`؛ `ctrl_text('ftm_l','{{WRAPPER}} .hsw-pfeat__mlabel')`؛ `ctrl_text('ftm_v','{{WRAPPER}} .hsw-pfeat__mval')`.

---

# 6) Widget: Product Purchase — `widgets/class-hsw-widget-product-purchase.php`
کلاس `HSW_Widget_Product_Purchase`، name `hsw-product-purchase`، icon `eicon-product-add-to-cart`. جعبه خرید دسکتاپ: قیمت/تعداد/دکمه‌ها/گارانتی‌ها. data-hsw-product الزامی.

### Markup
```html
<div class="{root_class('ppurch')}" data-hsw-product="{ID یا 0}">
  <div class="hsw-ppurch__top">
    <div>
      <div class="hsw-ppurch__oldrow">
        <span class="hsw-ppurch__old" data-hsw-old>{regular}</span>
        <span class="hsw-ppurch__save" data-hsw-save>{save} تومان تخفیف ویژه</span>
      </div>
      <div class="hsw-ppurch__row">
        <span class="hsw-ppurch__main" data-hsw-price>{sale}</span>
        <span class="hsw-ppurch__unit">تومان</span>
      </div>
    </div>
    <div class="hsw-ppurch__qty">
      <span class="hsw-ppurch__qtylabel">تعداد:</span>
      <div class="hsw-ppurch__stepper">
        <button type="button" class="hsw-ppurch__qbtn" data-hsw-qty="1" aria-label="افزایش"><span class="hsw-ic">add</span></button>
        <span class="hsw-ppurch__qval" data-hsw-qval>۱</span>
        <button type="button" class="hsw-ppurch__qbtn" data-hsw-qty="-1" aria-label="کاهش"><span class="hsw-ic">remove</span></button>
      </div>
    </div>
  </div>
  <div class="hsw-ppurch__ctas">
    <button type="button" class="hsw-ppurch__cart" data-hsw-add><span class="hsw-ic">shopping_cart</span><span>افزودن به سبد خرید</span></button>
    <button type="button" class="hsw-ppurch__buynow" data-hsw-buy><span class="hsw-ic">flash_on</span><span>خرید فوری</span></button>
  </div>
  <div class="hsw-ppurch__assure">
    <div class="hsw-ppurch__as hsw-ppurch__as--shield"><span class="hsw-ic">shield</span><span>{گارانتی}</span></div>
    <div class="hsw-ppurch__as hsw-ppurch__as--stock"><span class="hsw-ic">inventory_2</span><span>{موجودی}</span></div>
  </div>
</div>
```
- قیمت‌ها: `display_prices($product)`؛ sale/regular با price_num؛ save = regular-sale با price_num؛ اگر discount==0: old و save مخفی (style="display:none").
- demo: sale=۶۲,۹۹۰,۰۰۰ / regular=۶۷,۰۰۰,۰۰۰ / save=۴,۰۱۰,۰۰۰ (از demo()).
- موجودی متن سطر دوم: اگر in_stock «موجود در انبار تهران - آماده پردازش و ارسال» وگرنه «فعلاً ناموجود»؛ به ریشه کلاس `is-oos` اضافه شود وقتی ناموجود.
- متن دکمه‌ها: کنترل TEXT `cart_text` (default «افزودن به سبد خرید») و `buy_text` («خرید فوری»).
- خطوط assure: Repeater `assure_items` (فیلدها `icon` SELECT، `text`، `type` SELECT shield/stock)؛ پیش‌فرض دو آیتم طرح.

### کنترل‌ها
- CONTENT: `ctrl_visibility` (default desktop)؛ `cart_text`,`buy_text`؛ `qty_max` (NUMBER default 99؛ به data Attribute ریشه `data-hsw-max` — JS فعلاً 99 سخت دارد؛ فقط کنترل ثبت شود)؛ Repeater `assure_items`.
- STYLE: `ctrl_box('pb','{{WRAPPER}} .hsw-ppurch')`؛ قیمت اصلی: `{{WRAPPER}} .hsw-ppurch__main` → `--hsw-price-c` + `ctrl_text('pp_price','{{WRAPPER}} .hsw-ppurch__main')`؛ رنگ واحد: `{{WRAPPER}} .hsw-ppurch__unit` → `--hsw-unit-c`؛ `ctrl_text('pp_old','{{WRAPPER}} .hsw-ppurch__old')`؛ رنگ چیپ تخفیف: `{{WRAPPER}} .hsw-ppurch__save` → `--hsw-save-c` + background-color؛ دکمه سبد: `{{WRAPPER}} .hsw-ppurch__cart` → `--hsw-cart-bg`,`--hsw-cart-c` + `ctrl_text('pp_cart','{{WRAPPER}} .hsw-ppurch__cart')` + radius SLIDER `--hsw-pb-radius`? — radius دکمه: SLIDER مستقیم `border-radius` روی `.hsw-ppurch__cart`؛ دکمه فوری: `{{WRAPPER}} .hsw-ppurch__buynow` → `--hsw-bn-bc`,`--hsw-bn-c` + ctrl_text؛ `ctrl_text('pp_as','{{WRAPPER}} .hsw-ppurch__as')`؛ `ctrl_icon('pp_as_ic','{{WRAPPER}} .hsw-ppurch__as .hsw-ic')`.

---

# 7) Widget: Product Tabs — `widgets/class-hsw-widget-product-tabs.php`
کلاس `HSW_Widget_Product_Tabs`، name `hsw-product-tabs`، icon `eicon-product-tabs`. فقط دسکتاپ (ریشه hsw-d). سه تب: مشخصات / بررسی تخصصی / دیدگاه‌ها.

### Markup
```html
<section class="{root_class('ptabs', 'hsw-d')}">
  <div class="hsw-ptabs__nav">
    <button type="button" class="hsw-ptabs__btn is-active" data-hsw-tab="specs"><span class="hsw-ic">tune</span><span>مشخصات فنی</span></button>
    <button type="button" class="hsw-ptabs__btn" data-hsw-tab="review"><span class="hsw-ic">description</span><span>بررسی تخصصی کالا</span></button>
    <button type="button" class="hsw-ptabs__btn" data-hsw-tab="comments"><span class="hsw-ic">rate_review</span><span>دیدگاه‌ها و نظرات خریداران</span></button>
  </div>
  <div class="hsw-ptabs__panel is-active" data-hsw-panel="specs">
    <h2 class="hsw-ptabs__h2">مشخصات کلیدی و فنی کالا</h2>
    <div class="hsw-ptabs__table">
      <div class="hsw-ptabs__row">
        <span class="hsw-ptabs__th">{k}</span>
        <span class="hsw-ptabs__td">{v}</span>
      </div>
      <!-- ردیف‌های زوج کلاس alt می‌گیرند -->
    </div>
  </div>
  <div class="hsw-ptabs__panel" data-hsw-panel="review">
    <div class="hsw-ptabs__review">{محتوا p ها}</div>
  </div>
  <div class="hsw-ptabs__panel" data-hsw-panel="comments" id="reviews-section">
    <div class="hsw-ptabs__rvhead">
      <div>
        <h2 class="hsw-ptabs__h2">دیدگاه‌های تاییدشده خریداران</h2>
        <span class="hsw-ptabs__rvsub">بر اساس {n} بازخورد کاربران ثبت‌نام شده</span>
      </div>
      <button type="button" class="hsw-ptabs__newrv" data-hsw-add-review>ثبت دیدگاه جدید</button>
    </div>
    <div class="hsw-ptabs__rvlist">
      <div class="hsw-ptabs__rvitem">
        <div class="hsw-ptabs__rvtop">
          <div class="hsw-ptabs__rvwho">
            <span class="hsw-ptabs__rvav">{initials}</span>
            <span class="hsw-ptabs__rvname">{name}</span>
            {اگر verified: <span class="hsw-ptabs__rvtag">خریدار این کالا</span>}
          </div>
          <div class="hsw-ptabs__rvstars">
            <span class="hsw-ic hsw-ic--fill">star</span>×rating
            <span class="hsw-ptabs__rvval">{fa rating}.۰</span>
          </div>
        </div>
        <p class="hsw-ptabs__rvtext">{text}</p>
      </div>
    </div>
  </div>
</section>
```
- تب‌ها: هر کدام SWITCHER (`tab_specs`,`tab_review`,`tab_comments` — default yes) + TEXT label (`lbl_specs`/`lbl_review`/`lbl_comments` با پیش‌فرض‌های طرح).
- specs: `specs_rows($product)`؛ demo: demo()['specs']. اگر خالی: پاراگراف «مشخصاتی ثبت نشده است.»
- review: `apply_filters('the_content', $product->get_description())` — echo با phpcs:ignore؛ demo: دو <p> با متن‌های طرح. اگر خالی «توضیحی ثبت نشده است.»
- comments: `reviews_list($product, (int)$s['reviews_count'] ?? 5)`؛ count = `$product->get_review_count()`؛ ستاره‌ها: حلقه 1..5 با `hsw-ic--fill` تا rating. دکمه «ثبت دیدگاه جدید» → `<a>` به `#reviews-section` نمی‌خواهیم؛ طرح دکمه است — button با کلاس data-hsw-add-review و JS ندارد؛ به‌جای button از `<a href="#reviews-section">`؟ طرح: button. برای رفتار واقعی: لینک به فرم دیدگاه ووکامرس: `href="#review_form"` — از `<a class="hsw-ptabs__newrv" href="#reviews">` استفاده کن (استایل همان). 
- تب پیش‌فرض فعال: specs.

### کنترل‌ها
- CONTENT: `ctrl_visibility`؛ سه SWITCHER + سه TEXT label؛ `specs_title` (TEXT default «مشخصات کلیدی و فنی کالا»)؛ `reviews_count` (NUMBER default 5).
- STYLE: `ctrl_box('tabs','{{WRAPPER}} .hsw-ptabs')` (بدون padding! opts: padding=false، radius=true، border=true، shadow=true)؛ `ctrl_text('tab_b','{{WRAPPER}} .hsw-ptabs__btn')`؛ رنگ تب فعال: `{{WRAPPER}} .hsw-ptabs__btn.is-active` → `--hsw-tab-ac` و `--hsw-tab-abc` (border-bottom-color)؛ `ctrl_box('panel','{{WRAPPER}} .hsw-ptabs__panel')`؛ `ctrl_text('th','{{WRAPPER}} .hsw-ptabs__th')`؛ `ctrl_text('td','{{WRAPPER}} .hsw-ptabs__td')`؛ `ctrl_text('rv','{{WRAPPER}} .hsw-ptabs__rvtext')`؛ رنگ ستاره دیدگاه: `{{WRAPPER}} .hsw-ptabs__rvstars` → `--hsw-rvstar`.

---

# 8) Widget: Product Accordions — `widgets/class-hsw-widget-product-accordions.php`
کلاس `HSW_Widget_Product_Accordions`، name `hsw-product-accordions`، icon `eicon-accordion`. فقط موبایل (ریشه hsw-m). سه آکاردئون: مشخصات کامل / دیدگاه‌ها / گارانتی و ارسال. همه بسته شروع می‌شوند.

### Markup
```html
<section class="{root_class('pacc', 'hsw-m')}">
  <div class="hsw-pacc__item">
    <button type="button" class="hsw-pacc__head" data-hsw-acc>
      <span class="hsw-pacc__title"><span class="hsw-ic">tune</span>مشخصات فنی کامل</span>
      <span class="hsw-ic hsw-pacc__chev">expand_more</span>
    </button>
    <div class="hsw-pacc__body">
      <div class="hsw-pacc__row"><span class="hsw-pacc__k">{k}</span><span class="hsw-pacc__v">{v}</span></div>
    </div>
  </div>
  <div class="hsw-pacc__item">
    ... head: rate_review / دیدگاه‌ها و نظرات خریداران
    <div class="hsw-pacc__body">
      <div class="hsw-pacc__rvitem">
        <div class="hsw-pacc__rvtop">
          <span class="hsw-pacc__rvname">{name}</span>
          <span class="hsw-pacc__rvstars"><span class="hsw-ic hsw-ic--fill">star</span>×rating</span>
        </div>
        <p class="hsw-pacc__rvtext">{text}</p>
      </div>
    </div>
  </div>
  <div class="hsw-pacc__item">
    ... head: local_shipping / شرایط گارانتی و ارسال
    <div class="hsw-pacc__body">
      <div class="hsw-pacc__wline"><span class="hsw-ic">security</span><span>۱۸ ماه گارانتی شرکتی معتبر همراه با کد رجیستری فعال</span></div>
      <div class="hsw-pacc__wline"><span class="hsw-ic">bolt</span><span>ارسال فوری اکسپرس در تهران (تحویل حداکثر ۳ ساعته)</span></div>
      <div class="hsw-pacc__wline"><span class="hsw-ic">assignment_return</span><span>۷ روز مهلت تست و بازگشت بی‌قید و شرط کالا</span></div>
    </div>
  </div>
</section>
```
- specs از specs_rows؛ reviews از reviews_list (count کنترل)؛ گارانتی: Repeater `warranty_items` (icon SELECT + text؛ ۳ آیتم پیش‌فرض طرح).
- سوییچ هر آکاردئون: `acc_specs`/`acc_reviews`/`acc_warranty` (SWITCHER yes) + label TEXTها.

### کنترل‌ها
- CONTENT: `ctrl_visibility` (default mobile)؛ سوییچ‌ها و لیبل‌ها؛ `acc_specs_title` default «مشخصات فنی کامل»، `acc_reviews_title` default «دیدگاه‌ها و نظرات خریداران»، `acc_warranty_title` default «شرایط گارانتی و ارسال»؛ `reviews_count2` (NUMBER default 3)؛ Repeater `warranty_items`.
- STYLE: `ctrl_box('acc','{{WRAPPER}} .hsw-pacc__item')`؛ `ctrl_text('acc_t','{{WRAPPER}} .hsw-pacc__title')`؛ `ctrl_icon('acc_ic','{{WRAPPER}} .hsw-pacc__title .hsw-ic')`؛ `ctrl_text('acc_k','{{WRAPPER}} .hsw-pacc__k')`؛ `ctrl_text('acc_v','{{WRAPPER}} .hsw-pacc__v')`؛ `ctrl_text('acc_rv','{{WRAPPER}} .hsw-pacc__rvtext')`؛ رنگ ستاره: `{{WRAPPER}} .hsw-pacc__rvstars` → `--hsw-rvstar`؛ رنگ آیکون گارانتی: `{{WRAPPER}} .hsw-pacc__wline .hsw-ic` → `--hsw-wl-ic`.

---

# 9) Widget: Product Sticky Bar — `widgets/class-hsw-widget-product-sticky.php`
کلاس `HSW_Widget_Product_Sticky`، name `hsw-product-sticky`، icon `eicon-cart-medium`. نوار خرید چسبان موبایل (fixed bottom). data-hsw-product الزامی + توست.

### Markup
```html
<aside class="{root_class('psticky', 'hsw-m')}" data-hsw-product="{ID یا 0}">
  <div class="hsw-psticky__inner">
    <div class="hsw-psticky__main">
      <div class="hsw-psticky__price">
        <div class="hsw-psticky__oldrow">
          <span class="hsw-psticky__old" data-hsw-old>{regular}</span>
          <span class="hsw-psticky__off" data-hsw-off>٪۱۲-</span>
        </div>
        <div class="hsw-psticky__prow">
          <span class="hsw-psticky__pval" data-hsw-price>{sale}</span>
          <span class="hsw-psticky__unit">تومان</span>
        </div>
      </div>
      <div class="hsw-psticky__side">
        <div class="hsw-psticky__stepper">
          <button type="button" class="hsw-psticky__qbtn hsw-psticky__qbtn--plus" data-hsw-qty="1" aria-label="افزایش تعداد"><span class="hsw-ic">add</span></button>
          <span class="hsw-psticky__qval" data-hsw-qval>۱</span>
          <button type="button" class="hsw-psticky__qbtn hsw-psticky__qbtn--minus" data-hsw-qty="-1" aria-label="کاهش تعداد"><span class="hsw-ic">remove</span></button>
        </div>
        <button type="button" class="hsw-psticky__cart" data-hsw-add>
          <span class="hsw-ic">shopping_cart</span><span>{cart_text}</span>
        </button>
      </div>
    </div>
    <div class="hsw-psticky__toast" data-hsw-toast>
      <span class="hsw-psticky__toastmsg"><span class="hsw-ic">check_circle</span><span>محصول با موفقیت به سبد خرید اضافه شد</span></span>
      <a class="hsw-psticky__toastlink" href="{cart_url}">مشاهده سبد</a>
    </div>
  </div>
</aside>
```
- قیمت‌ها مثل purchase (display_prices / demo). دکمه فقط افزودن (بدون خرید فوری) — دقیقاً طرح.
- cart_url با `wc_get_cart_url()` (demo: '#').
- اگر OOS: کلاس is-oos روی ریشه.
- `cart_text` TEXT default «افزودن به سبد».

### کنترل‌ها
- CONTENT: `ctrl_visibility` (default mobile)؛ `cart_text`؛ `sticky_fixed` (SWITCHER yes → اگر خاموش، ریشه کلاس `hsw-psticky--static` و استایل inline `position:static` لازم نیست — فقط کلاس بده؛ CSS ندارد، پس وقتی خاموش style="position:static" بده).
- STYLE: رنگ دکمه: `{{WRAPPER}} .hsw-psticky__cart` → `--hsw-cart-bg`,`--hsw-cart-c` + `ctrl_text('st_cart','{{WRAPPER}} .hsw-psticky__cart')`؛ قیمت: `ctrl_text('st_price','{{WRAPPER}} .hsw-psticky__pval')` + رنگ `{{WRAPPER}} .hsw-psticky__pval` → `--hsw-price-c`؛ `ctrl_text('st_old','{{WRAPPER}} .hsw-psticky__old')`؛ رنگ قرص تخفیف: `{{WRAPPER}} .hsw-psticky__off` → background-color + color؛ پس‌زمینه نوار: `{{WRAPPER}} .hsw-psticky` → background-color + backdrop-filter بردار نیست (فقط رنگ).

---

## چک‌لیست نهایی هر ایجنت
1. worklog.md و این سند و exemplar و class-hsw-product-base.php و hsw-product.css را کامل بخوان.
2. فقط فایل(های) ویجت خودت را بساز؛ کلاس‌های markup را با hsw-product.css تطبیق بده (هیچ کلاس/استایل جدیدی به CSS اضافه نکن).
3. کنترل‌ها با helperهای پایه؛ متن‌های UI فارسی با `__()/esc_html__()` و textdomain.
4. PHP 7.4-safe؛ docblocks؛ escape کامل.
5. اعتبارسنجی: `node -e` با php-parser اگر نصب بود، وگرنه بازخوانی کامل فایل.
6. worklog.md را append کن (Task ID خودت).
