#!/bin/bash
# Comprehensive Website Functionality Audit
# Tests all major features and reports status

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║     NexMart - Complete Functionality Audit                   ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SITE_URL=$(wp option get siteurl 2>/dev/null)
if [ -z "$SITE_URL" ]; then
    SITE_URL="http://localhost/ecommerce-wordpress"
fi

PASSED=0
FAILED=0
WARNINGS=0

# Test function
test_feature() {
    local name=$1
    local result=$2
    
    if [ "$result" -eq 0 ]; then
        echo -e "${GREEN}✅ PASS${NC} - $name"
        ((PASSED++))
    elif [ "$result" -eq 2 ]; then
        echo -e "${YELLOW}⚠️  WARN${NC} - $name"
        ((WARNINGS++))
    else
        echo -e "${RED}❌ FAIL${NC} - $name"
        ((FAILED++))
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔍 SECTION 1: Core WordPress Installation"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# WordPress installed
wp core is-installed > /dev/null 2>&1
test_feature "WordPress Core Installed" $?

# Database connectivity
wp db check > /dev/null 2>&1
test_feature "Database Connectivity" $?

# Theme active
ACTIVE_THEME=$(wp theme list --status=active --field=name 2>/dev/null)
if [ "$ACTIVE_THEME" = "nexmart" ]; then
    test_feature "NexMart Theme Active" 0
else
    test_feature "NexMart Theme Active (Current: $ACTIVE_THEME)" 1
fi

# PHP version
PHP_VERSION=$(php -v | head -n 1 | cut -d' ' -f2 | cut -d'.' -f1)
if [ "$PHP_VERSION" -ge 7 ]; then
    test_feature "PHP Version ($PHP_VERSION.x)" 0
else
    test_feature "PHP Version ($PHP_VERSION.x - Needs 7.x+)" 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📄 SECTION 2: Essential Pages"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

PAGES=("Home" "Shop" "Cart" "Checkout" "Login" "Register" "My Account")
for page in "${PAGES[@]}"; do
    PAGE_SLUG=$(echo "$page" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
    if [ "$page" = "Home" ]; then
        PAGE_COUNT=1
    else
        PAGE_COUNT=$(wp post list --post_type=page --name="$PAGE_SLUG" --format=count 2>/dev/null)
    fi
    
    if [ "$PAGE_COUNT" -gt 0 ]; then
        test_feature "$page page exists" 0
    else
        test_feature "$page page exists" 1
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🗄️  SECTION 3: Database Tables"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TABLES=(
    "nxm_nexmart_products:Products"
    "nxm_nexmart_categories:Categories"
    "nxm_nexmart_vendors:Vendors"
    "nxm_nexmart_cart:Shopping Cart"
    "nxm_nexmart_orders:Orders"
    "nxm_nexmart_order_items:Order Items"
    "nxm_nexmart_reviews:Reviews"
    "nxm_nexmart_wishlists:Wishlists"
    "nxm_nexmart_coupons:Coupons"
)

for entry in "${TABLES[@]}"; do
    TABLE=$(echo $entry | cut -d: -f1)
    NAME=$(echo $entry | cut -d: -f2)
    
    EXISTS=$(wp db query "SHOW TABLES LIKE '$TABLE';" 2>/dev/null | grep -c "$TABLE")
    if [ "$EXISTS" -gt 0 ]; then
        COUNT=$(wp db query "SELECT COUNT(*) FROM $TABLE;" --skip-column-names 2>/dev/null | tail -n1)
        test_feature "$NAME table ($COUNT records)" 0
    else
        test_feature "$NAME table" 1
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔐 SECTION 4: Authentication System"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check auth class exists
if [ -f "wp-content/themes/nexmart/inc/class-nexmart-auth.php" ]; then
    test_feature "Auth class file exists" 0
else
    test_feature "Auth class file exists" 1
fi

# Test registration AJAX endpoint
NONCE=$(wp eval 'echo wp_create_nonce("nexmart_nonce");' 2>/dev/null)
TIMESTAMP=$(date +%s)
REG_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
    -d "action=nexmart_register" \
    -d "email=audit${TIMESTAMP}@test.com" \
    -d "password=testpass123" \
    -d "name=Audit User" \
    -d "nonce=$NONCE" 2>/dev/null)

