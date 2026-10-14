# vnetbot

ربات تلگرام فروش VPN روی پنل PasarGuard (فروش سرویس، تمدید، کیف پول، نمایندگی). PHP + MySQL، بدون Composer.

## نیازمندی‌ها

- PHP 8.0 یا بالاتر با افزونه‌های `curl`، `pdo_mysql`، `mbstring`
- دیتابیس MySQL / MariaDB
- دامنه با **HTTPS** (تلگرام فقط https قبول می‌کنه)
- توکن ربات از @BotFather و آیدی عددی خودت (از @userinfobot)
- پنل PasarGuard با یک ادمین **sudo**

## نصب روی هاست (cPanel)

1. توی cPanel بخش **MySQL Databases** یک دیتابیس و یوزر بساز و یوزر رو با «All Privileges» به دیتابیس وصل کن.
2. فایل zip رو توی **File Manager** داخل `public_html` (یا یک زیرپوشه مثل `public_html/bot`) آپلود و Extract کن.
3. توی مرورگر برو به `https://دامنه‌ی-تو/bot/installer.php` و فرم رو پر کن: توکن، آیدی ادمین، اطلاعات دیتابیس. نصب‌کننده جدول‌ها و Webhook رو خودش می‌سازه.
4. فایل `installer.php` رو پاک کن.
5. توی cPanel بخش **Cron Jobs** یک کرون **هر ۱ دقیقه** بساز:
   ```
   * * * * * php /home/USER/public_html/bot/cron.php
   ```
   اگه هاستت اجازه‌ی `php` توی کرون نمی‌ده:
   ```
   * * * * * wget -q -O /dev/null "https://دامنه‌ی-تو/bot/cron.php?key=WEBHOOK_SECRET"
   ```
   مقدار `WEBHOOK_SECRET` رو از فایل `config.php` بردار.

## نصب روی سرور (VPS اوبونتو)

```bash
apt update && apt install -y nginx php-fpm php-mysql php-curl php-mbstring mysql-server unzip certbot python3-certbot-nginx
mysql -e "CREATE DATABASE vnetbot CHARACTER SET utf8mb4; CREATE USER 'vnetbot'@'localhost' IDENTIFIED BY 'رمز-قوی'; GRANT ALL ON vnetbot.* TO 'vnetbot'@'localhost';"
mkdir -p /var/www/vnetbot && unzip vnetbot.zip -d /var/www && chown -R www-data:www-data /var/www/vnetbot
certbot --nginx -d دامنه‌ی-تو
```

داخل بلوک `server` در nginx (نسخه‌ی php-fpm رو با سرورت چک کن):

```nginx
root /var/www/vnetbot;
index index.php;
location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ ^/(config\.php|bot_errors\.log|includes/|handlers/) { deny all; }
```

بعد `https://دامنه‌ی-تو/installer.php` رو باز کن و مثل بخش بالا نصب کن. کرون:

```bash
crontab -e   # اضافه کن:
* * * * * php /var/www/vnetbot/cron.php
```

## نصب روی دیتابیس قبلی

توی فرم نصب همون دیتابیس قبلی رو وارد کن. هیچ جدولی پاک یا خالی نمی‌شه؛ جدول‌ها و ستون‌هایی که کم باشن اضافه می‌شن و متن‌ها، دکمه‌ها، کاربرها، سرویس‌ها و سفارش‌های قبلی می‌مونن. قبلش از دیتابیس بکاپ بگیر (phpMyAdmin ← Export).

اگه دیتابیس مال ربات دیگه‌ای (مثلاً نسخه‌ی پایتونی) با ساختار جدول‌های متفاوته، بهتره یه دیتابیس تازه بسازی.

## بروزرسانی

فایل‌های جدید رو روی فایل‌های قبلی آپلود کن (**`config.php` رو دست نزن**). ساختار دیتابیس موقع اولین پیام به ربات خودکار بروز می‌شه.

## بعد از نصب

1. توی تلگرام به ربات `/start` و با حساب ادمین `/admin` بزن.
2. **پنل‌ها ← افزودن**: آدرس پنل (مثل `https://panel.example.com`) و یوزر/پسورد یک ادمین sudo.
3. **پلن‌ها** رو بساز. برای سرویس تست: توی پلن «تنظیم سرویس تست» رو بزن (تعداد، و هر روز/هفته/ماه/یک‌بار). برای پلنی که فقط تسته گزینه‌ی «فقط تست» رو روشن کن.
4. **تنظیمات پرداخت**: کارت، کیف پول تتر و تون.
5. **پلن‌های نمایندگی**: برای «نقش» شماره‌ی (ID) نقش رو از پنل بنویس (یا `-` تا اولین نقش استفاده بشه).
6. چیدمان منو: **«🧩 چیدمان دکمه‌های منو (مینی‌اپ)»** توی `/admin`.

## عیب‌یابی

- ادمین با دستور `/log` آخرین خطاها رو توی تلگرام می‌بینه. خطاهای مهم (تحویل سفارش، ساخت نمایندگی) خودکار برای ادمین‌ها پیام می‌شن.
- فایل `bot_errors.log` کنار `webhook.php` هم همین خطاها رو نگه می‌داره.
- ربات جواب نمی‌ده: آدرس `https://api.telegram.org/bot<TOKEN>/getWebhookInfo` رو باز کن و `last_error_message` رو بخون.
- دکمه‌ی مینی‌اپ کار نمی‌کنه: سایت باید با https باز بشه و آدرس ذخیره‌شده باید درست باشه (`site.base_url` در جدول `settings`؛ پیش‌فرض خودش از آدرس Webhook پر می‌شه).
