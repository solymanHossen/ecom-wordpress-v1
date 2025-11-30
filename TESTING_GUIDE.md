# Testing Guide - NexMart E-commerce
**Last Updated**: November 30, 2025

## Quick Test URLs

### Main Pages
- **Homepage**: http://localhost/ecommerce-wordpress/
- **Shop**: http://localhost/ecommerce-wordpress/shop/
- **Cart**: http://localhost/ecommerce-wordpress/cart/
- **Checkout**: http://localhost/ecommerce-wordpress/checkout/

### Authentication Pages
- **Register**: http://localhost/ecommerce-wordpress/register/
- **Login**: http://localhost/ecommerce-wordpress/login/
- **My Account**: http://localhost/ecommerce-wordpress/my-account/

---

## Test Scenarios

### 1. User Registration (FIXED ✅)

**Test Steps:**
1. Open: http://localhost/ecommerce-wordpress/register/
2. Fill in the form:
   - **Full Name**: John Doe
   - **Email**: john.doe@example.com
   - **Password**: SecurePass123!
   - **Confirm Password**: SecurePass123!
   - Check "I agree to the Terms & Conditions"
3. Click "Create Account"

**Expected Results:**
- ✅ Green success message: "Account created successfully!"
- ✅ Automatic redirect to login page after 2 seconds
- ✅ No "Unexpected token" errors in console
- ✅ User created in WordPress (check: Users → All Users)

**Common Issues:**
- ❌ "Unexpected token '<'" → FIXED with output buffer cleaning
- ❌ "Email already exists" → Use a different email or delete existing user
- ❌ "Password must be at least 8 characters" → Use longer password

---

### 2. User Login (FIXED ✅)

**Test Steps:**
1. Open: http://localhost/ecommerce-wordpress/login/
2. Enter credentials:
   - **Email**: john.doe@example.com
   - **Password**: SecurePass123!
   - Optional: Check "Remember me"
3. Click "Sign In"

**Expected Results:**
- ✅ Green success message: "Login successful! Redirecting..."
- ✅ Redirect to My Account page after 1 second
- ✅ User session active (see user name in header)
- ✅ No JSON parsing errors

**Common Issues:**
- ❌ "Invalid email or password" → Check credentials, try resetting
- ❌ Redirects to wrong page → Check redirect_to parameter

---

### 3. Cart Icon & Dropdown (FIXED ✅)

**Test Steps:**
1. Open homepage: http://localhost/ecommerce-wordpress/
2. Click any "Add to Cart" button on a product
3. Observe cart icon in header (top-right)
4. Click the cart icon

**Expected Results:**
- ✅ Badge appears on cart icon showing item count (e.g., "1")
- ✅ Success notification: "Product added to cart!"
- ✅ Cart dropdown opens automatically after adding item
- ✅ Cart dropdown shows product with image, name, price, quantity
- ✅ Subtotal displays correctly
- ✅ "View Cart" and "Checkout" buttons work

**Common Issues:**
- ❌ Cart dropdown doesn't open → FIXED with preventDefault()
- ❌ Cart badge not showing → FIXED with updateCartUI() call
- ❌ Empty cart on page load → Check session cookies enabled

---

### 4. Add to Cart Functionality

**Test Steps:**
1. Navigate to any product page
2. Change quantity (e.g., 3)
3. Click "Add to Cart"
4. Click cart icon to open dropdown
5. Test quantity increase/decrease buttons
6. Test remove item button (X)

**Expected Results:**
- ✅ Product added with selected quantity
- ✅ Cart updates without page reload
- ✅ Quantity buttons work (+/-)
- ✅ Remove button removes item
- ✅ Subtotal recalculates automatically

---

### 5. Cart Page

**Test Steps:**
1. Add items to cart
2. Navigate to: http://localhost/ecommerce-wordpress/cart/
3. Test all cart operations

