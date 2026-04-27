#!/bin/bash

# Warna untuk output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🚀 Memulai proses optimasi dan deployment...${NC}\n"

# Function untuk logging
log() {
    echo -e "${GREEN}✓ $1${NC}"
}

error() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

# Check if running in correct directory
if [ ! -f "package.json" ]; then
    error "Script harus dijalankan dari direktori frontend!"
fi

# 1. Install dependencies
echo -e "${YELLOW}📦 Menginstall dependencies...${NC}"
npm install || error "Gagal menginstall dependencies"
log "Dependencies berhasil diinstall"

# 2. Run tests
echo -e "\n${YELLOW}🧪 Menjalankan tests...${NC}"
npm run test -- --watchAll=false || error "Tests gagal"
log "Semua tests berhasil"

# 3. Analyze bundle
echo -e "\n${YELLOW}📊 Menganalisis bundle...${NC}"
npm run analyze || error "Analisis bundle gagal"
log "Analisis bundle selesai"

# 4. Check performance
echo -e "\n${YELLOW}⚡ Mengecek performa...${NC}"
npm run check:performance || error "Pengecekan performa gagal"
log "Pengecekan performa selesai"

# 5. Optimize images
echo -e "\n${YELLOW}🖼️ Mengoptimasi gambar...${NC}"
if command -v imagemin &> /dev/null; then
    find public/images -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" \) -exec imagemin {} --out-dir=public/images/optimized \;
    log "Optimasi gambar selesai"
else
    echo -e "${YELLOW}⚠️ imagemin tidak ditemukan, melewati optimasi gambar${NC}"
fi

# 6. Build production
echo -e "\n${YELLOW}🏗️ Membuild untuk production...${NC}"
npm run build:prod || error "Build gagal"
log "Build production berhasil"

# 7. Run lighthouse audit
echo -e "\n${YELLOW}🔍 Menjalankan lighthouse audit...${NC}"
npm run lighthouse || error "Lighthouse audit gagal"
log "Lighthouse audit selesai"

# 8. Check bundle size
echo -e "\n${YELLOW}📏 Mengecek ukuran bundle...${NC}"
MAX_BUNDLE_SIZE=2000000 # 2MB
BUNDLE_SIZE=$(find build/static/js -name "*.js" -type f -exec ls -l {} \; | awk '{sum += $5} END {print sum}')

if [ $BUNDLE_SIZE -gt $MAX_BUNDLE_SIZE ]; then
    error "Bundle size ($BUNDLE_SIZE bytes) melebihi batas ($MAX_BUNDLE_SIZE bytes)"
fi
log "Bundle size dalam batas yang diizinkan"

# 9. Generate service worker
echo -e "\n${YELLOW}🔧 Generating service worker...${NC}"
npm run generate-sw || error "Gagal generate service worker"
log "Service worker berhasil digenerate"

# 10. Validate manifest
echo -e "\n${YELLOW}📱 Validating PWA manifest...${NC}"
if [ -f "public/manifest.json" ]; then
    if ! jq empty public/manifest.json 2>/dev/null; then
        error "manifest.json tidak valid"
    fi
    log "PWA manifest valid"
else
    error "manifest.json tidak ditemukan"
fi

# 11. Check for security vulnerabilities
echo -e "\n${YELLOW}🔒 Mengecek vulnerabilities...${NC}"
npm audit || echo -e "${YELLOW}⚠️ Ditemukan beberapa vulnerabilities, mohon review${NC}"
log "Security check selesai"

# 12. Clean up
echo -e "\n${YELLOW}🧹 Membersihkan temporary files...${NC}"
rm -rf .cache
rm -rf coverage
log "Cleanup selesai"

# 13. Create deployment archive
echo -e "\n${YELLOW}📦 Membuat archive untuk deployment...${NC}"
VERSION=$(node -p "require('./package.json').version")
ARCHIVE_NAME="siap-absensi-frontend-v${VERSION}.zip"
zip -r "$ARCHIVE_NAME" build/* || error "Gagal membuat archive"
log "Archive deployment berhasil dibuat: $ARCHIVE_NAME"

# 14. Generate deployment report
echo -e "\n${YELLOW}📄 Membuat laporan deployment...${NC}"
REPORT_FILE="deployment-report-$(date +%Y%m%d-%H%M%S).txt"
{
    echo "SIAP Absensi Frontend Deployment Report"
    echo "======================================="
    echo "Version: $VERSION"
    echo "Date: $(date)"
    echo "Bundle Size: $(numfmt --to=iec-i --suffix=B $BUNDLE_SIZE)"
    echo "Lighthouse Scores:"
    cat lighthouse-report.json 2>/dev/null || echo "Lighthouse report not found"
    echo "======================================="
} > "$REPORT_FILE"
log "Laporan deployment berhasil dibuat: $REPORT_FILE"

# Final success message
echo -e "\n${GREEN}✨ Optimasi dan persiapan deployment selesai!${NC}"
echo -e "Archive: ${YELLOW}$ARCHIVE_NAME${NC}"
echo -e "Report: ${YELLOW}$REPORT_FILE${NC}"
echo -e "\n${YELLOW}Langkah selanjutnya:${NC}"
echo "1. Review laporan deployment"
echo "2. Upload archive ke server"
echo "3. Jalankan script deployment di server"
echo -e "\n${GREEN}Semua proses selesai!${NC}"
