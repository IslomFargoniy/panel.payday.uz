# PayDay Panel — Ishga Tushirish va Operatsion Qo‘llanma (Rollout & Operations Guide)

Ushbu qo'llanma **Phases 0–5** doirasida amalga oshirilgan barcha xavfsizlik, hisob-kitob, ma'lumotlar yaxlitligi va ishonchlilik yangilanishlarini jonli (production) serverda xavfsiz va to'g'ri ishga tushirish uchun mo'ljallangan.

---

## 🔐 1. Yangi Muhit O'zgaruvchilari (`.env`)

Serverdagi `.env` faylida quyidagi yangi kalitlar to'g'ri sozlanganligiga ishonch hosil qiling:

```dotenv
# --- Telegram Bot Xavfsizligi ---
TELEGRAM_BOT_TOKEN="your_bot_token"
TELEGRAM_LOG_CHAT_ID="your_log_chat_id"
TELEGRAM_WEBHOOK_SECRET="generate_random_secret_string_32_chars"

# --- Hikvision ISUP Gateway Integratsiyasi ---
HIKVISION_GATEWAY_URL="http://127.0.0.1:7661"
HIKVISION_GATEWAY_TOKEN="your_shared_gateway_secret_token"
HIKVISION_GATEWAY_TOKEN_REQUIRED=false   # Yangilanish davrida soft-mode (false), keyin true ga o'tkaziladi
HIKVISION_USE_DEVICE_TIME=false          # Agar qurilma vaqti server vaqtidan farq qilsa, server vaqtidan foydalaniladi
HIKVISION_RESTART_COMMAND="sudo systemctl restart hikvision-isup"
HIKVISION_DAS_ADDRESS="193.180.213.188"
HIKVISION_TIMEOUT=10

# --- Ma'lumotlar Bazasi ---
DB_STRICT=false                          # Standart: false (Production MariaDB 10.6 sql_mode bo'sh holati uchun)
```

> ⚠️ **DIQQAT (Strict rejim bo'yicha ogohlantirish va o'tish rejasi):**
> Production MariaDB muhitida `sql_mode` bo'sh (strict emas). `DB_STRICT=true` ni yoqishdan oldin jonli bazaning zaxira nusxasida (staging/test muhitida) barcha INSERT/UPDATE va hisobot so'rovlarini to'liq sinab ko'rish **shart**. Aks holda uzunlik yoki tip mos kelmasligi sababli qurilma callbacklari 500 xatolik berib, eventlar saqlanmay qolishi mumkin.
>
> **Strict rejimga o'tish rejasi:**
> 1. Dastlabki deploy davrida `DB_STRICT=false` qoldiriladi.
> 2. `php artisan attendance:smoke-test` orqali barcha so'rovlar muvaffaqiyatli o'tishi tekshiriladi.
> 3. MariaDB 10.6 replikasida `DB_STRICT=true` yoqilib, kunlik eventlar oqimi sinovdan o'tkaziladi.
> 4. Sinovlar 100% muvaffaqiyatli yakunlangach, serverda `DB_STRICT=true` qilib yoqiladi.

---

## 🚀 2. Deploy'dan Oldin va Keyin Bajariladigan Qadamlar

### Deploy'dan oldin:
1. **Alohida branch va Pull Request:**
   Kodni to'g'ridan-to'g'ri `main` ga push qilmang. O'zgarishlarni alohida branch'ga push qiling (masalan `release/security-fixes`) va GitHub'da `main` ga Pull Request (PR) oching.
2. **CI Tekshiruvlari:**
   GitHub Actions'da avtomatik testlar (`tests.yml` va `lint.yml`) to'liq o'tishi kerak:
   - SQLite testlari;
   - MariaDB 10.6 testlari (`phpunit.mariadb.xml`);
   - TypeScript turlari va ESLint tekshiruvlari.
3. **Merge:**
   Faqat barcha CI testlari muvaffaqiyatli yakunlangach, PR `main` branchiga merge qilinadi.
4. **Server Git Remote URL ni yangilash (Tavsiya):**
   Serverdagi git remote manzili `git@github.com:IslomFargoniy/panel.payday.git` bo'lishi mumkin, hozirgi to'g'ri repo esa `panel.payday.uz.git`. Serverda remote URL'ni to'g'rilash uchun quyidagi buyruqni qo'lda bajaring (faqat serverda, deploy.sh ichida emas):
   ```bash
   git remote set-url origin git@github.com:IslomFargoniy/panel.payday.uz.git
   ```

### Deploy'dan keyin (Serverda bajariladigan tekshiruvlar):
1. **Attendance Smoke Test:**
   ```bash
   sudo -u panel_payday_usr /opt/php82/bin/php artisan attendance:smoke-test
   ```
   Barcha o'qish so'rovlari (dashboard, salary_report, monthly_attendance, attendance_grid, daily_attendance, mobile_myAttendance) "OK" holatda va qatorlar soni 0 dan katta ekanligiga ishonch hosil qiling.
