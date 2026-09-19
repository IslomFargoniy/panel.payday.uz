# 4. Muammolarni Bartaraf Etish va Tez-Tez Beriladigan Savollar (FAQ)

Ushbu bo‘limda PayDay paneli va Hikvision terminallari bilan ishlashda yuzaga kelishi mumkin bo‘lgan eng keng tarqalgan muammolar va ularning yechimlari to‘liq yoritilgan.

---

## 1. Terminal Holati "Offline" (Ulanmayapti)

### 🔴 Belgilar:
- PayDay panelida qurilma yonida "Nofaol / Offline" turibdi.
- Hikvision web panelida ISUP holati "Registering..." yoki "Offline" bo‘lib qolgan.

### 🟢 Tekshirish va Yechim:
1. **Server IP va Portni tekshiring:**
   - Terminaldagi **Server IP** maydoniga serveringizning tashqi Statik IP manzili (masalan `193.180.213.188`) kiritilganiga ishonch hosil qiling (`localhost` yoki `127.0.0.1` bo‘lmasligi kerak).
   - Port `7660` ekanligini tekshiring.
2. **Firewall (Xavfsizlik devori) portlari ochiqligini tekshiring:**
   ```bash
   sudo ufw status | grep 7660
   ```
   Agar ochiq bo‘lmasa:
   ```bash
   sudo ufw allow 7660/tcp
   sudo ufw allow 7660/udp
   sudo ufw allow 7662/tcp
   ```
3. **Gateway xizmati ishlayotganini tekshiring:**
   ```bash
   sudo systemctl status hikvision-gateway
   ```
   Agar to‘xtab qolgan bo‘lsa:
   ```bash
   sudo systemctl restart hikvision-gateway
   ```
4. **Device ID va Device Key mosligini tekshiring:**
   - PayDay panelida yaratilgan qurilma `Device ID` va `Key` kiritilgan qiymatlar terminaldagi bilan harfma-harf bir xil bo‘lishi kerak (Katta-kichik harflarga sezgir).

---

## 2. Yuz Rasmi Terminalga Yuklanmayapti (Face Upload Error)

### 🔴 Belgilar:
- Panelda xodim yaratildi, lekin terminalda yuz orqali tanimayapti.
- Laravel logida `FaceLib / FaceDataRecord Error` chiqmoqda.

### 🟢 Tekshirish va Yechim:
1. **Rasm formati va hajmi:**
   - Hikvision terminallari faqat **JPG / JPEG** formatdagi rasmlarni qabul qiladi. PNG yoki WebP format yuklamang.
   - Rasm hajmi **200 KB dan oshmasligi** kerak (ideal o‘lcham: 50–100 KB).
2. **Storage Symlink va Rasm Havolasi:**
   - Serverda `php artisan storage:link` bajarilganligini tekshiring.
   - Rasm manzili to‘g‘ri ochilishini brauzerda tekshiring: `https://panel.payday.uz/storage/avatars/...jpg`.
3. **Yuzning rasmda joylashuvi:**
   - Yuz rasm markazida, ko‘zlar ochiq va fon iloji boricha bir xil rangda bo‘lishi lozim.

---

## 3. Davomat Vaqti Noto‘g‘ri Yozilmoqda (Timezone Offset)

### 🔴 Belgilar:
- Xodim soat 09:00 da keldi, lekin panelda 04:00 yoki 14:00 ko‘rinmoqda.

### 🟢 Tekshirish va Yechim:
1. **Terminalning Vaqt Mintaqasini tekshiring:**
   - Hikvision Web interfeysi ➔ `System ➔ System Settings ➔ Time Settings`.
   - Time Zone: `GMT+05:00 (Tashkent)`.
   - `NTP` sinxronizatsiyasini yoqing (`pool.ntp.org`).
2. **Server Vaqtini tekshiring:**
   ```bash
   timedatectl
   ```
   Agar boshqa vaqt mintaqasi bo‘lsa:
   ```bash
   sudo timedatectl set-timezone Asia/Tashkent
   ```
3. **Laravel `.env` faylini tekshiring:**
   ```env
   APP_TIMEZONE=Asia/Tashkent
   ```

---

## 4. Internet Uzilganda Davomat Qolib Ketishi (Offline Logs)

### 🔴 Savol:
Filialda internet bir necha soat uzilib qolsa, davomat yo‘qoladimi?

### 🟢 Yechim:
- **Yo‘qolmaydi!** Hikvision terminallari o‘zining ichki xotirasiga 100,000 tagacha davomat loglarini saqlaydi.
- Internet qayta ulanganda:
  - Terminal yangi hodisalarni to‘g‘ridan-to‘g‘ri yuboradi.
  - O‘tib ketgan kunlar logini tortib olish uchun serverda cron orqali ishlovchi buyruq mavjud:
    ```bash
    php artisan hikvision:sync-events --days=7
    ```
  - Ushbu buyruq oxirgi 7 kundagi barcha qolib ketgan hodisalarni bazaga to‘ldirib beradi.

---

## 5. Webhook / HTTP Listening 419 (Page Expired) yoki 403 Xatosi

### 🔴 Belgilar:
- HTTP listening orqali yuborilgan ma'lumotlar serverga tushmayapti.
- Nginx logida `419` yoki `403` status qaytmoqda.

### 🟢 Yechim:
- Hikvision qurilmalari CSRF token yubormaydi.
- Shuning uchun `routes/api.php` dagi `/api/hikvision-callback` manzili CSRF tekshiruvidan ozod etilgan.
- Endpoint aniq `https://panel.payday.uz/api/hikvision-callback` ekanligini tekshiring.

---

## 6. Foydali Tashxis Buyruqlari (Diagnostic Commands)

```bash
# 1. Laravel keshlarini tozalash
php artisan optimize:clear

# 2. Xatoliklar logini kuzatish
tail -n 100 -f /var/www/panel.payday.uz/storage/logs/laravel.log

# 3. Hikvision Gateway logini kuzatish
tail -n 100 -f /var/log/hikvision-gateway.log

# 4. Portlar ochiqligini tekshirish
sudo netstat -tulpn | grep -E '7660|7661|7662|80|443'

# 5. Navbatdagi xabarlarni qayta tekshirish
sudo supervisorctl status
```
