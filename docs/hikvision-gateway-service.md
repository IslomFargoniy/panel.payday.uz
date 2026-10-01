# 3. Hikvision ISUP Gateway Servisi (C++ Daemon)

**Hikvision ISUP Gateway** — bu serverda doimiy ishlovchi C++ daemon bo‘lib, Hikvision ISUP 5.0 (EHome) protokoli orqali terminallardan ulanishlarni qabul qiladi, jonli hodisalarni Laravel backendga uzatadi va Laravel tomonidan yuborilgan buyruqlarni (xodim qo‘shish, yuz rasmi yuklash, o‘chirish) terminallarga yetkazadi.

---

## 1. Gateway Arxitekturasi va Portlar

```
┌─────────────────────────┐
│ Hikvision Terminal (lar)│
└────────────┬────────────┘
             │ ISUP 5.0 (EHome)
             ▼
┌────────────────────────────────────────────────────────┐
│               Hikvision ISUP Gateway Daemon             │
│                                                        │
│  - TCP 7660 / UDP 7660 : CMS Ulanish va Ro'yxatdan o'tish│
│  - TCP 7662            : Alarm / Davomat Hodisalari    │
│  - TCP 7661 (Localhost): REST API (Laravel bilan aloqa)│
└────────────┬─────────────────────────────▲─────────────┘
             │ Event Callback              │ REST ISAPI So'rovlar
             │ (POST /api/hikvision-...)   │ (/api/isapi)
             ▼                             │
┌──────────────────────────────────────────┴─────────────┐
│                 Laravel PayDay Web Panel                │
│                 (PHP 8.2 + MySQL)                      │
└────────────────────────────────────────────────────────┘
```

---

## 2. Gateway-ni Kompilyatsiya Qilish (Build)

### 2.1. Kerakli Kutubxonalarni O‘rnatish
Serverda C++ kompilyatori va `make` o‘rnatilgan bo‘lishi kerak:
```bash
sudo apt update
sudo apt install -y build-essential g++ cmake libssl-dev libcurl4-openssl-dev
```

### 2.2. Hikvision Linux 64-bit SDK Fayllari
Kutubxonalar joylashuvi:
- SDK Headerlar: `/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include/`
- SDK Dinamik Kutubxonalari (`.so`): `/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib/`
- C++ Headerlar (`httplib.h`, `json.hpp`): `/opt/hikvision-gateway/include/`

### 2.3. Kompilyatsiya Buyrug‘i (g++)
Loyiha ildizida joylashgan `gateway_main.cpp` faylidan binar fayl yaratish:
```bash
cd /var/www/panel.payday.uz

g++ -std=c++17 -O2 gateway_main.cpp -o /usr/local/bin/hikvision-gateway \
    -I/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include \
    -I/opt/hikvision-gateway/include \
    -L/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib \
    -lHCISUPCMS -lHCISUPAlarm -lHCISUPSS -lpthread -ldl

sudo chmod +x /usr/local/bin/hikvision-gateway
```

### 2.4. Dinamik Kutubxonalar Yo‘lini Qo‘shish (LD_LIBRARY_PATH)
Tizimga Hikvision `.so` kutubxonalarini tanitish:
```bash
echo "/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib" | sudo tee /etc/ld.so.conf.d/hikvision.conf
sudo ldconfig
```

---

---

---

## 3. Servis Sifatida Sozlash (`hikvision-isup.service`)

Production muhitida Gateway alohida **systemd** servisi sifatida boshqariladi (`hikvision-isup.service`):
- `ExecStart=/usr/local/bin/hikvision-gateway` (yoki `/usr/local/bin/hikvision-gateway /etc/hikvision-gateway/gateway_config.json`)
- `Restart=always`
- Supervisor faqat `payday-worker:*` Laravel queue jarayonlari uchun ishlatiladi; Supervisor'da gateway **yo'q**.

### 3.1. Systemd Unit Konfiguratsiyasi
Unit fayli `/etc/systemd/system/hikvision-isup.service` (yoki `/etc/systemd/system/hikvision-gateway.service` aliasi):

