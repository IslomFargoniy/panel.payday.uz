# 1. Yangi Serverda PayDay Panelini O‘rnatish va Sozlash Qo‘llanmasi

Ushbu qo‘llanma orqali siz yangi Linux (Ubuntu 22.04 / 24.04 LTS) serverida **PayDay** loyihasini noldan to‘liq ishga tushirishingiz mumkin.

---

## 1. Minimal Server Talablari
- **Operatsion tizim:** Ubuntu 22.04 / 24.04 LTS (yoki Debian 12)
- **CPU:** 2 vCPU yoki undan yuqori
- **RAM:** Kamida 2 GB (4 GB tavsiya etiladi)
- **Disk:** 20 GB SSD/NVMe
- **Statik Public IP:** Hikvision terminallari ulanishi uchun ochiq tashqi IP manzil

---

## 2. Asosiy Paketlarni O‘rnatish

Serverga SSH orqali kiring va tizimni yangilang:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip zip htop ufw
```

### 2.1. PHP 8.2+ va Kerakli Kengaytmalarni O‘rnatish
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd \
    php8.2-intl php8.2-redis php8.2-sqlite3
```

### 2.2. Composer O‘rnatish
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 2.3. Node.js (v20+) va NPM O‘rnatish
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### 2.4. MySQL Server O‘rnatish
```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```
Baza va foydalanuvchi yaratish:
```sql
sudo mysql -u root -p
CREATE DATABASE payday_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'payday_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON payday_db.* TO 'payday_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Loyihani Serverga Yuklash va O‘rnatish

Loyiha papkasiga o‘ting (masalan `/var/www/panel.payday.uz`):
```bash
sudo mkdir -p /var/www/panel.payday.uz
sudo chown -R $USER:$USER /var/www/panel.payday.uz
cd /var/www/panel.payday.uz

# Git orqali klon qilish
git clone git@github.com:IslomFargoniy/panel.payday.git .
```

### 3.1. Muhit Sozlamalari (.env)
```bash
cp .env.example .env
nano .env
```
`.env` faylidagi asosiy qatorlarni to‘ldiring:
```env
APP_NAME=PayDay
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.payday.uz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payday_db
DB_USERNAME=payday_user
DB_PASSWORD=StrongPassword123!

QUEUE_CONNECTION=database
TIMEZONE=Asia/Tashkent
```

### 3.2. Composer va NPM Paketlarini O‘rnatish
```bash
# Composer bog'liqliklari
composer install --no-dev --optimize-autoloader

# Kalit yaratish
php artisan key:generate

# Migratsiyalar va Seederlar
php artisan migrate --force

# Storage havolasini yaratish
php artisan storage:link

# Frontend Build yig'ish
npm ci
npm run build
```

### 3.3. Ruxsatlarni (Permissions) Sozlash
```bash
sudo chown -R www-data:www-data /var/www/panel.payday.uz
sudo chmod -R 775 /var/www/panel.payday.uz/storage /var/www/panel.payday.uz/bootstrap/cache
```

---

## 4. Nginx VHost Sozlash

Yangi Nginx konfiguratsiya faylini yarating:
```bash
sudo nano /etc/nginx/sites-available/panel.payday.uz
```
Quyidagi konfiguratsiyani kiriting:
```nginx
server {
    listen 80;
    server_name panel.payday.uz;
    root /var/www/panel.payday.uz/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    # Katta yuz rasmlari yuklanishi uchun limit
    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Saytni faollashtiring va Nginx-ni qayta ishga tushiring:
```bash
sudo ln -s /etc/nginx/sites-available/panel.payday.uz /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 4.1. SSL Sertifikatini O‘rnatish (Let's Encrypt Certbot)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d panel.payday.uz
```

---

## 5. Cron Job va Supervisor Sozlash

### 5.1. Laravel Cron Rejalashtiruvchisi (Schedule)
Hikvision loglarini vaqti-vaqti bilan avtomatik tortib olish (`hikvision:sync-events`) va boshqa rejali topshiriqlar uchun server crontab-iga quyidagini qo‘shing:
```bash
sudo crontab -u www-data -e
```
Fayl oxiriga qo‘shing:
```cron
* * * * * cd /var/www/panel.payday.uz && php artisan schedule:run >> /dev/null 2>&1
```

### 5.2. Supervisor (Queue Worker)
Navbatdagi xabarlar va og'ir jarayonlar uchun:
```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/payday-worker.conf
```
Ichiga yozing:
```ini
[program:payday-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/panel.payday.uz/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/panel.payday.uz/storage/logs/worker.log
stopwaitsecs=3600
```
Supervisor-ni qayta ishga tushiring:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start payday-worker:*
```

---

## 6. Xavfsizlik Devori (UFW Firewall)

Serverda kerakli portlarni oching:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Hikvision ISUP va Gateway portlari:
sudo ufw allow 7660/tcp   # ISUP CMS Registration
sudo ufw allow 7660/udp   # ISUP CMS UDP
sudo ufw allow 7662/tcp   # ISUP Alarm / Event Stream
# Diqqat: 7661 porti faqat localhost (127.0.0.1) uchun ishlaydi, tashqariga ochish shart emas!

sudo ufw enable
sudo ufw status
```
