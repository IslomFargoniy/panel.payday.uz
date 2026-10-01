#!/usr/bin/env bash

# ==============================================================================
# PayDay Panel - Hikvision C++ ISUP Gateway Deployment Script
# ==============================================================================
# Ushbu skript faqat qo'lda ishga tushiriladi va ISUP Gateway daemonini
# xavfsiz kompilyatsiya qilish, zaxiralash, atomik almashtirish va
# sog'ligini tekshirish uchun xizmat qiladi.
# ==============================================================================

set -e

# Server configuration
SERVER_USER="younine"
SERVER_HOST="193.180.213.188"
SERVER_PATH="/var/www/panel_payday_usr/data/www/panel.payday.uz"

# Arguments
SETUP_CONFIG=false
DRY_RUN=false
for arg in "$@"; do
    case "$arg" in
        --setup-config)
            SETUP_CONFIG=true
            ;;
        --dry-run)
            DRY_RUN=true
            ;;
        *)
            echo "Noma'lum argument: $arg (mavjud: --setup-config, --dry-run)" >&2
            exit 2
            ;;
    esac
done

# Text styles
GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
NC="\033[0m"

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}📡 Hikvision ISUP Gateway - Deployment Boshlanmoqda${NC}"
echo -e "${BLUE}======================================================${NC}"

# Remote execution via SSH
ssh "$SERVER_USER@$SERVER_HOST" bash -s -- "$SETUP_CONFIG" "$DRY_RUN" <<'EOF'
set -e

SETUP_CONFIG="$1"
DRY_RUN="$2"
SERVER_PATH="/var/www/panel_payday_usr/data/www/panel.payday.uz"
CONFIG_DIR="/etc/hikvision-gateway"
CONFIG_FILE="$CONFIG_DIR/gateway_config.json"
TARGET_BIN="/usr/local/bin/hikvision-gateway"
NEW_BIN="/usr/local/bin/hikvision-gateway.new"
KNOWN_GOOD="/usr/local/bin/hikvision-gateway.known-good"
TIMESTAMP=$(date +%Y%m%d%H%M%S)
BACKUP_BIN="/usr/local/bin/hikvision-gateway.bak.$TIMESTAMP"

# Vaqtinchalik kompilyatsiya fayli har qanday holatda (xato yoki --dry-run) tozalansin
trap 'sudo rm -f "$NEW_BIN"' EXIT

GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
NC="\033[0m"

# Binary'ni xavfsiz o'rnatish: vaqtinchalik faylga nusxalab, keyin mv bilan almashtiriladi.
# mv inode'ni almashtiradi, shuning uchun ishlab turgan binary ustiga yozishda
# "Text file busy" xatosi chiqmaydi (oddiy cp shu xatoga olib keladi).
install_binary() {
    local src="$1"
    local tmp="$TARGET_BIN.tmp.$$"
    sudo cp "$src" "$tmp" && sudo chmod +x "$tmp" && sudo mv -f "$tmp" "$TARGET_BIN"
}

# Gateway /health javob berishini 10 soniyagacha kutish (0 = OK, 1 = javob bermadi)
wait_for_health() {
    local i
    for i in {1..10}; do
        echo -n "Kutilmoqda ($i/10s)... "
        if curl -fsS "${HEADER_FLAG[@]}" http://127.0.0.1:7661/health >/dev/null 2>&1; then
            echo -e "${GREEN}OK!${NC}"
            return 0
        fi
        sleep 1
    done
    echo ""
    return 1
}

# 0. --setup-config tekshiruvi
if [ "$SETUP_CONFIG" = "true" ] && [ "$DRY_RUN" = "true" ]; then
    echo -e "${YELLOW}ℹ️  --dry-run: --setup-config bosqichi o'tkazib yuborildi (fayllar yaratilmaydi).${NC}"
