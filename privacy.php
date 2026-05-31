<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
$user = current_user();
$page = 'privacy';
$pageTitle = 'حریم خصوصی';
$seoTitle = 'حریم خصوصی دانش‌یار';
$seoDescription = 'سیاست حفظ حریم خصوصی دانش‌یار برای کاربران وب‌اپ و اپلیکیشن اندروید.';
include __DIR__ . '/includes/header.php';
?>
<div style="max-width:820px;margin:0 auto;padding:10px 0 40px">
  <div class="glass" style="padding:24px">
    <h1 style="font-size:28px;margin-bottom:10px;display:flex;align-items:center;gap:10px"><?= icon('shield') ?> حریم خصوصی دانش‌یار</h1>
    <p style="color:var(--text-dim);line-height:1.9;margin-bottom:18px">این صفحه برای کاربران وب‌سایت، وب‌اپ iPhone و اپلیکیشن اندروید دانش‌یار نوشته شده و توضیح می‌دهد چه داده‌هایی نگهداری می‌شود و چگونه استفاده می‌شود.</p>

    <div style="display:grid;gap:14px;line-height:1.95;color:var(--text)">
      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۱) اطلاعاتی که دریافت می‌کنیم</h2>
        <p>برای ساخت حساب، اطلاعاتی مثل نام، نام خانوادگی، شماره موبایل، پایه تحصیلی، رشته و نام مدرسه (در صورت وارد کردن) ذخیره می‌شود. همچنین پیام‌ها، گفت‌وگوها، کتاب انتخابی، فایل‌های ارسالی مثل تصویر یا PDF و رسیدهای پرداخت نیز برای ارائه سرویس ذخیره می‌شوند.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۲) هدف استفاده از اطلاعات</h2>
        <p>اطلاعات فقط برای ورود به حساب، ارائه پاسخ آموزشی، مدیریت چت‌ها، بررسی پرداخت‌ها، پشتیبانی و بهبود کیفیت سرویس استفاده می‌شود.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۳) مجوزهای اپلیکیشن اندروید</h2>
        <p>اپلیکیشن اندروید فقط به اینترنت و وضعیت اتصال شبکه نیاز دارد. برای انتخاب فایل یا تصویر از انتخاب‌گر استاندارد خود اندروید استفاده می‌شود و دسترسی اضافه‌ای به حافظه دستگاه درخواست نمی‌شود.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۴) اشتراک‌گذاری با شخص ثالث</h2>
        <p>اطلاعات حساب شما فروخته نمی‌شود. برای پاسخ‌گویی هوش مصنوعی، متن سوال و فایل ارسالی ممکن است به سرویس هوش مصنوعی متصل‌شده به دانش‌یار ارسال شود. این ارسال فقط برای تولید پاسخ انجام می‌شود.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۵) نگهداری و امنیت</h2>
        <p>ما تلاش می‌کنیم اطلاعات را روی سرور با دسترسی محدود نگهداری کنیم. با این حال هیچ سرویس آنلاین ۱۰۰٪ بدون ریسک نیست؛ بنابراین لطفاً از ارسال اطلاعات بسیار حساس و غیرضروری خودداری کنید.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۶) درخواست حذف اطلاعات</h2>
        <p>اگر بخواهید حساب یا داده‌های شما حذف شود، از طریق صفحه <a href="<?= BASE_URL ?>/contact.php" style="color:var(--orange)">ارتباط با ما</a> پیام بفرستید تا درخواست بررسی شود.</p>
      </section>

      <section>
        <h2 style="font-size:18px;color:var(--orange);margin-bottom:6px">۷) تماس</h2>
        <p>برای هر سوال درباره حریم خصوصی، از صفحه <a href="<?= BASE_URL ?>/contact.php" style="color:var(--orange)">ارتباط با ما</a> استفاده کنید.</p>
      </section>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
