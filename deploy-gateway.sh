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
for arg in "$@"; do
    case "$arg" in
        --setup-config)
            SETUP_CONFIG=true
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
ssh "$SERVER_USER@$SERVER_HOST" bash -s -- "$SETUP_CONFIG" <<'EOF'
set -e

SETUP_CONFIG="$1"
SERVER_PATH="/var/www/panel_payday_usr/data/www/panel.payday.uz"
CONFIG_DIR="/etc/hikvision-gateway"
CONFIG_FILE="$CONFIG_DIR/gateway_config.json"
TARGET_BIN="/usr/local/bin/hikvision-gateway"
NEW_BIN="/usr/local/bin/hikvision-gateway.new"
TIMESTAMP=$(date +%Y%m%d%H%M%S)
BACKUP_BIN="/usr/local/bin/hikvision-gateway.bak.$TIMESTAMP"

GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
NC="\033[0m"

# 0. --setup-config tekshiruvi
if [ "$SETUP_CONFIG" = "true" ]; then
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
if [ -f "$TARGET_BIN" ]; then
    echo -e "${BLUE}--> 2. Joriy binary zaxira qilinmoqda ($BACKUP_BIN)...${NC}"
    sudo cp "$TARGET_BIN" "$BACKUP_BIN"
    echo -e "${GREEN}✔ Zaxira nusxasi yaratildi.${NC}"
fi

# 3. Atomik almashtirish
echo -e "${BLUE}--> 3. Yangi binary joyiga ko'chirilmoqda (atomik mv)...${NC}"
sudo mv "$NEW_BIN" "$TARGET_BIN"
echo -e "${GREEN}✔ Yangi binary o'rnatildi.${NC}"

# 4. Servisni qayta ishga tushirish
echo -e "${BLUE}--> 4. systemd xizmati qayta ishga tushirilmoqda (hikvision-isup)...${NC}"
sudo systemctl restart hikvision-isup

# 5. Sog'ligini tekshirish (Health check)
echo -e "${BLUE}--> 5. Gateway sog'lig'i tekshirilmoqda (10 soniya ichida)...${NC}"

# Tokenni aniqlash
GW_TOKEN=""
if [ -f "$CONFIG_FILE" ]; then
    GW_TOKEN=$(sudo jq -r '.gateway_token // empty' "$CONFIG_FILE" 2>/dev/null || true)
fi

HEADER_FLAG=()
if [ -n "$GW_TOKEN" ]; then
    HEADER_FLAG=(-H "X-Gateway-Token: $GW_TOKEN")
fi

HEALTH_OK=false
for i in {1..10}; do
    echo -n "Kutilmoqda ($i/10s)... "
    if curl -fsS "${HEADER_FLAG[@]}" http://127.0.0.1:7661/health >/dev/null 2>&1; then
        echo -e "${GREEN}OK!${NC}"
        HEALTH_OK=true
        break
    fi
    sleep 1
done

if [ "$HEALTH_OK" = "true" ]; then
    echo -e "\n${GREEN}✔ Gateway muvaffaqiyatli ishga tushdi va /health javob berdi!${NC}"
    
    # Ulangan qurilmalar ro'yxatini chiqarish
    echo -e "${BLUE}--> Ulangan qurilmalar (/api/devices):${NC}"
    curl -fsS "${HEADER_FLAG[@]}" http://127.0.0.1:7661/api/devices 2>/dev/null || echo -e "${YELLOW}(Qurilmalar ro'yxati olinmadi)${NC}"
    echo ""
else
    echo -e "\n${RED}❌ Health check muvaffaqiyatsiz tugadi (/health javob bermadi)!${NC}"
    if [ -f "$BACKUP_BIN" ]; then
        echo -e "${YELLOW}⚠️ Oldingi binary tiklanmoqda ($BACKUP_BIN -> $TARGET_BIN)...${NC}"
        sudo cp "$BACKUP_BIN" "$TARGET_BIN"
        sudo systemctl restart hikvision-isup
        echo -e "${YELLOW}Oldingi holatga qaytarildi va servis restart qilindi.${NC}"
    fi
    exit 1
fi

EOF

echo -e "${GREEN}======================================================${NC}"
echo -e "${GREEN}🎉 Gateway yangilanishi muvaffaqiyatli yakunlandi!${NC}"
echo -e "${GREEN}======================================================${NC}"
