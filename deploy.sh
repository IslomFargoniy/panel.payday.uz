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

# 1. Local Git status check
if [[ -n $(git status -s) ]]; then
    echo -e "${RED}⚠️  Diqqat: Mahalliy repositoryda saqlanmagan o'zgarishlar mavjud!${NC}"
    echo -e "${RED}Deploy qilishdan oldin o'zgarishlarni commit qiling yoki stash qiling.${NC}"
    git status -s
    exit 1
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

# Keraksiz AppleDouble (._*) fayllarni tozalash
find . -name "._*" -delete 2>/dev/null || true

PREV=\$(sudo git rev-parse HEAD)
echo -e "${BLUE}--> Joriy commit (rollback uchun saqlandi): \$PREV${NC}"

rollback() {
    echo -e "${RED}❌ Xatolik yuz berdi! Avvalgi holatga qaytarilmoqda (\$PREV)...${NC}"
    sudo git reset --hard "\$PREV"
    if [ -f "composer.json" ]; then
        sudo composer install --no-interaction --prefer-dist --optimize-autoloader || true
    fi
    sudo -u panel_payday_usr $PHP_BIN artisan optimize:clear || true
    sudo -u panel_payday_usr $PHP_BIN artisan config:cache || true
    sudo -u panel_payday_usr $PHP_BIN artisan route:cache || true
    sudo chown -R panel_payday_usr:panel_payday_usr $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache || true
    sudo chmod -R 775 $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache || true
    echo -e "${YELLOW}⚠️ Rollback yakunlandi.${NC}"
    echo -e "${YELLOW}⚠️ Migratsiyalar orqaga qaytarilmadi — 'php artisan migrate:status' ni tekshiring.${NC}"
}
trap rollback ERR

echo -e "${BLUE}--> Eng so'nggi kodlar tortib olinmoqda (git pull)...${NC}"
sudo git checkout $BRANCH
sudo git pull origin $BRANCH

echo -e "${BLUE}--> Composer qaramliklari tekshirilmoqda...${NC}"
if [ -f "composer.json" ]; then
    sudo composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo -e "${BLUE}--> Frontend aktivlari build qilinmoqda (npm ci && npm run build)...${NC}"
if [ -f "package.json" ]; then
    sudo npm ci
    sudo npm run build
fi

echo -e "${BLUE}--> Ma'lumotlar bazasi migratsiyalari ishga tushirilmoqda...${NC}"
sudo -u panel_payday_usr $PHP_BIN artisan migrate --force

echo -e "${BLUE}--> Tizim keshlari tozalanmoqda va optimallashmoqda...${NC}"
sudo -u panel_payday_usr $PHP_BIN artisan optimize:clear
sudo -u panel_payday_usr $PHP_BIN artisan config:cache
sudo -u panel_payday_usr $PHP_BIN artisan route:cache

# Muvaffaqiyatli yakunlanganda rollback tuzog'ini bekor qilish
trap - ERR

echo -e "${BLUE}--> Ruxsatlar (permissions) sozlanmoqda...${NC}"
sudo chown -R panel_payday_usr:panel_payday_usr $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache
sudo chmod -R 775 $SERVER_PATH/storage $SERVER_PATH/bootstrap/cache

echo -e "${BLUE}--> Queue workerlar va Supervisor qayta ishga tushirilmoqda...${NC}"
sudo -u panel_payday_usr $PHP_BIN artisan queue:restart || true
sudo supervisorctl reread || true
sudo supervisorctl update || true
sudo supervisorctl restart payday-worker:* || true



echo -e "${GREEN}✔ Serverdagi yangilanish muvaffaqiyatli yakunlandi!${NC}"
EOF

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}🎉 Deployment muvaffaqiyatli yakunlandi!${NC}"
echo -e "${GREEN}🌐 Sayt: https://panel.payday.uz${NC}"
echo -e "${GREEN}======================================================${NC}"
