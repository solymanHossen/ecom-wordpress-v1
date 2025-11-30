#!/bin/bash
# Vendor System Complete Testing Workflow
# Tests all vendor functionality end-to-end

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║        NexMart Vendor System - Complete Workflow Test        ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

SITE_URL="http://localhost/ecommerce-wordpress"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}📋 VENDOR ACCOUNT DETAILS${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}Email:${NC}     vendor@nexmart.com"
echo -e "${GREEN}Password:${NC}  Vendor@123456"
echo -e "${GREEN}Role:${NC}      Vendor"
echo -e "${GREEN}Store:${NC}     TechGadgets Pro"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}1️⃣  Vendor Profile Information${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

wp db query "SELECT 
    v.id AS 'Vendor ID',
    v.store_name AS 'Store Name',
    v.store_slug AS 'Store URL',
    v.status AS 'Status',
    v.commission_rate AS 'Commission %',
    v.total_sales AS 'Total Sales',
    u.user_login AS 'Username',
    u.user_email AS 'Email'
FROM nxm_nexmart_vendors v 
JOIN nxm_users u ON v.user_id = u.ID 
WHERE u.user_email = 'vendor@nexmart.com';" 2>/dev/null

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}2️⃣  Vendor Products${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

wp db query "SELECT 
    p.id AS 'Product ID',
    p.name AS 'Product Name',
    CONCAT('$', p.price) AS 'Price',
    CONCAT('$', p.sale_price) AS 'Sale Price',
    p.stock_quantity AS 'Stock',
    p.status AS 'Status',
    p.sales_count AS 'Sales'
FROM nxm_nexmart_products p 
JOIN nxm_nexmart_vendors v ON p.vendor_id = v.id 
WHERE v.store_slug = 'techgadgets-pro';" 2>/dev/null

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}3️⃣  Testing Vendor Login${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

NONCE=$(wp eval 'echo wp_create_nonce("nexmart_nonce");' 2>/dev/null)
LOGIN_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -d "action=nexmart_login" \
  -d "email=vendor@nexmart.com" \
  -d "password=Vendor@123456" \
  -d "nonce=$NONCE" \
  -c /tmp/vendor_cookies.txt 2>/dev/null)

if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✅ Vendor login successful${NC}"
    USER_DATA=$(echo "$LOGIN_RESPONSE" | python3 -c "import sys, json; data=json.load(sys.stdin); print('User ID:', data['data']['user']['id'], '| Role:', data['data']['user']['role'])" 2>/dev/null)
    echo "   $USER_DATA"
else
    echo -e "${RED}❌ Vendor login failed${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}4️⃣  Vendor Sales & Orders${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

VENDOR_ORDERS=$(wp db query "SELECT 
    COUNT(DISTINCT oi.order_id) AS 'Total Orders',
    SUM(oi.quantity) AS 'Items Sold',
    CONCAT('$', SUM(oi.subtotal)) AS 'Gross Sales',
    CONCAT('$', SUM(oi.subtotal * v.commission_rate / 100)) AS 'Commission',
    CONCAT('$', SUM(oi.subtotal * (100 - v.commission_rate) / 100)) AS 'Vendor Earnings'
FROM nxm_nexmart_order_items oi
JOIN nxm_nexmart_products p ON oi.product_id = p.id
JOIN nxm_nexmart_vendors v ON p.vendor_id = v.id
WHERE v.store_slug = 'techgadgets-pro';" --skip-column-names 2>/dev/null)

if [ -n "$VENDOR_ORDERS" ]; then
    echo "$VENDOR_ORDERS"
else
    echo "No orders yet for this vendor"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}5️⃣  Vendor Store URL${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}Store Page:${NC}    $SITE_URL/vendor/techgadgets-pro"
echo -e "${GREEN}All Products:${NC}  $SITE_URL/shop?vendor=techgadgets-pro"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}6️⃣  Product URLs${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

wp db query "SELECT 
    CONCAT('$SITE_URL/product/', p.slug) AS 'Product URLs'
FROM nxm_nexmart_products p 
JOIN nxm_nexmart_vendors v ON p.vendor_id = v.id 
WHERE v.store_slug = 'techgadgets-pro';" --skip-column-names 2>/dev/null | sed 's/^/   /'

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}📊 TESTING CHECKLIST${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Test these scenarios manually:"
echo ""
echo "1. Login as Vendor:"
echo "   → Go to: $SITE_URL/login"
echo "   → Email: vendor@nexmart.com"
echo "   → Password: Vendor@123456"
echo "   → Should redirect to vendor dashboard"
echo ""
echo "2. View Vendor Store:"
echo "   → Visit: $SITE_URL/vendor/techgadgets-pro"
echo "   → Should show 3 products"
echo "   → Store info should display"
echo ""
echo "3. Add Vendor Product to Cart:"
echo "   → Visit any product URL above"
echo "   → Click 'Add to Cart'"
echo "   → Should add successfully"
echo ""
echo "4. Complete Purchase:"
echo "   → Login as customer"
echo "   → Add vendor product to cart"
echo "   → Complete checkout"
echo "   → Check vendor gets commission"
echo ""
echo "5. Vendor Dashboard (if implemented):"
echo "   → Visit: $SITE_URL/vendor-dashboard"
echo "   → View sales reports"
echo "   → Manage products"
echo "   → View earnings"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${BLUE}📁 Database Tables to Monitor${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "• nxm_nexmart_vendors - Vendor profiles"
echo "• nxm_nexmart_products - Vendor products"
echo "• nxm_nexmart_orders - Orders containing vendor products"
echo "• nxm_nexmart_order_items - Individual vendor product sales"
echo "• nxm_users - Vendor user accounts"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo -e "${GREEN}✅ Vendor System Ready for Testing!${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Next steps:"
echo "1. Login at: $SITE_URL/login"
echo "2. Use credentials above"
echo "3. Test vendor workflows"
echo "4. Check commission calculations"
echo ""

# Cleanup
rm -f /tmp/vendor_cookies.txt