elif [ "$SETUP_CONFIG" = "true" ]; then
    echo -e "${BLUE}--> Konfiguratsiya fayli tekshirilmoqda ($CONFIG_FILE)...${NC}"
    if [ ! -d "$CONFIG_DIR" ]; then
        echo -e "${YELLOW}Katalog yaratilmoqda: $CONFIG_DIR${NC}"
        sudo mkdir -p "$CONFIG_DIR"
    fi

    if [ ! -f "$CONFIG_FILE" ]; then
        echo -e "${YELLOW}Konfiguratsiya fayli topilmadi. Repo nusxasi ko'chirilmoqda...${NC}"
        sudo cp "$SERVER_PATH/gateway_config.json" "$CONFIG_FILE"
        sudo chmod 600 "$CONFIG_FILE"
        echo -e "${GREEN}✔ $CONFIG_FILE yaratildi va 600 ruxsati berildi.${NC}"
    else
        echo -e "${GREEN}✔ $CONFIG_FILE allaqachon mavjud.${NC}"
    fi

    echo -e "\n${YELLOW}ℹ️  Systemd unit uchun drop-in ko'rsatmasi (ExecStart sozlash):${NC}"
    echo -e "Systemd unit faylini qo'lda o'zgartirish o'rniga quyidagi drop-in qo'shilishi tavsiya etiladi:"
    echo -e "------------------------------------------------------------------"
    echo -e "sudo mkdir -p /etc/systemd/system/hikvision-isup.service.d"
    echo -e "sudo tee /etc/systemd/system/hikvision-isup.service.d/override.conf <<'CONF'"
    echo -e "[Service]"
    echo -e "ExecStart="
    echo -e "ExecStart=/usr/local/bin/hikvision-gateway /etc/hikvision-gateway/gateway_config.json"
    echo -e "CONF"
    echo -e "sudo systemctl daemon-reload"
    echo -e "------------------------------------------------------------------"
fi

# Gateway tokenini config'dan o'qish (serverda jq yo'q, shuning uchun PHP ishlatiladi).
# Binary almashtirilishidan OLDIN o'qiladi: config buzuq bo'lsa, hech narsa o'zgarmasdan to'xtaydi.
GW_TOKEN=""
if [ -f "$CONFIG_FILE" ]; then
    if ! GW_TOKEN=$(sudo /opt/php82/bin/php -r '$c = json_decode((string) @file_get_contents($argv[1]), true); if (!is_array($c)) { fwrite(STDERR, "INVALID_JSON\n"); exit(2); } echo (string) ($c["gateway_token"] ?? "");' "$CONFIG_FILE"); then
        echo -e "${RED}❌ Xatolik: $CONFIG_FILE o'qilmadi yoki yaroqsiz JSON. Hech narsa o'zgartirilmadi.${NC}"
        exit 1
    fi
    if [ -n "$GW_TOKEN" ]; then
        echo -e "${GREEN}✔ Gateway tokeni config'dan o'qildi (health check X-Gateway-Token bilan bajariladi).${NC}"
    else
        echo -e "${YELLOW}ℹ️  Config'da gateway_token bo'sh: health check tokensiz bajariladi.${NC}"
    fi
else
    echo -e "${YELLOW}ℹ️  $CONFIG_FILE topilmadi: health check tokensiz bajariladi.${NC}"
fi

HEADER_FLAG=()
if [ -n "$GW_TOKEN" ]; then
    HEADER_FLAG=(-H "X-Gateway-Token: $GW_TOKEN")
fi

# 1. SDK mavjudligini tekshirish
if [ ! -d "/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include" ]; then
    echo -e "${RED}❌ Xatolik: HCISUP SDK topilmadi (/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include).${NC}"
    exit 1
fi

echo -e "\n${BLUE}--> 1. Yangi binary vaqtinchalik faylga kompilyatsiya qilinmoqda ($NEW_BIN)...${NC}"
sudo g++ -std=c++17 -O2 "$SERVER_PATH/gateway_main.cpp" -o "$NEW_BIN" \
    -I/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include \
    -I/opt/hikvision-gateway/include \
    -L/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib \
    -lHCISUPCMS -lHCISUPAlarm -lHCISUPSS -lpthread -ldl

sudo chmod +x "$NEW_BIN"
echo -e "${GREEN}✔ Yangi binary muvaffaqiyatli kompilyatsiya qilindi.${NC}"

