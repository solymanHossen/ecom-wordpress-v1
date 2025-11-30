#!/bin/bash
# Test cart functionality with proper session handling

echo "🛒 Testing Cart Session & Data Flow"
echo "═════════════════════════════════════════"

SITE_URL="http://localhost/ecommerce-wordpress"
NONCE=$(wp eval 'echo wp_create_nonce("nexmart_nonce");' 2>/dev/null)
COOKIES="/tmp/nexmart_cart_test_cookies.txt"

# Clean previous cookies
rm -f $COOKIES

echo ""
echo "1️⃣  Adding product to cart (creates session)..."
echo "────────────────────────────────────────────────"

ADD_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -d "action=nexmart_add_to_cart" \
  -d "product_id=1" \
  -d "quantity=2" \
  -d "nonce=$NONCE" \
  -b $COOKIES -c $COOKIES)

echo "$ADD_RESPONSE" | python3 -m json.tool 2>/dev/null | head -20
echo ""

if echo "$ADD_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Product added successfully"
else
    echo "❌ Failed to add product"
fi

echo ""
echo "2️⃣  Getting cart (should reuse session)..."
echo "────────────────────────────────────────────────"

CART_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -d "action=nexmart_get_cart" \
  -d "nonce=$NONCE" \
  -b $COOKIES -c $COOKIES)

echo "$CART_RESPONSE" | python3 -m json.tool 2>/dev/null | head -30
echo ""

ITEM_COUNT=$(echo "$CART_RESPONSE" | grep -o '"item_count":[0-9]*' | cut -d: -f2)

if [ "$ITEM_COUNT" -gt 0 ]; then
    echo "✅ Cart has $ITEM_COUNT item(s)"
else
    echo "❌ Cart is empty (session not maintained)"
fi

echo ""
echo "3️⃣  Checking database cart items..."
echo "────────────────────────────────────────────────"

wp db query "SELECT id, session_id, product_id, quantity, created_at FROM nxm_nexmart_cart ORDER BY created_at DESC LIMIT 3;" 2>/dev/null

echo ""
echo "4️⃣  Testing browser simulation (without cookies)..."
echo "────────────────────────────────────────────────"

NEW_SESSION_RESPONSE=$(curl -s -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  -d "action=nexmart_get_cart" \
  -d "nonce=$NONCE")

NEW_ITEM_COUNT=$(echo "$NEW_SESSION_RESPONSE" | grep -o '"item_count":[0-9]*' | cut -d: -f2)

if [ "$NEW_ITEM_COUNT" -eq 0 ]; then
    echo "✅ New session has empty cart (expected)"
else
    echo "⚠️  New session has $NEW_ITEM_COUNT items (unexpected)"
fi

echo ""
echo "═════════════════════════════════════════"
echo "📋 Summary:"
echo "═════════════════════════════════════════"
echo ""
echo "The cart system works correctly when:"
echo "  ✓ Browser sends cookies (session maintained)"
echo "  ✓ Same session ID is reused"
echo ""
echo "The cart appears empty when:"
echo "  ✗ Browser doesn't send cookies"
echo "  ✗ New session ID generated each request"
echo ""
echo "🔍 Solution:"
echo "  → Ensure browser cookies are enabled"
echo "  → Check that session cookies are being set"
echo "  → Verify JavaScript uses credentials: 'same-origin'"
echo ""

# Cleanup
rm -f $COOKIES
