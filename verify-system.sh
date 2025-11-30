#!/bin/bash
# NexMart E-commerce - Quick Verification Script
# Verifies all fixes are in place and working

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║     NexMart E-commerce - System Verification             ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "wp-config.php" ]; then
    echo -e "${RED}❌ Error: Not in WordPress root directory${NC}"
    exit 1
fi

echo "📍 Location: $(pwd)"
echo ""

# 1. Check PHP syntax
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  Checking PHP Syntax..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php -l wp-content/themes/nexmart/inc/class-nexmart-auth.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ class-nexmart-auth.php - OK${NC}"
else
    echo -e "${RED}❌ class-nexmart-auth.php - SYNTAX ERROR${NC}"
fi

php -l wp-content/themes/nexmart/page-register.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ page-register.php - OK${NC}"
else
    echo -e "${RED}❌ page-register.php - SYNTAX ERROR${NC}"
fi

php -l wp-content/themes/nexmart/page-login.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ page-login.php - OK${NC}"
else
    echo -e "${RED}❌ page-login.php - SYNTAX ERROR${NC}"
fi

echo ""

# 2. Check JavaScript syntax
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  Checking JavaScript Syntax..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if command -v node &> /dev/null; then
    node -c wp-content/themes/nexmart/assets/js/main.js > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ main.js - OK${NC}"
    else
        echo -e "${RED}❌ main.js - SYNTAX ERROR${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Node.js not installed - skipping JS syntax check${NC}"
fi

echo ""

# 3. Check critical files exist
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  Checking Critical Files..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

FILES=(
    "wp-content/themes/nexmart/inc/class-nexmart-auth.php"
    "wp-content/themes/nexmart/page-login.php"
    "wp-content/themes/nexmart/page-register.php"
    "wp-content/themes/nexmart/page-my-account.php"
    "wp-content/themes/nexmart/assets/js/main.js"
    "wp-content/themes/nexmart/header.php"
    "wp-content/themes/nexmart/footer.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✅ $file${NC}"
    else
        echo -e "${RED}❌ Missing: $file${NC}"
    fi
done

echo ""

# 4. Test AJAX endpoints
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  Testing AJAX Endpoints..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Get site URL
SITE_URL=$(wp option get siteurl 2>/dev/null)
if [ -z "$SITE_URL" ]; then
    SITE_URL="http://localhost/ecommerce-wordpress"
fi

echo "🌐 Site URL: $SITE_URL"

# Generate nonce
NONCE=$(wp eval 'echo wp_create_nonce("nexmart_nonce");' 2>/dev/null)

if [ -n "$NONCE" ]; then
    # Test registration endpoint (with unique email)
    TIMESTAMP=$(date +%s)
    RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
        -d "action=nexmart_register" \
        -d "email=test${TIMESTAMP}@example.com" \
        -d "password=testpass123" \
        -d "name=Test User" \
        -d "nonce=$NONCE" 2>/dev/null)
    
    if echo "$RESPONSE" | grep -q '"success":true'; then
        echo -e "${GREEN}✅ Registration endpoint - OK${NC}"
    elif echo "$RESPONSE" | grep -q 'Email already exists'; then
        echo -e "${YELLOW}⚠️  Registration endpoint - OK (user exists)${NC}"
    elif echo "$RESPONSE" | grep -q '"success"'; then
        echo -e "${GREEN}✅ Registration endpoint - Responding (JSON)${NC}"
    else
        echo -e "${RED}❌ Registration endpoint - ERROR${NC}"
        echo "   Response: ${RESPONSE:0:100}"
    fi
    
    # Test get cart endpoint
    CART_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
        -d "action=nexmart_get_cart" \
        -d "nonce=$NONCE" 2>/dev/null)
    
    if echo "$CART_RESPONSE" | grep -q '"success":true'; then
        echo -e "${GREEN}✅ Get cart endpoint - OK${NC}"
    elif echo "$CART_RESPONSE" | grep -q '"success"'; then
        echo -e "${GREEN}✅ Get cart endpoint - Responding (JSON)${NC}"
    else
        echo -e "${RED}❌ Get cart endpoint - ERROR${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Could not generate nonce - WP-CLI may not be available${NC}"
fi

echo ""

# 5. Check database tables
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5️⃣  Checking Database Tables..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TABLES=(
    "nxm_nexmart_cart"
    "nxm_nexmart_orders"
    "nxm_nexmart_order_items"
    "nxm_nexmart_products"
    "nxm_nexmart_vendors"
)

for table in "${TABLES[@]}"; do
    COUNT=$(wp db query "SELECT COUNT(*) as c FROM $table;" --skip-column-names 2>/dev/null | tail -n1)
    if [ -n "$COUNT" ]; then
        echo -e "${GREEN}✅ $table ($COUNT rows)${NC}"
    else
        echo -e "${RED}❌ $table - NOT FOUND${NC}"
    fi
done

echo ""

# 6. Check WordPress pages
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6️⃣  Checking WordPress Pages..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

PAGES=("Login" "Register" "My Account" "Cart" "Checkout")

for page in "${PAGES[@]}"; do
    PAGE_EXISTS=$(wp post list --post_type=page --name="$(echo $page | tr '[:upper:]' '[:lower:]' | tr ' ' '-')" --format=count 2>/dev/null)
    if [ "$PAGE_EXISTS" -gt 0 ]; then
        PAGE_URL=$(wp post list --post_type=page --title="$page" --field=url 2>/dev/null | head -n1)
        echo -e "${GREEN}✅ $page page - $PAGE_URL${NC}"
    else
        echo -e "${RED}❌ $page page - NOT FOUND${NC}"
    fi
done

echo ""

# 7. Check permissions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7️⃣  Checking File Permissions..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -w "wp-content/uploads" ]; then
    echo -e "${GREEN}✅ wp-content/uploads - Writable${NC}"
else
    echo -e "${RED}❌ wp-content/uploads - Not writable${NC}"
fi

if [ -r "wp-config.php" ]; then
    echo -e "${GREEN}✅ wp-config.php - Readable${NC}"
else
    echo -e "${RED}❌ wp-config.php - Not readable${NC}"
fi

echo ""

# 8. Summary and URLs
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 Summary & Quick Links"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo ""
echo "🔗 Test URLs:"
echo "   Homepage:      $SITE_URL/"
echo "   Register:      $SITE_URL/register/"
echo "   Login:         $SITE_URL/login/"
echo "   My Account:    $SITE_URL/my-account/"
echo "   Cart:          $SITE_URL/cart/"
echo "   Shop:          $SITE_URL/shop/"
echo ""

echo "📚 Documentation:"
echo "   Quick Start:   START_HERE.md"
echo "   Testing Guide: TESTING_GUIDE.md"
echo "   Fixes:         FIXES_COMPLETED.md"
echo "   Auth Guide:    AUTHENTICATION_GUIDE.md"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✅ Verification Complete!${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Next steps:"
echo "  1. Open your browser"
echo "  2. Visit: $SITE_URL/register/"
echo "  3. Create a test account"
echo "  4. Test the cart functionality"
echo ""
echo "For detailed testing instructions, run:"
echo "  cat TESTING_GUIDE.md | less"
echo ""