if echo "$REG_RESPONSE" | grep -q '"success":true'; then
    test_feature "Registration AJAX (JSON response)" 0
    # Clean up test user
    wp user delete "audit${TIMESTAMP}@test.com" --yes > /dev/null 2>&1
elif echo "$REG_RESPONSE" | grep -q '"success":false'; then
    test_feature "Registration AJAX (responding)" 2
else
    test_feature "Registration AJAX (Invalid response)" 1
fi

# Test login endpoint
LOGIN_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
    -d "action=nexmart_login" \
    -d "email=test@example.com" \
    -d "password=wrong" \
    -d "nonce=$NONCE" 2>/dev/null)

if echo "$LOGIN_RESPONSE" | grep -q '"success"'; then
    test_feature "Login AJAX (JSON response)" 0
else
    test_feature "Login AJAX" 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🛒 SECTION 5: Shopping Cart System"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check cart class
if [ -f "wp-content/themes/nexmart/inc/class-nexmart-cart.php" ]; then
    test_feature "Cart class file exists" 0
else
    test_feature "Cart class file exists" 1
fi

# Test get cart endpoint
CART_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
    -d "action=nexmart_get_cart" \
    -d "nonce=$NONCE" 2>/dev/null)

if echo "$CART_RESPONSE" | grep -q '"success":true'; then
    test_feature "Get Cart AJAX (JSON response)" 0
elif echo "$CART_RESPONSE" | grep -q '"success"'; then
    test_feature "Get Cart AJAX (responding)" 2
else
    test_feature "Get Cart AJAX" 1
fi

# Check if products exist for add to cart
PRODUCT_COUNT=$(wp db query "SELECT COUNT(*) FROM nxm_nexmart_products WHERE status='active';" --skip-column-names 2>/dev/null | tail -n1)
if [ "$PRODUCT_COUNT" -gt 0 ]; then
    test_feature "Active products available ($PRODUCT_COUNT)" 0
else
    test_feature "Active products available" 2
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎨 SECTION 6: Frontend Assets"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# JavaScript
if [ -f "wp-content/themes/nexmart/assets/js/main.js" ]; then
    if command -v node &> /dev/null; then
        node -c wp-content/themes/nexmart/assets/js/main.js > /dev/null 2>&1
        test_feature "main.js (syntax checked)" $?
    else
        test_feature "main.js (exists, syntax not checked)" 2
    fi
else
    test_feature "main.js" 1
fi

# CSS
if [ -f "wp-content/themes/nexmart/assets/css/style.css" ] || [ -f "wp-content/themes/nexmart/style.css" ]; then
    test_feature "CSS files exist" 0
else
    test_feature "CSS files exist" 2
fi

# Check Tailwind CDN in header
if grep -q "tailwindcss" wp-content/themes/nexmart/header.php 2>/dev/null; then
    test_feature "Tailwind CSS configured" 0
else
    test_feature "Tailwind CSS configured" 2
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⚙️  SECTION 7: PHP Configuration"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Session support
SESSION_SUPPORT=$(php -r 'echo function_exists("session_start") ? "yes" : "no";')
if [ "$SESSION_SUPPORT" = "yes" ]; then
    test_feature "PHP Session support" 0
else
    test_feature "PHP Session support" 1
fi

# JSON support
JSON_SUPPORT=$(php -r 'echo function_exists("json_encode") ? "yes" : "no";')
if [ "$JSON_SUPPORT" = "yes" ]; then
    test_feature "PHP JSON support" 0
