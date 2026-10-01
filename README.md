<div align="center">

<img src="public/images/icons/payday-icon.png" width="120" alt="PayDay">

# 🚀 PayDay Panel – Premium Boshqaruv Tizimi

**Zamonaviy, Tezkor va Keng Qamrovli Xodimlar Davomati va Maosh Boshqaruv Paneli**

[![React Version](https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://reactjs.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

[Xususiyatlar](#✨-asosiy-xususiyatlar) • [Texnologiyalar](#🛠-texnologiyalar-steki) • [O'rnatish](#🚀-tezkor-boshlash) • [To'lov Tizimlari](#💳-tolov-integratsiyalari) • [Bog'lanish](#📞-boglanish-va-qollab-quvvatlash)

</div>

---

## 📖 Loyiha Haqida

**PayDay Panel** — biznes jarayonlarini boshqarish, foydalanuvchilar va to'lovlarni nazorat qilish hamda kuchli analitikani taqdim etuvchi zamonaviy boshqaruv paneli. U yuqori unumdorlik va qulay foydalanuvchi interfeysiga (UI/UX) asoslangan bo'lib, eng so'nggi veb-texnologiyalar yordamida ishlab chiqilgan. 

Tizim ko'p tillilikni (i18n), xavfsiz autentifikatsiyani, ijtimoiy tarmoqlar orqali kirishni (Socialite) va real vaqtda ishlash qobiliyatini qo'llab-quvvatlaydi.

---

## ✨ Asosiy Xususiyatlar

🎯 **Zamonaviy va Interaktiv UI/UX**
- **React 19 & Inertia.js**: Sahifalar yuklanishisiz tezkor o'tishlar.
- **Shadcn UI & Tailwind CSS v4**: Chiroyli va moslashuvchan (responsive) dizayn komponentlari.
- **Keng qamrovli Dashboard**: ApexCharts va ECharts yordamida vizual statistika va grafiklar.

🔐 **Xavfsizlik va Ruxsatlar (ACL)**
- **Spatie Permissions**: Moslashuvchan rollar va ruxsatlar tizimi (Role-based access control).
- **Sanctum API**: API orqali xavfsiz ulanish, alohida `panel` va `worker` token qobiliyatlari.
- **Socialite Login**: Google orqali oson avtorizatsiya.

🌍 **Ko'p Tillilik (i18n)**
- 3 xil to'liq mahalliylashtirilgan til: **O'zbekcha (uz), Ruscha (ru), Inglizcha (en)**.

📊 **Davomat va Hisobotlar**
- **Hikvision ISUP 5.0 & HTTP Listening**: Terminallardan hodisalarni real-vaqtda yoki avtomatik sinxronizatsiya orqali qabul qilish.
- **Ish jadvallari va bayramlar**: Haftalik ish kunlari, bayramlar va shaxsiy ta'tillarni hisobga oluvchi hisob-kitob.
- **Telegram Bot & Mini App**: Telegram orqali xodimlarni ro'yxatga olish va davomatini belgilash.
- Excel formatida ma'lumotlarni yuklash va eksport qilish.

🚀 **Qo'shimcha Imkoniyatlar**
- **PWA (Progressive Web App)**: Ilovani smartfon yoki kompyuterga o'rnatish imkoniyati.
- **Telescope**: Laravel ilovasidek xatolarni kuzatib borish va debug qilish.

---

## 🛠 Texnologiyalar Steki

| Qism | Texnologiyalar |
| :--- | :--- |
| **Backend** | PHP 8.2+, Laravel 12.0, Sanctum, Socialite, Spatie Permissions |
| **Frontend** | React 19, Inertia.js, Tailwind CSS 4, Shadcn UI (Radix UI) |
| **Ma'lumotlar Bazasi** | MySQL / SQLite |
| **Qurilmalar Integratsiyasi** | Hikvision ISUP 5.0 (C++ Gateway), HTTP Listening |
| **Kutubxonalar** | ApexCharts, ECharts, i18next, ExcelJS |

---

## 📚 Server va Hikvision Hujjatlari (Docs)

Boshqa serverlarga o‘rnatish va Hikvision terminallari bilan ishlash bo‘yicha to‘liq qo‘llanmalar [`docs/`](docs/README.md) papkasida joylashgan:

- 🖥️ **[Serverni O‘rnatish va Sozlash](docs/server-installation-guide.md)** — Nginx, PHP 8.2, MySQL, SSL, Supervisor va Cron sozlash.
- 📹 **[Hikvision Terminallari Integratsiyasi](docs/hikvision-integration-guide.md)** — ISUP 5.0, HTTP Listening va tarmoq parametrlari.
- ⚙️ **[Hikvision ISUP Gateway Servisi (C++)](docs/hikvision-gateway-service.md)** — C++ daemonni kompilyatsiya qilish va systemd xizmati.
- 🛠️ **[Muammolarni Bartaraf Etish (FAQ)](docs/troubleshooting-and-faq.md)** — Offline holatlar, yuz rasmlari, vaqt mintaqasi va diagnostika.
- 🚀 **[Ishga Tushirish va Rollout Qo'llanmasi](docs/rollout-and-operations-guide.md)** — Baza migratsiyalari, diagnostika va muhit sozlamalari.

---

### 3️⃣ Ma'lumotlar bazasi sozlamalari

Ma'lumotlar bazasini `.env` faylida sozlang va migratsiyalarni ishlating:
```bash
php artisan migrate --force
```

> **🔑 Standart Kirish Ma'lumotlari:**
> - **Login:** `admin@gmail.com`
> - **Parol:** `123456`

### 4️⃣ Loyihani ishga tushirish (Development Mode)

Barcha jarayonlarni (Backend, Frontend Vite serveri, Navbatlar va Loglar) bir vaqtda ishga tushirish uchun qulay buyruq:
```bash
composer run dev
```
Shundan so'ng ilova odatda `http://localhost:8000` manzilida ishga tushadi.

---

## 📜 Qo'shimcha Havolalar

- 🔭 **Telescope (Debug)**: `/telescope`
- 📚 **Swagger (API Docs)**: `/api/documentation`
  *(Swagger hujjatlarini yangilash uchun `php artisan l5-swagger:generate` buyrug'ini ishlating)*

---

## 📞 Bog'lanish va Qo'llab-quvvatlash

Loyiha yuzasidan savollar yoki xavfsizlikka oid muammolar topgan bo'lsangiz, iltimos, to'g'ridan-to'g'ri elektron pochtaga murojaat qiling: 
📧 **abdurahmanislam304@gmail.com**

<a href="https://payme.uz/@longevity" target="_blank">
  <img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" alt="Meni Qo'llab-quvvatlang" style="height: 41px !important;width: 174px !important;box-shadow: 0px 3px 2px 0px rgba(190, 190, 190, 0.5) !important;-webkit-box-shadow: 0px 3px 2px 0px rgba(190, 190, 190, 0.5) !important;" >
</a>

<br>

**Ijtimoiy Tarmoqlar:**
- 🐙 [GitHub](https://github.com/IslomFargoniy)
- 💬 [Telegram](https://t.me/IslomFargoniy)
- 📺 [YouTube](https://www.youtube.com/@IslomFargoniy)
- ✈️ [Telegram](https://t.me/IslomFargoniy)

---

<div align="center">
  
Tizim ❤️ bilan **Islam Abdurahman** tomonidan ishlab chiqilgan.<br>
Loyihaning barcha huquqlari [MIT Litsenziyasi](LICENSE.md) asosida himoyalangan.
</div>
