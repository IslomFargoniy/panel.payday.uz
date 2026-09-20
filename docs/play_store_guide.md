# 🚀 PayDay — Google Play Store Nashr Qilish Bo'yicha To'liq Qo'llanma

Ushbu hujjat **PayDay** mobil ilovasini Google Play Console-ga muvaffaqiyatli yuklash va moderatsiyadan o'tkazish uchun zarur bo'lgan barcha ma'lumotlar, havolalar va matnlarni o'z ichiga oladi.

---

## 🌐 1. Rasmiy Havolalar (Store Listing & Policy URLs)

| Maqsad | Havola (URL) |
|---|---|
| **Maxfiylik Siyosati (Privacy Policy URL)** | `https://panel.payday.uz/privacy-policy` |
| **Foydalanish Shartlari (Terms of Service)** | `https://panel.payday.uz/terms-of-service` |
| **Qo'llab-quvvatlash Email (Support Email)** | `support@payday.uz` |
| **Rasmiy Veb-sayt (Website)** | `https://panel.payday.uz` |

---

## 📝 2. Google Play Store Matnlari (Store Listing Metadata)

### 🇺🇿 O'zbekcha (Default - O'zbekiston)

* **Ilova nomi (App Name - max 30 belgi):**
  > PayDay: Xodimlar va Ish haqi

* **Qisqa tavsif (Short Description - max 80 belgi):**
  > Xodimlar davomati, kunlik/oylik ish vaqti hisobi va oylik maosh boshqaruvi tizimi.

* **To'liq tavsif (Full Description - max 4000 belgi):**
  > **PayDay** — korxona, ishlab chiqarish va xizmat ko'rsatish sohasidagi xodimlarni boshqarish, davomatni nazorat qilish hamda oylik maoshlarni avtomatlashtirilgan tarzda hisoblash uchun qulay va zamonaviy mobil platforma.
  >
  > 🌟 **ASOSIY IMKONIYATLAR:**
  >
  > 📊 **Boshqaruv Paneli (Dashboard):**
  > • Bugungi kunlik davomat holati va kelgan xodimlar foizi;
  > • O'z vaqtida kelganlar, kechikkanlar va kelmaganlar statistikasi;
  > • Barcha filiallar kesimida real-time KPI ko'rsatkichlari.
  >
  > 👥 **Xodimlar Boshqaruvi:**
  > • Har bir xodim uchun shaxsiy rejim, smena vaqtlari va soatlik/kunlik stavkalar;
  > • Xodimlar bo'yicha qidiruv va filtrlash;
  > • Hikvision biometrik terminallaridan uzatilgan kirish-chiqish (Check-in / Check-out) loglari.
  >
  > 📅 **Davomat va 31 Kunlik Matritsa:**
  > • Kunlik keldi-ketdi, kechikish daqiqalari va ishlangan vaqt hisoboti;
  > • 31 kunlik to'liq oylik davomat jadvali (2D scroll matritsasi);
  > • Bitta bosishda Excel (.xlsx) formatida hisobot yaratish va ulashish.
  >
  > 💰 **Ish Haqi va To'lovlar:**
  > • Ishlangan soatlar va jarimalarni hisobga olgan holda oylik maoshni avtomatik hisoblash;
  > • Ilovaning o'zidan avans va to'lovlarni amalga oshirish;
  > • Amalga oshirilgan barcha to'lovlarning shaffof tarixi.
  >
  > 🏢 **Filiallar va Qurilmalar:**
  > • Filiallar manzili, tarif stavkalari va ulangan Hikvision terminallari monitoringi.
  >
  > 🔒 **Xavfsizlik va Qulaylik:**
  > • Biometriya (Barmoq izi / Face ID) orqali xavfsiz kirish;
  > • O'zbek, Rus va Ingliz tillari;
  > • Qorong'i va yorug' rejim (Dark / Light mode).

---

### 🇷🇺 Русский (Russian)

* **Название приложения (App Name):**
  > PayDay: Учет сотрудников

* **Краткое описание (Short Description):**
  > Учет посещаемости, рабочего времени, зарплаты и филиалов предприятия.

* **Полное описание (Full Description):**
  > **PayDay** — современная система автоматизации учета рабочего времени сотрудников, контроля посещаемости и расчета заработной платы для бизнеса и предприятий.

---

## 🔒 3. Google Play Data Safety (Ma'lumotlar Xavfsizligi Bo'limi)

Google Play Console so'rovnomasida quyidagi javoblarni belgilang:

1. **Ma'lumot to'planadimi?** — Ha (`Yes`).
2. **Ma'lumotlar shifrlanadimi?** — Ha, barcha ma'lumotlar tranzit paytida HTTPS/TLS orqali shifrlanadi.
3. **Foydalanuvchi hisobini o'chirishni so'rashi mumkinmi?** — Ha, profil sahifasidan yoki qo'llab-quvvatlash xizmati orqali.
4. **To'planadigan ma'lumot turlari:**
   - **Personal info (Shaxsiy ma'lumot):** Ism, Email, Telefon raqami (`App functionality, Account management`).
   - **Photos & Videos:** Xodim profil surati (`App functionality, Optional`).
   - **Financial info:** Ish haqi hisob-kitoblari (`App functionality`).
   - **Biometrics:** Faqat qurilmaning o'zida lokal tekshiriladi (Serverga yuborilmaydi).

---

## 📦 4. Yig'ilgan Fayllar (Artifacts)

| Fayl turi | Fayl manzili | Tavsif |
|---|---|---|
| **Debug APK** | `android/app/build/outputs/apk/debug/app-debug.apk` | Telefonga to'g'ridan-to'g'ri o'rnatib sinash uchun |
| **Debug AAB** | `android/app/build/outputs/bundle/debug/app-debug.aab` | Google Play Console ichki testiga yuklash uchun |

---

## 🔑 5. Release uchun Imzolash (Keystore yaratish)

Google Play-ga Production versiyani yuklash uchun quyidagi buyruq orqali imzolangan release AAB yaratishingiz mumkin:

```bash
# 1. Keystore yaratish (agar mavjud bo'lmasa):
keytool -genkey -v -keystore release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias payday

# 2. Release AAB yig'ish:
./gradlew bundleRelease
```