# 2. Joriy binary zaxira nusxasini olish
# Muhim: zaxira DISKDAGI fayldan emas, avvalo ISHLAB TURGAN jarayondan (/proc/<pid>/exe) olinadi.
# Sababi: diskdagi fayl ishlab turgan versiyadan farq qilishi mumkin (masalan, oddiy deploy yangi
# binary yozib, servisni restart qilmagan bo'lsa) va u hech qachon sinalmagan bo'lishi mumkin.
PID=$(systemctl show -p MainPID --value hikvision-isup 2>/dev/null || echo 0)
PID=${PID:-0}
BACKUP_SRC=""
BACKUP_FROM_PROC=false
if [ "$PID" -gt 0 ] 2>/dev/null && sudo test -r "/proc/$PID/exe"; then
    BACKUP_SRC="/proc/$PID/exe"
    BACKUP_FROM_PROC=true
    echo -e "${BLUE}--> 2. Zaxira manbasi: ishlab turgan jarayon (PID $PID, /proc/$PID/exe)${NC}"
elif [ -f "$TARGET_BIN" ]; then
    BACKUP_SRC="$TARGET_BIN"
    echo -e "${YELLOW}⚠️ Ishlab turgan jarayon topilmadi. Zaxira diskdagi fayldan olinadi ($TARGET_BIN) — u sinalgan versiya bo'lmasligi mumkin!${NC}"
else
    echo -e "${YELLOW}⚠️ Zaxira olinadigan binary topilmadi: rollback imkonsiz bo'ladi.${NC}"
fi

# Checksum'lar (solishtirish oson bo'lishi uchun)
sha_of() { sudo sha256sum "$1" 2>/dev/null | cut -c1-16; }
echo -e "${BLUE}    Checksum (sha256, dastlabki 16 belgi):${NC}"
if [ "$BACKUP_FROM_PROC" = "true" ]; then
    echo "    ishlab turgan jarayon : $(sha_of "/proc/$PID/exe")"
fi
[ -f "$TARGET_BIN" ] && echo "    diskdagi $TARGET_BIN : $(sha_of "$TARGET_BIN")"
echo "    yangi (kompilyatsiya) : $(sha_of "$NEW_BIN")"
if [ "$BACKUP_FROM_PROC" = "true" ] && [ -f "$TARGET_BIN" ] && [ "$(sha_of "/proc/$PID/exe")" != "$(sha_of "$TARGET_BIN")" ]; then
    echo -e "${YELLOW}    ⚠️ Diskdagi binary ishlab turgan versiyadan FARQ QILADI (ishlab turgani zaxiraga olinadi).${NC}"
fi

if [ "$DRY_RUN" = "true" ]; then
    echo -e "\n${YELLOW}ℹ️  --dry-run: binary almashtirilmadi, servis restart qilinmadi, zaxira fayllar yozilmadi.${NC}"
    if [ -n "$BACKUP_SRC" ]; then
        echo -e "${YELLOW}    Haqiqiy ishga tushirishda zaxira manbasi: $BACKUP_SRC -> $BACKUP_BIN${NC}"
    fi
    if [ ! -f "$KNOWN_GOOD" ] && [ "$BACKUP_FROM_PROC" = "true" ]; then
        echo -e "${YELLOW}    Birinchi marta: known-good nusxa yaratiladi ($KNOWN_GOOD).${NC}"
    fi
    exit 0
fi

if [ -n "$BACKUP_SRC" ]; then
    echo -e "${BLUE}    Zaxira nusxa yaratilmoqda ($BACKUP_BIN)...${NC}"
    sudo cp "$BACKUP_SRC" "$BACKUP_BIN"
    sudo chmod +x "$BACKUP_BIN"
    if [ "$BACKUP_FROM_PROC" = "true" ] && [ "$(sha_of "$BACKUP_BIN")" != "$(sha_of "/proc/$PID/exe")" ]; then
        echo -e "${RED}❌ Zaxira checksum'i ishlab turgan binary'ga mos kelmadi. Hech narsa o'zgartirilmadi.${NC}"
        sudo rm -f "$BACKUP_BIN"
        exit 1
    fi
    echo -e "${GREEN}✔ Zaxira nusxasi yaratildi (checksum: $(sha_of "$BACKUP_BIN")).${NC}"

    # Bir martalik "known-good" nusxa: faqat ishlab turgan jarayondan olinadi va hech qachon ustidan yozilmaydi
    if [ ! -f "$KNOWN_GOOD" ] && [ "$BACKUP_FROM_PROC" = "true" ]; then
        sudo cp "$BACKUP_BIN" "$KNOWN_GOOD"
        sudo chmod 755 "$KNOWN_GOOD"
        echo -e "${GREEN}✔ Known-good nusxa saqlandi: $KNOWN_GOOD (bu fayl hech qachon ustidan yozilmaydi).${NC}"
    fi

    # Eski zaxira nusxalardan faqat oxirgi 5 tasini qoldirish (known-good ga tegilmaydi)
    ls -1t /usr/local/bin/hikvision-gateway.bak.* 2>/dev/null | tail -n +6 | while read -r old; do
        sudo rm -f "$old"
    done
