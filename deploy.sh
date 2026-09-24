#!/usr/bin/env bash

# ==============================================================================
# PayDay Panel - Production Deployment Script
# ==============================================================================

set -e

# Server configuration
SERVER_USER="younine"
SERVER_HOST="193.180.213.188"
SERVER_PATH="/var/www/panel_payday_usr/data/www/panel.payday.uz"
PHP_BIN="/opt/php82/bin/php"
BRANCH="main"

# Text styles
GREEN="\033[0;32m"
BLUE="\033[0;34m"
YELLOW="\033[1;33m"
RED="\033[0;31m"
NC="\033[0m" # No Color

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}🚀 PayDay Panel - Deployment boshlanmoqda...${NC}"
echo -e "${BLUE}======================================================${NC}"

# 1. Local Git status check and push
if [[ -n $(git status -s) ]]; then
    COMMIT_MSG="${1:-Deploy updates $(date +'%Y-%m-%d %H:%M:%S')}"
    echo -e "${YELLOW}📦 Mahalliy o'zgarishlar Git-ga yuklanmoqda...${NC}"
    git add .
    git commit -m "$COMMIT_MSG"
    echo -e "${GREEN}✔ Commit qilindi: $COMMIT_MSG${NC}"
fi

echo -e "${YELLOW}⬆️  GitHub ga push qilinmoqda ($BRANCH)...${NC}"
git push origin "$BRANCH"
echo -e "${GREEN}✔ GitHub-ga muvaffaqiyatli yuklandi.${NC}"

# 2. Server Deployment via SSH
echo -e "\n${YELLOW}🌐 Serverga ulanish va yangilash ($SERVER_HOST)...${NC}"

ssh "$SERVER_USER@$SERVER_HOST" bash -s <<EOF
set -e

echo -e "${BLUE}--> Serverdagi papkaga o'tilmoqda: $SERVER_PATH${NC}"
cd $SERVER_PATH

echo -e "${BLUE}--> Eng so'nggi kodlar tortib olinmoqda (git pull)...${NC}"
sudo git checkout $BRANCH
sudo git pull origin $BRANCH

echo -e "${BLUE}--> Composer qaramliklari tekshirilmoqda...${NC}"
if [ -f "composer.json" ]; then
    sudo composer install --no-interaction --prefer-dist --optimize-autoloader || true
fi

echo -e "${BLUE}--> Frontend aktivlari build qilinmoqda (npm run build)...${NC}"
if [ -f "package.json" ]; then
    sudo npm run build || true
fi

echo -e "${BLUE}--> Ma'lumotlar bazasi migratsiyalari ishga tushirilmoqda...${NC}"
sudo $PHP_BIN artisan migrate --force

echo -e "${BLUE}--> Tizim keshlari tozalanmoqda va optimallashmoqda...${NC}"
sudo $PHP_BIN artisan optimize:clear
sudo $PHP_BIN artisan config:cache || true
sudo $PHP_BIN artisan route:cache || true

echo -e "${BLUE}--> Ruxsatlar (permissions) sozlanmoqda...${NC}"
sudo chown -R panel_payday_usr:panel_payday_usr $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache
sudo chmod -R 775 $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache

echo -e "${BLUE}--> Queue workerlar va Supervisor qayta ishga tushirilmoqda...${NC}"
sudo $PHP_BIN artisan queue:restart || true
sudo supervisorctl reread || true
sudo supervisorctl update || true
sudo supervisorctl restart payday-worker:* || true

# Agar gateway kompilyatsiya kutubxonalari mavjud bo'lsa, qayta kompilyatsiya va restart qilish
if [ -d "/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include" ]; then
    echo -e "${BLUE}--> Hikvision Gateway tekshirilmoqda va qayta kompilyatsiya qilinmoqda...${NC}"
    sudo g++ -std=c++17 -O2 $SERVER_PATH/gateway_main.cpp -o /usr/local/bin/hikvision-gateway \
        -I/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/include \
        -I/opt/hikvision-gateway/include \
        -L/opt/ip-camera-ehome-server/thirdparty/HCISUPSDK/linux64/lib \
        -lHCISUPCMS -lHCISUPAlarm -lHCISUPSS -lpthread -ldl || true
    sudo chmod +x /usr/local/bin/hikvision-gateway || true
    sudo supervisorctl restart hikvision-gateway || true
fi

echo -e "${GREEN}✔ Serverdagi yangilanish muvaffaqiyatli yakunlandi!${NC}"
EOF

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}🎉 Deployment muvaffaqiyatli yakunlandi!${NC}"
echo -e "${GREEN}🌐 Sayt: https://panel.payday.uz${NC}"
echo -e "${GREEN}======================================================${NC}"
