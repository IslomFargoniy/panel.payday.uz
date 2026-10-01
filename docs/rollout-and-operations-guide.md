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

## 🛠 2. Bosqichma-bosqich Rollout Jarayoni

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

## 📡 3. Jonli Qurilmalar Holati va Monitoring

Tizimdagi faol qurilmalar uzluksiz ishlashini ta'minlash uchun:
- **ISUP Qurilma 20 (`branch14`)**: Gateway orqali port 7660/7662 da bog'langan.
- **HTTP Listening Qurilmalar 15, 17, 18**: `/api/hikvision-callback` orqali webhook qabul qiladi.
- **Healthcheck & Monitoring**:
  Har 5 daqiqada `hikvision:healthcheck` cron vazifasi ishlaydi:
  - Agar gateway javob bermasa, `[5, 15, 30]` daqiqalik backoff bilan restart buyrug'ini beradi.
  - Faqat holat o'zgarganda (DOWN yoki UP) Telegram kanalga xabarnoma jo'natadi.

---

## 🔄 4. Orqaga Qaytish Rejasi (Rollback Plan)

Kutilmagan muammo yuzaga kelsa:
1. `git checkout <oldingi_commit_hash>`
2. `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`
3. `php artisan queue:restart`
4. Server ma'lumotlar bazasi `backup` nusxalari avtomatik saqlanib turishi tavsiya etiladi.