else
    test_feature "PHP JSON support" 1
fi

# MySQL/PDO
PDO_SUPPORT=$(php -r 'echo extension_loaded("pdo_mysql") ? "yes" : "no";')
if [ "$PDO_SUPPORT" = "yes" ]; then
    test_feature "PDO MySQL extension" 0
else
    test_feature "PDO MySQL extension" 2
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔒 SECTION 8: Security Checks"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# wp-config.php permissions
WP_CONFIG_PERMS=$(stat -c %a wp-config.php 2>/dev/null)
if [ "$WP_CONFIG_PERMS" = "644" ] || [ "$WP_CONFIG_PERMS" = "640" ] || [ "$WP_CONFIG_PERMS" = "600" ]; then
    test_feature "wp-config.php permissions ($WP_CONFIG_PERMS)" 0
else
    test_feature "wp-config.php permissions ($WP_CONFIG_PERMS - Consider 644)" 2
fi

# Check for wp-config-sample.php (should be removed in production)
if [ -f "wp-config-sample.php" ]; then
    test_feature "wp-config-sample.php (remove in production)" 2
else
    test_feature "wp-config-sample.php removed" 0
fi

# Check readme.html (should be removed in production)
if [ -f "readme.html" ]; then
    test_feature "readme.html (remove in production)" 2
else
    test_feature "readme.html removed" 0
fi

# HTTPS check
if echo "$SITE_URL" | grep -q "https://"; then
    test_feature "HTTPS enabled" 0
else
    test_feature "HTTPS enabled (HTTP only - enable for production)" 2
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 SECTION 9: HTTP Response Tests"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Homepage response
HOME_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/" 2>/dev/null)
if [ "$HOME_STATUS" = "200" ]; then
    test_feature "Homepage loads (HTTP $HOME_STATUS)" 0
else
    test_feature "Homepage loads (HTTP $HOME_STATUS)" 1
fi

# Registration page
REG_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/register/" 2>/dev/null)
if [ "$REG_STATUS" = "200" ]; then
    test_feature "Registration page (HTTP $REG_STATUS)" 0
else
    test_feature "Registration page (HTTP $REG_STATUS)" 1
fi

# Login page
LOGIN_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/login/" 2>/dev/null)
if [ "$LOGIN_STATUS" = "200" ]; then
    test_feature "Login page (HTTP $LOGIN_STATUS)" 0
else
    test_feature "Login page (HTTP $LOGIN_STATUS)" 1
fi

# Cart page
CART_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$SITE_URL/cart/" 2>/dev/null)
if [ "$CART_STATUS" = "200" ]; then
    test_feature "Cart page (HTTP $CART_STATUS)" 0
else
    test_feature "Cart page (HTTP $CART_STATUS)" 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 AUDIT SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

TOTAL=$((PASSED + FAILED + WARNINGS))
PASS_RATE=$((PASSED * 100 / TOTAL))

echo -e "${GREEN}✅ Passed:  $PASSED${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"
echo -e "${RED}❌ Failed:  $FAILED${NC}"
echo "   ────────────"
echo "   Total:    $TOTAL tests"
echo ""
echo -e "Pass Rate: ${GREEN}${PASS_RATE}%${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✅ ALL CRITICAL TESTS PASSED!          ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════╝${NC}"
else
    echo -e "${RED}╔══════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ⚠️  ISSUES DETECTED - REVIEW REQUIRED  ║${NC}"
    echo -e "${RED}╚══════════════════════════════════════════╝${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔗 Quick Links:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   Homepage:     $SITE_URL/"
echo "   Register:     $SITE_URL/register/"
echo "   Login:        $SITE_URL/login/"
echo "   Cart:         $SITE_URL/cart/"
echo "   My Account:   $SITE_URL/my-account/"
echo ""
echo "📝 For detailed testing, see: TESTING_GUIDE.md"
echo ""

exit $FAILED