2. **Qurilmalar online oqimini tekshirish:**
   4 ta online qurilmadan (AE7106709, GF0132950, GG8507033, branch14) yangi eventlar kelayotganini tekshiring:
   ```bash
   sudo -u panel_payday_usr /opt/php82/bin/php artisan hikvision:online-check
   ```
3. **Loglarda xatoliklar yo'qligini tekshirish:**
   `storage/logs/laravel.log` faylida `Hikvision callback rejected` va `Hikvision Callback Error` yozuvlari yo'qligini tekshiring:
   ```bash
   tail -n 100 storage/logs/laravel.log | grep -E "Hikvision callback rejected|Hikvision Callback Error"
   ```

---

## 🛠 3. Bosqichma-bosqich Rollout Jarayoni

### 1-qadam: Kodni tortib olish va paketlarni o'rnatish
```bash
git pull origin main
composer install --no-interaction --prefer-dist --optimize-autoloader
npm run build
```

### 2-qadam: Baza migratsiyalarini yurgizish
```bash
php artisan migrate --force
```
*Bu barcha yangi migratsiyalarni (SoftDeletes, avatar ustuni, va boshqalar) xavfsiz qo'shadi.*

### 3-qadam: Tarixiy ma'lumotlarni tozalash va birxillashtirish
```bash
# Ishchilar avatarlari yo'llaridagi ortiqcha /storage/ va storage/ prefikslarini tozalash:
php artisan worker:clean-avatar-paths --force

# Hikvision hodisalari attendanceStatus larini Enum ga moslash:
php artisan hikvision:normalize-statuses --force

# Qurilmalar shifrlash kalitlarini to'ldirish (bo'sh bo'lgan ISUP qurilmalar uchun):
php artisan hikvision:fill-default-keys --force
```

### 4-qadam: Qurilma va Server vaqti diagnostikasi (Drift Check)
```bash
# Qurilma vaqti va server vaqti o'rtasidagi tafovutni ko'rish:
php artisan hikvision:drift-report

# Agar zarur bo'lsa, xato saqlangan tarixiy yozuvlarni tuzatish:
php artisan hikvision:backfill-event-time --dry-run
php artisan hikvision:backfill-event-time --force
```

### 5-qadam: Telegram Webhook-ni xavfsiz token bilan ro'yxatdan o'tkazish
```bash
php artisan telegram:set-webhook
```

### 6-qadam: Keshlar va Queue workerlarni yangilash
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

