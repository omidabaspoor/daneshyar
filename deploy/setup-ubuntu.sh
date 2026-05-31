#!/usr/bin/env bash
# ============================================================
#  دانش‌یار - اسکریپت نصب خودکار روی Ubuntu (۲۰.۰۴ / ۲۲.۰۴ / ۲۴.۰۴)
#  برای سرور مجازی ۱ کور / ۱ گیگ رم
#
#  طرز استفاده (با کاربر root یا sudo):
#      sudo bash deploy/setup-ubuntu.sh
#
#  این اسکریپت:
#   1. Swap می‌سازد (حیاتی برای ۱ گیگ رم)
#   2. Nginx + PHP-FPM + MariaDB نصب می‌کند
#   3. کانفیگ‌های بهینه‌شده را کپی می‌کند
#   4. دیتابیس و کاربر می‌سازد
#   5. پروژه را در /var/www/daneshyar مستقر می‌کند
# ============================================================
set -euo pipefail

# --------- رنگ‌ها ---------
G='\033[0;32m'; Y='\033[1;33m'; R='\033[0;31m'; N='\033[0m'
say()  { echo -e "${G}==>${N} $1"; }
warn() { echo -e "${Y}!!${N} $1"; }
err()  { echo -e "${R}xx${N} $1"; }

if [[ $EUID -ne 0 ]]; then err "این اسکریپت را با sudo اجرا کن."; exit 1; fi

# مسیر سورس پروژه = پوشه‌ی والد deploy
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="/var/www/daneshyar"
PHP_VER="${PHP_VER:-8.3}"

# --------- مقادیر دیتابیس ---------
DB_NAME="${DB_NAME:-daneshyar}"
DB_USER="${DB_USER:-daneshyar}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 12)}"

# =====================================================
say "گام ۱/۸ : ساخت Swap (۲ گیگ) — حیاتی برای رم ۱ گیگ"
# =====================================================
if ! swapon --show | grep -q '/swapfile'; then
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    # تنظیم swappiness پایین: فقط وقتی واقعاً لازم شد از swap استفاده کن
    sysctl -w vm.swappiness=10
    grep -q 'vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=10' >> /etc/sysctl.conf
    say "Swap ساخته شد."
else
    warn "Swap از قبل موجود است؛ رد شد."
fi

# =====================================================
say "گام ۲/۸ : به‌روزرسانی و نصب پکیج‌ها"
# =====================================================
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq software-properties-common curl unzip git openssl

# مخزن PHP (ondrej) برای نسخه‌ی به‌روز
if ! apt-cache policy | grep -q ondrej; then
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt-get update -qq
fi

apt-get install -y -qq \
    nginx \
    mariadb-server \
    php${PHP_VER}-fpm php${PHP_VER}-mysql php${PHP_VER}-curl \
    php${PHP_VER}-mbstring php${PHP_VER}-gd php${PHP_VER}-xml \
    php${PHP_VER}-opcache php${PHP_VER}-imagick 2>/dev/null || \
apt-get install -y -qq \
    nginx mariadb-server \
    php${PHP_VER}-fpm php${PHP_VER}-mysql php${PHP_VER}-curl \
    php${PHP_VER}-mbstring php${PHP_VER}-gd php${PHP_VER}-xml php${PHP_VER}-opcache

say "پکیج‌ها نصب شدند (PHP ${PHP_VER})."



# =====================================================
say "گام ۳/۸ : استقرار فایل‌های پروژه در ${APP_DIR}"
# =====================================================
mkdir -p "$APP_DIR"
# کپی همه‌چیز جز پوشه‌های توسعه
rsync -a --delete \
    --exclude '.git' \
    --exclude 'android' \
    --exclude 'deploy' \
    --exclude '*.py' \
    --exclude 'patch_*.php' \
    --exclude 'fix_*.php' \
    --exclude 'debug_*.html' \
    --exclude 'preview*.html' \
    "$SRC_DIR/" "$APP_DIR/" 2>/dev/null || cp -r "$SRC_DIR/." "$APP_DIR/"

# پوشه‌های قابل‌نوشتن
mkdir -p "$APP_DIR/uploads/receipts" "$APP_DIR/books"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/uploads" "$APP_DIR/books"

# =====================================================
say "گام ۴/۸ : ساخت دیتابیس و کاربر"
# =====================================================
systemctl enable --now mariadb
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# اجرای اسکیمای اولیه اگر موجود است
if [[ -f "$APP_DIR/sql/install.sql" ]]; then
    mysql "${DB_NAME}" < "$APP_DIR/sql/install.sql" 2>/dev/null || \
    warn "install.sql خطا داد (شاید قبلاً اجرا شده). در صورت نیاز دستی اجرا کن."