```ini
[Unit]
Description=PayDay Hikvision ISUP 5.0 Gateway Service
After=network.target nginx.service mysql.service

[Service]
Type=simple
User=root
WorkingDirectory=/var/www/panel_payday_usr/data/www/panel.payday.uz
ExecStart=/usr/local/bin/hikvision-gateway /etc/hikvision-gateway/gateway_config.json
Restart=always
RestartSec=5
LimitNOFILE=65535
Environment="LD_LIBRARY_PATH=/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib"
Environment="HIKVISION_GATEWAY_CONFIG=/etc/hikvision-gateway/gateway_config.json"

StandardOutput=append:/var/log/hikvision-gateway.log
StandardError=append:/var/log/hikvision-gateway-error.log

[Install]
WantedBy=multi-user.target
```

Config qidirish tartibi:
1. `argv[1]` (masalan: `/usr/local/bin/hikvision-gateway /etc/hikvision-gateway/gateway_config.json`)
2. `HIKVISION_GATEWAY_CONFIG` environment o'zgaruvchisi
3. Binary yonidagi `gateway_config.json`
4. `/etc/hikvision-gateway/gateway_config.json`

```bash
sudo systemctl daemon-reload
sudo systemctl enable hikvision-isup
sudo systemctl restart hikvision-isup
sudo systemctl status hikvision-isup
```

---

## 4. Gateway-ni Xavfsiz Yangilash (`deploy-gateway.sh`)

> ⚠️ **MUHIM QOIDA:** Gateway dasturi `deploy.sh` ichida **kompilyatsiya qilinmaydi**. Umumiy veb-sayt deploy skripti gateway'ga tegmaydi. Gateway faqat alohida `deploy-gateway.sh` orqali qo'lda yangilanadi.

### Ishga tushirish:
```bash
# Oddiy yangilash:
./deploy-gateway.sh

# Konfiguratsiya faylini sozlash bilan birga:
./deploy-gateway.sh --setup-config
```

### Skript bajaradigan bosqichlar:
1. **Compile:** Yangi binary avval `/usr/local/bin/hikvision-gateway.new` vaqtinchalik manziliga kompilyatsiya qilinadi. Xatolik bo'lsa, jarayon to'xtaydi.
2. **Backup:** Ishlab turgan joriy binary `/usr/local/bin/hikvision-gateway.bak.<timestamp>` qilib saqlanadi.
3. **Almashtirish:** `.new` fayli `mv` yordamida atomik tarzda joyiga qo'yiladi.
4. **Restart:** `sudo systemctl restart hikvision-isup` bajariladi.
5. **Health check:** 10 soniya davomida `curl -fsS http://127.0.0.1:7661/health` (sozlangan bo'lsa `X-Gateway-Token` bilan) tekshiriladi va `/api/devices` dan qurilmalar olinadi.
6. **Rollback:** Agar 10 soniya ichida healthcheck javob bermasa, avtomatik ravishda zaxiradagi eski binary joyiga qaytariladi va servis qayta ishga tushiriladi (`exit 1`).

---

## 5. Xizmatni Boshqarish va Monitoring Buyruqlari

| Amal | Systemd Buyrug‘i |
|---|---|
| **Holatni ko‘rish** | `sudo systemctl status hikvision-isup` |
| **Qayta ishga tushirish** | `sudo systemctl restart hikvision-isup` |
| **To‘xtatish** | `sudo systemctl stop hikvision-isup` |
| **Jonli loglarni ko‘rish** | `tail -f /var/log/hikvision-gateway.log` |
| **Avtomatik Sog‘liq Tekshiruvi**| `php artisan hikvision:healthcheck` |


---

## 5. Gateway REST API (127.0.0.1:7661)

Laravel `HikvisionSyncService` daemon bilan `http://127.0.0.1:7661` orqali aloqa qiladi.

### 5.1. Ulanish Holatini Tekshirish
```bash
curl http://127.0.0.1:7661/api/devices
```
Javob:
```json
{
  "count": 2,
  "devices": [
    {
      "device_id": "TERM_BALAM_01",
      "ip": "195.158.24.12",
      "online": true,
      "last_seen": "2026-09-19 23:15:00"
    }
  ]
}
```

### 5.2. ISAPI Buyruq Yuborish (`POST /api/isapi`)
```bash
curl -X POST http://127.0.0.1:7661/api/isapi \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "TERM_BALAM_01",
    "method": "POST",
    "url": "POST /ISAPI/AccessControl/UserInfo/Record?format=json",
    "body": "{\"UserInfo\":{\"employeeNo\":\"101\",\"name\":\"Ali Valiyev\"}}"
  }'
```