# Queue workerlarni qayta ishga tushirish (SyncWorkerToHikvisionJob va boshqalar uchun):
php artisan queue:restart
sudo supervisorctl restart payday-worker:*
```

---

## 📡 4. Jonli Qurilmalar Holati va Monitoring

Tizimdagi faol qurilmalar uzluksiz ishlashini ta'minlash uchun:
- **ISUP Qurilma 20 (`branch14`)**: Gateway orqali port 7660/7662 da bog'langan.
- **HTTP Listening Qurilmalar 15, 17, 18**: `/api/hikvision-callback` orqali webhook qabul qiladi.
- **Healthcheck & Monitoring**:
  Har 5 daqiqada `hikvision:healthcheck` cron vazifasi ishlaydi:
  - Agar gateway javob bermasa, `[5, 15, 30]` daqiqalik backoff bilan restart buyrug'ini beradi.
  - Faqat holat o'zgarganda (DOWN yoki UP) Telegram kanalga xabarnoma jo'natadi.

### ⚠️ Gateway yangilanishi (`deploy-gateway.sh`)
ISUP Gateway dasturi veb-panelning umumiy `deploy.sh` skriptidan **butunlay ajratilgan** va har deploy paytida qayta kompilyatsiya qilinmaydi.
Gateway kodiga o'zgartirish kiritilganda u faqat alohida skript orqali qo'lda yangilanadi:
**Birinchi marta ishga tushirish tartibi:**
```bash
./deploy.sh                       # 1) veb-panel (gateway'ga tegmaydi)
./deploy-gateway.sh --dry-run     # 2) compile + zaxira manbasi va checksum'lar, hech narsa o'zgarmaydi
./deploy-gateway.sh               # 3) haqiqiy yangilash
# konfiguratsiya faylini yaratish/tekshirish bilan:
./deploy-gateway.sh --setup-config
```
> Serverdagi gateway jarayoni 19-sentabrdan beri **diskdan o'chirilgan** eski binary bilan ishlab turgan bo'lishi mumkin, diskdagi fayl esa boshqa (hech qachon sinalmagan) versiya. Shuning uchun zaxira diskdan emas, ishlab turgan jarayondan (`/proc/<pid>/exe`) olinadi va birinchi yangilashda bir martalik `hikvision-gateway.known-good` nusxasi saqlanadi. Batafsil: `docs/hikvision-gateway-service.md`, 4-bo'lim.

Skript quyidagi qat'iy xavfsizlik zanjirida ishlaydi:
0. Gateway tokeni config'dan PHP orqali o'qiladi (serverda `jq` yo'q); config yaroqsiz bo'lsa, hech narsa o'zgartirilmasdan to'xtaydi.
1. Kod `/usr/local/bin/hikvision-gateway.new` vaqtinchalik manziliga kompilyatsiya qilinadi.
2. Ishlab turgan **jarayondan** zaxira olinadi: `/usr/local/bin/hikvision-gateway.bak.<timestamp>` (oxirgi 5 tasi saqlanadi), checksum solishtiriladi.
3. Yangi binary vaqtinchalik faylga nusxalanib, `mv` bilan atomik tarzda joyiga qo'yiladi.
4. `sudo systemctl restart hikvision-isup` bajariladi.
5. 10 soniya ichida `/health` (token bilan) va `/api/devices` tekshiriladi.
6. Agar healthcheck o'tmasa, zaxira nusxa xuddi shu xavfsiz usulda qaytariladi, servis restart qilinadi, health check **qayta** tekshiriladi va natija (`Rollback OK` yoki `ROLLBACK HAM MUVAFFAQIYATSIZ`) chop etiladi.

---

## 🔄 5. Orqaga Qaytish Rejasi (Rollback Plan)

Kutilmagan muammo yuzaga kelsa:
1. `deploy.sh` da avtomatik tuzoq (`trap rollback ERR`) o'rnatilgan bo'lib, agar `git pull` dan keyin `composer install`, `npm ci`, `npm run build` yoki `artisan migrate` bosqichlarida xatolik yuz bersa, skript avtomatik tarzda `git reset --hard $PREV` qilib, oldingi barqaror holatga qaytaradi va keshni tozalaydi.
   > ⚠️ **Muhim:** Migratsiyadan keyingi qadamlardan birida xatolik yuz berib kod rollback qilinsa ham, ma'lumotlar bazasi migratsiyalari avtomatik orqaga qaytarilmaydi. Rollbackdan so'ng `php artisan migrate:status` ni tekshirish shart.
2. Qo'lda orqaga qaytarish uchun:
   ```bash
   git reset --hard <oldingi_barqaror_commit>
   sudo composer install --no-interaction --prefer-dist --optimize-autoloader
   sudo -u panel_payday_usr /opt/php82/bin/php artisan optimize:clear
   sudo -u panel_payday_usr /opt/php82/bin/php artisan config:cache
   sudo -u panel_payday_usr /opt/php82/bin/php artisan route:cache
   sudo -u panel_payday_usr /opt/php82/bin/php artisan queue:restart
   ```
3. Server ma'lumotlar bazasi `backup` nusxalari avtomatik saqlanib turishi tavsiya etiladi.

---

## 👤 6. Server Foydalanuvchilari, Crontab va Sudoers Sozlamalari

Serverdagi jarayonlar (php-fpm va queue worker) `panel_payday_usr` foydalanuvchisi sifatida ishlaydi. Fayl egaligi (`storage` va `bootstrap/cache`) `root` ga o'tib ketmasligi va permissions xatoliklari kelib chiqmasligi uchun quyidagi tizim sozlamalarini amalga oshirish zarur:

### 1. Laravel Scheduler-ni `panel_payday_usr` crontab-iga ko'chirish
Hozirda `schedule:run` root crontab'ida turibdi. Uni `panel_payday_usr` ga ko'chirish:
```bash
# 1) Root crontab'dan schedule:run qatorini o'chirish:
sudo crontab -e

# 2) panel_payday_usr foydalanuvchisi crontab-iga qo'shish:
sudo crontab -u panel_payday_usr -e
```
Qo'shiladigan qator:
```cron
* * * * * /opt/php82/bin/php /var/www/panel_payday_usr/data/www/panel.payday.uz/artisan schedule:run >> /dev/null 2>&1
```

### 2. Healthcheck servisini parolsiz qayta ishga tushirish uchun Sudoers qoidasi
Gateway sog'lig'i tekshirilganda (`hikvision:healthcheck`) agar gateway to'xtab qolsa, artisan buyrug'i `systemctl restart hikvision-isup` ni chaqiradi. Buning uchun `panel_payday_usr` ga parolsiz restart huquqini berish zarur:
```bash
sudo visudo -f /etc/sudoers.d/panel_payday_usr
```
Fayl ichiga quyidagi qatorni yozing:
```sudoers
panel_payday_usr ALL=(root) NOPASSWD: /bin/systemctl restart hikvision-isup
```
Saqlang va huquqni tekshiring:
```bash
sudo chmod 0440 /etc/sudoers.d/panel_payday_usr
```