fi

# =====================================================
say "گام ۵/۸ : ساخت فایل .env"
# =====================================================
if [[ ! -f "$APP_DIR/.env" ]]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    sed -i "s|^DB_NAME=.*|DB_NAME=${DB_NAME}|"  "$APP_DIR/.env"
    sed -i "s|^DB_USER=.*|DB_USER=${DB_USER}|"  "$APP_DIR/.env"
    sed -i "s|^DB_PASS=.*|DB_PASS=${DB_PASS}|"  "$APP_DIR/.env"
    sed -i "s|^DEV_MODE=.*|DEV_MODE=false|"     "$APP_DIR/.env"
    chown www-data:www-data "$APP_DIR/.env"
    chmod 640 "$APP_DIR/.env"
    warn "فایل .env ساخته شد. ⚠ حتماً AI_API_KEY و ADMIN_PASS را داخلش ویرایش کن:"
    warn "    nano ${APP_DIR}/.env"
else
    warn ".env از قبل موجود است؛ دست نخورد."
fi

# =====================================================
say "گام ۶/۸ : کپی کانفیگ‌های بهینه‌شده"
# =====================================================
# PHP-FPM pool (نسخه‌ی PHP را در فایل جایگزین کن)
sed "s/php8.3/php${PHP_VER}/g" "$SRC_DIR/deploy/php/daneshyar-pool.conf" \
    > "/etc/php/${PHP_VER}/fpm/pool.d/daneshyar.conf"
# حذف pool پیش‌فرض برای صرفه‌جویی رم
[[ -f "/etc/php/${PHP_VER}/fpm/pool.d/www.conf" ]] && \
    mv "/etc/php/${PHP_VER}/fpm/pool.d/www.conf" "/etc/php/${PHP_VER}/fpm/pool.d/www.conf.disabled"

# Nginx
sed "s/php8.3/php${PHP_VER}/g" "$SRC_DIR/deploy/nginx/daneshyar.conf" \
    > "/etc/nginx/sites-available/daneshyar.conf"
ln -sf "/etc/nginx/sites-available/daneshyar.conf" "/etc/nginx/sites-enabled/daneshyar.conf"
rm -f /etc/nginx/sites-enabled/default

# MariaDB tune
cp "$SRC_DIR/deploy/mariadb/daneshyar-tune.cnf" "/etc/mysql/mariadb.conf.d/99-daneshyar.cnf"

# =====================================================
say "گام ۷/۸ : تست و ری‌استارت سرویس‌ها"
# =====================================================
nginx -t
systemctl restart "php${PHP_VER}-fpm"
systemctl restart nginx
systemctl restart mariadb
systemctl enable nginx "php${PHP_VER}-fpm" mariadb

# =====================================================
say "گام ۸/۸ : فایروال"
# =====================================================
if command -v ufw >/dev/null; then
    ufw allow 22/tcp >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    yes | ufw enable >/dev/null 2>&1 || true
fi

IP=$(curl -s --max-time 5 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
echo ""
say "✅ نصب کامل شد!"
echo "-------------------------------------------"
echo -e " آدرس سایت     : ${G}http://${IP}/${N}"
echo -e " پنل ادمین     : ${G}http://${IP}/admin/login.php${N}"
echo -e " دیتابیس       : ${DB_NAME}"
echo -e " کاربر دیتابیس : ${DB_USER}"
echo -e " رمز دیتابیس   : ${Y}${DB_PASS}${N}  (در .env ذخیره شد)"
echo "-------------------------------------------"
warn "کارهای باقی‌مانده (مهم):"
echo "  1) کلید جدید API را در .env بگذار:  nano ${APP_DIR}/.env"
echo "  2) رمز ادمین (ADMIN_PASS) را در .env عوض کن."
echo "  3) بعد از تست، install.php و migrate.php را پاک کن:"
echo "       rm -f ${APP_DIR}/install.php ${APP_DIR}/migrate.php"
echo "  4) برای فعال‌سازی خودکار اشتراک‌ها، cron زیر را اضافه کن (crontab -e):"
echo "       */5 * * * * curl -s 'http://127.0.0.1/api/scheduler.php?token=dy_sch_b037ca7f8ec42c0a11ff5d0bfbed5757' >/dev/null 2>&1"
