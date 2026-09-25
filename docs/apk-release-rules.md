# APK Build va Downloads Papkasi Qoidalari

Ushbu hujjat Android APK fayllarini yig'ish (build) va `public/downloads` papkasiga joylashtirish tartib-qoidalarini belgilaydi.

---

## 📌 QAT'IY QOIDALAR (STRICT RULES):

1. **Faqat Bitta APK Fayl**:
   - `public/downloads/` papkasida har doim **faqat bitta (eng oxirgi versiya)** APK fayli bo'lishi shart.
   - Papkada hech qachon 2 ta yoki undan ortiq `.apk` fayli (masalan, `payday.apk` va `payday-v1.0.0.apk`, yoki `app-debug.apk`) bir vaqtning o'zida turishi MUMKIN EMAS.
   - Ortiqcha symlink'lar (masalan `payday.apk -> ...`) yoki dublikat fayllar yaratilmaydi.

2. **Fayl Nomi Har Doim Versiya Bilan**:
   - APK fayli nomi aniq versiyasi bilan saqlanadi:
     ```
     payday-v{version}.apk
     ```
     *Misol: `payday-v1.0.0.apk`, `payday-v1.0.1.apk`*

3. **Eski Versiyalarni Tozalash**:
   - Yangi versiya APK yig'ilib `public/downloads/` ga ko'chirilganda, avvalgi barcha eski APK fayllar **darhol o'chiriladi**.

4. **`version.json` Format**:
   - `public/downloads/version.json` fayli faqat bitta oxirgi versiyani ko'rsatadi:
     ```json
     {
       "version": "1.0.0",
       "apk": "payday-v1.0.0.apk"
     }
     ```

5. **Serverda ham Bir Xillik**:
   - Lokal kompyuterda ham, ishlab chiqarish serverida ham (`/var/www/panel_payday_usr/data/www/panel.payday.uz/public/downloads`) bir xil tartib:
     - `payday-v{version}.apk` (bitta fayl)
     - `version.json`