**Expected Results:**
- ✅ All cart items display correctly
- ✅ Images, names, prices show correctly
- ✅ Quantity can be updated
- ✅ Remove items works
- ✅ Totals (Subtotal, Tax, Shipping, Total) calculate correctly
- ✅ "Proceed to Checkout" button works

---

### 6. Checkout Flow (Guest vs Logged In)

**As Guest:**
1. Add items to cart
2. Click "Checkout"
3. Should prompt for login/registration

**As Logged In User:**
1. Add items to cart
2. Click "Checkout"
3. Should show checkout form with pre-filled details
4. Fill in shipping information
5. Choose payment method
6. Place order

**Expected Results:**
- ✅ Guest users prompted to login
- ✅ Logged-in users can proceed directly
- ✅ User data pre-filled (name, email, address)
- ✅ Order places successfully
- ✅ Order visible in My Account → Orders

---

## Browser Console Tests

### Check Cart Initialization
```javascript
// Open browser console (F12)
console.log(NexMart.cart);
// Should show cart object with items array

console.log(nexmartObj);
// Should show AJAX config with nonce and URLs
```

### Manually Add to Cart
```javascript
// Test adding product ID 1 with quantity 2
NexMart.addToCart(1, 2)
  .then(data => console.log('Success:', data))
  .catch(err => console.error('Error:', err));
```

### Check Cart UI Update
```javascript
// Force cart UI update
NexMart.updateCartUI();

// Open cart drawer manually
NexMart.openCartDrawer();

// Close cart drawer
NexMart.closeCartDrawer();
```

---

## AJAX Endpoint Tests (cURL)

### Test Registration Endpoint
```bash
cd /var/www/html/ecommerce-wordpress

# Get nonce
NONCE=$(wp eval 'echo wp_create_nonce("nexmart_nonce");')

# Test registration
curl -X POST "http://localhost/ecommerce-wordpress/wp-admin/admin-ajax.php" \
  -d "action=nexmart_register" \
  -d "email=testuser$(date +%s)@example.com" \
  -d "password=testpass123" \
  -d "name=Test User" \
  -d "nonce=$NONCE"

# Expected: {"success":true,"data":{"message":"Account created successfully!","redirect":"..."}}
```

### Test Login Endpoint
```bash
curl -X POST "http://localhost/ecommerce-wordpress/wp-admin/admin-ajax.php" \
  -d "action=nexmart_login" \
  -d "email=testuser@example.com" \
  -d "password=testpass123" \
  -d "nonce=$NONCE" \
  -c cookies.txt

# Expected: {"success":true,"data":{"message":"Login successful!","redirect":"...","user":{...}}}
```

### Test Get Cart
```bash
curl -X POST "http://localhost/ecommerce-wordpress/wp-admin/admin-ajax.php" \
  -d "action=nexmart_get_cart" \
  -d "nonce=$NONCE" \
  -b cookies.txt

# Expected: {"success":true,"data":{"cart":{"items":[],"item_count":0,...}}}
```

### Test Add to Cart
```bash
curl -X POST "http://localhost/ecommerce-wordpress/wp-admin/admin-ajax.php" \
  -d "action=nexmart_add_to_cart" \
  -d "product_id=1" \
  -d "quantity=1" \
  -d "nonce=$NONCE" \
  -b cookies.txt -c cookies.txt

# Expected: {"success":true,"data":{"cart":{...},"message":"Product added to cart"}}
```

---

## Database Verification

### Check New User Created
```bash
wp user list --search=john.doe@example.com --format=table
```

### Check Cart Items
```bash
wp db query "SELECT * FROM nxm_nexmart_cart ORDER BY created_at DESC LIMIT 5;"
```

### Check Orders
```bash
wp db query "SELECT * FROM nxm_nexmart_orders ORDER BY created_at DESC LIMIT 5;"
```

---

## Error Log Monitoring

### Watch PHP Error Log
```bash
tail -f /var/log/apache2/error.log
```

### Watch WordPress Debug Log
```bash
tail -f wp-content/debug.log
```