fi

# 3. Atomik almashtirish
echo -e "${BLUE}--> 3. Yangi binary joyiga ko'chirilmoqda (atomik mv)...${NC}"
install_binary "$NEW_BIN"
sudo rm -f "$NEW_BIN"
echo -e "${GREEN}✔ Yangi binary o'rnatildi.${NC}"

# 4. Servisni qayta ishga tushirish
echo -e "${BLUE}--> 4. systemd xizmati qayta ishga tushirilmoqda (hikvision-isup)...${NC}"
sudo systemctl restart hikvision-isup

# 5. Sog'ligini tekshirish (Health check)
echo -e "${BLUE}--> 5. Gateway sog'lig'i tekshirilmoqda (10 soniya ichida)...${NC}"

# Token 1-qadamdan oldin aniqlangan (GW_TOKEN, HEADER_FLAG)
HEALTH_OK=false
if wait_for_health; then
    HEALTH_OK=true
fi

if [ "$HEALTH_OK" = "true" ]; then
    echo -e "\n${GREEN}✔ Gateway muvaffaqiyatli ishga tushdi va /health javob berdi!${NC}"
    
    # Ulangan qurilmalar ro'yxatini chiqarish
    echo -e "${BLUE}--> Ulangan qurilmalar (/api/devices):${NC}"
    curl -fsS "${HEADER_FLAG[@]}" http://127.0.0.1:7661/api/devices 2>/dev/null || echo -e "${YELLOW}(Qurilmalar ro'yxati olinmadi)${NC}"
    echo ""
else
    echo -e "\n${RED}❌ Health check muvaffaqiyatsiz tugadi (/health javob bermadi)!${NC}"

    # Rollback ichida xato yuz bersa ham skript yarim yo'lda to'xtab qolmasligi kerak
    set +e
    if [ -f "$BACKUP_BIN" ]; then
        echo -e "${YELLOW}⚠️ Oldingi binary tiklanmoqda ($BACKUP_BIN -> $TARGET_BIN)...${NC}"
        if install_binary "$BACKUP_BIN"; then
            sudo systemctl restart hikvision-isup
            echo -e "${YELLOW}Rollback: servis qayta ishga tushirildi, health check qayta tekshirilmoqda...${NC}"
            if wait_for_health; then
                echo -e "${GREEN}✔ Rollback OK: oldingi binary tiklandi va /health javob beryapti.${NC}"
            else
                echo -e "${RED}❌ ROLLBACK HAM MUVAFFAQIYATSIZ — qo'lda aralashuv kerak!${NC}"
                sudo systemctl status hikvision-isup --no-pager -n 30
            fi
        else
            echo -e "${RED}❌ ROLLBACK HAM MUVAFFAQIYATSIZ — zaxira binary o'rnatilmadi, qo'lda aralashuv kerak!${NC}"
            sudo systemctl status hikvision-isup --no-pager -n 30
        fi
    else
        echo -e "${RED}❌ Zaxira binary mavjud emas ($BACKUP_BIN), rollback imkonsiz — qo'lda aralashuv kerak!${NC}"
        sudo systemctl status hikvision-isup --no-pager -n 30
    fi
    exit 1
fi

EOF

echo -e "${GREEN}======================================================${NC}"
if [ "$DRY_RUN" = "true" ]; then
    echo -e "${GREEN}✔ Dry-run yakunlandi: kompilyatsiya o'tdi, hech narsa o'zgartirilmadi.${NC}"
else
    echo -e "${GREEN}🎉 Gateway yangilanishi muvaffaqiyatli yakunlandi!${NC}"
fi
echo -e "${GREEN}======================================================${NC}"