### Check for AJAX Errors
```bash
grep "nexmart" /var/log/apache2/error.log | tail -20
```

---

## Performance Testing

### Page Load Times
- Homepage: < 2 seconds
- Product Page: < 2 seconds  
- Cart Page: < 1.5 seconds
- Checkout: < 2 seconds

### AJAX Response Times
- Add to Cart: < 500ms
- Get Cart: < 300ms
- Login: < 800ms
- Registration: < 1 second

### Measure with cURL
```bash
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost/ecommerce-wordpress/"

# Create curl-format.txt:
time_namelookup:  %{time_namelookup}s\n
time_connect:  %{time_connect}s\n
time_appconnect:  %{time_appconnect}s\n
time_pretransfer:  %{time_pretransfer}s\n
time_redirect:  %{time_redirect}s\n
time_starttransfer:  %{time_starttransfer}s\n
----------\n
time_total:  %{time_total}s\n
```

---

## Mobile Testing

### Responsive Breakpoints
- Mobile: 320px - 767px
- Tablet: 768px - 1023px
- Desktop: 1024px+

### Test Devices
- iPhone SE (375x667)
- iPhone 12 Pro (390x844)
- iPad (768x1024)
- Desktop (1920x1080)

### Features to Test
- ✅ Mobile navigation menu
- ✅ Sticky bottom navigation
- ✅ Cart dropdown on mobile
- ✅ Touch-friendly buttons
- ✅ Form inputs on mobile keyboards

---

## Security Testing

### XSS Prevention
- Try injecting `<script>alert('XSS')</script>` in name field
- Should be sanitized

### SQL Injection
- Try `' OR '1'='1` in email field
- Should be prevented by prepared statements

### CSRF Protection
- Try submitting form without nonce
- Should be rejected (with lenient fallback)

### Rate Limiting
- Try 20 registration attempts rapidly
- Should consider adding rate limiting

---

## Troubleshooting Common Issues

### Issue: "Unexpected token '<'" on Registration
**Cause**: PHP outputting HTML before JSON response
**Fix**: ✅ FIXED - Added ob_clean() in AJAX handlers
**Verify**: Check network tab shows Content-Type: application/json

### Issue: Cart Icon Not Showing Count
**Cause**: updateCartUI() not called after loadCart()
**Fix**: ✅ FIXED - Added updateCartUI() call in loadCart()
**Verify**: Inspect element, badge should not have 'hidden' class

### Issue: Cart Dropdown Not Opening
**Cause**: Link navigation preventing drawer opening
**Fix**: ✅ FIXED - Added e.preventDefault() in click handler
**Verify**: Console should log "Cart button clicked"

### Issue: Session Lost After Page Refresh
**Cause**: PHP sessions not configured
**Fix**: Check session.save_path in php.ini
**Verify**: `<?php session_start(); var_dump($_SESSION); ?>`

---

## Success Criteria

All tests pass when:
- ✅ Users can register without errors
- ✅ Users can login successfully
- ✅ Cart icon shows correct item count
- ✅ Cart dropdown opens and displays items
- ✅ Add to cart works from all pages
- ✅ Cart updates work (quantity, remove)
- ✅ Checkout flow completes
- ✅ Orders are created and visible
- ✅ No JavaScript console errors
- ✅ No PHP errors in logs
- ✅ Mobile responsive design works
- ✅ Page load times under 2 seconds

---

## Next Steps After Testing

1. ✅ All fixes implemented and tested
2. ⏳ Deploy to staging environment
3. ⏳ User acceptance testing (UAT)
4. ⏳ Performance optimization
5. ⏳ Security audit
6. ⏳ Production deployment

---

## Support

For issues or questions:
- Check `FIXES_COMPLETED.md` for detailed fix information
- Review `PROJECT_AUDIT.md` for system architecture
- See `AUTHENTICATION_GUIDE.md` for auth system details

**Testing completed successfully! Ready for deployment.**
