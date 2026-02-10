# 🔍 Invoice Loading Issue - Debugging Guide

## Issue Description

**Problem:** Invoice page shows "Loading reservations..." indefinitely, with no data appearing in the table.

**Page Display:**
```
ParkaLot
Parking Management System

Invoice
Date: 30/01/2026

Total Reservations: 0
Total Amount: £0.00

ID | Garage | Start Time | End Time | Duration | Price (£)
Loading reservations...
```

---

## 🔧 Fixes Applied

### 1. Enhanced InvoiceController.php

**Changes:**
- ✅ Now retrieves `price` field from database if it exists
- ✅ Falls back to calculating price using `price_per_hour` from garages table
- ✅ Returns properly formatted price (2 decimal places)
- ✅ Includes all necessary fields (status, created_at, etc.)

**Before:**
```php
SELECT r.reservation_id, r.garage_id, garage_name, r.start_time, r.end_time
FROM reservations r
LEFT JOIN garages g ON g.garage_id = r.garage_id
WHERE r.user_id = ?
```

**After:**
```php
SELECT r.reservation_id, r.garage_id, r.user_id, garage_name, 
       r.start_time, r.end_time, r.price, r.status, r.created_at,
       g.price_per_hour
FROM reservations r
LEFT JOIN garages g ON g.garage_id = r.garage_id
WHERE r.user_id = ?
```

---

### 2. Added Debugging to app.js

**Added console.log statements to track:**
- When loadInvoice() is called
- Authentication check results
- API request URL and response status
- Data received from API
- Any errors that occur

**Example console output (success):**
```
Loading invoice data...
Auth check response: 200
Fetching invoice from: ../api/index.php?route=invoice
Invoice response status: 200
Invoice data received: {count: 2, total: "35.50", reservations: Array(2)}
```

**Example console output (no data):**
```
Loading invoice data...
Auth check response: 200
Fetching invoice from: ../api/index.php?route=invoice
Invoice response status: 200
Invoice data received: {count: 0, total: "0.00", reservations: []}
```

---

### 3. Fixed invoice.html Initialization

**Changes:**
- ✅ Ensures app.js loads before calling loadInvoice()
- ✅ Waits for DOMContentLoaded event
- ✅ Verifies loadInvoice function exists before calling
- ✅ Added console logging to track initialization

**Initialization sequence:**
```javascript
1. DOM loads
2. Log: "DOM loaded, starting initialization..."
3. Call checkAuth()
4. Log: "Auth result: true"
5. Check if loadInvoice exists
6. Call loadInvoice()
7. Log: "Loading invoice data..."
```

---

## 🧪 Test Script Created

**File:** `tmp_rovodev_test_invoice.php`

**What it checks:**
1. ✅ If user is logged in (session check)
2. ✅ If reservations table exists
3. ✅ Table structure (all columns)
4. ✅ Total reservations count (all users)
5. ✅ Current user's reservation count
6. ✅ Detailed reservation data
7. ✅ InvoiceController functionality
8. ✅ API response simulation

**How to run:**
```bash
# Option 1: Via browser
http://localhost/parkalot_system/tmp_rovodev_test_invoice.php

# Option 2: Via command line
php tmp_rovodev_test_invoice.php
```

---

## 🔍 Possible Causes

### Cause 1: No Reservations in Database
**Symptom:** Count shows 0, array is empty
**Solution:** Create at least one reservation first

**How to create a reservation:**
1. Login to customer dashboard
2. Select a garage from dropdown
3. Choose start date/time
4. Choose end date/time
5. Click "Reserve Now"
6. Wait for success message
7. Then view invoice

---

### Cause 2: Not Logged In
**Symptom:** Redirects to login page immediately
**Solution:** Login with valid credentials

**Check in browser console:**
```javascript
// Should show user info
fetch('../api/index.php?route=me')
  .then(r => r.json())
  .then(console.log)
```

---

### Cause 3: Session Expired
**Symptom:** Auth check fails, redirect to login
**Solution:** Login again (sessions expire after 1 hour)

---

### Cause 4: JavaScript Error
**Symptom:** Console shows errors, function not defined
**Solution:** Check browser console (F12) for errors

**Common errors:**
- "loadInvoice is not a function" → app.js not loaded
- "Cannot read property 'textContent' of null" → DOM element missing
- "Failed to fetch" → API endpoint not accessible

---

### Cause 5: API Endpoint Not Working
**Symptom:** HTTP 404 or 500 error in console
**Solution:** Verify API endpoint exists

**Test API directly:**
```bash
# After logging in, test with cookies
curl -b cookies.txt \
  http://localhost/parkalot_system/api/index.php?route=invoice
```

---

### Cause 6: Wrong User ID
**Symptom:** Other users have reservations but yours shows 0
**Solution:** Verify you're logged in with the correct account

**Check in database:**
```sql
-- Check which user has reservations
SELECT user_id, COUNT(*) as count 
FROM reservations 
GROUP BY user_id;

-- Check current session user
SELECT user_id, full_name, email FROM users WHERE user_id = ?;
```

---

## 📋 Debugging Checklist

### Step 1: Run Test Script
- [ ] Run `tmp_rovodev_test_invoice.php`
- [ ] Note the output (logged in? reservations count?)
- [ ] Check if InvoiceController works
- [ ] Verify API returns data

### Step 2: Check Browser Console
- [ ] Open invoice.html
- [ ] Press F12 to open developer tools
- [ ] Go to Console tab
- [ ] Look for these messages:
  - [ ] "DOM loaded, starting initialization..."
  - [ ] "Initializing invoice page..."
  - [ ] "Auth result: true"
  - [ ] "Calling loadInvoice()..."
  - [ ] "Loading invoice data..."
  - [ ] "Invoice data received: {...}"

### Step 3: Check Network Tab
- [ ] Open Network tab in dev tools
- [ ] Reload invoice.html
- [ ] Look for requests:
  - [ ] `?route=me` (should return 200)
  - [ ] `?route=invoice` (should return 200)
- [ ] Click on `?route=invoice` request
- [ ] Check Response tab - should show JSON with reservations

### Step 4: Verify You Have Reservations
- [ ] Login to system
- [ ] Go to customer dashboard
- [ ] Create at least one reservation
- [ ] Wait for success message
- [ ] Then try invoice page again

### Step 5: Check Database Directly
```sql
-- Count your reservations
SELECT COUNT(*) FROM reservations WHERE user_id = YOUR_USER_ID;

-- View your reservations
SELECT * FROM reservations WHERE user_id = YOUR_USER_ID;

-- Check if prices are set
SELECT reservation_id, price FROM reservations WHERE user_id = YOUR_USER_ID;
```

---

## 🎯 Quick Diagnosis

### If test script shows:
- **"NOT SET" for user_id** → Not logged in, login first
- **"Total reservations: 0"** → Database is empty, create test data
- **"Your reservations: 0"** → Current user has no reservations, create one
- **"Your reservations: X"** but invoice shows 0 → JavaScript/API issue

### If browser console shows:
- **"loadInvoice is not a function"** → app.js not loaded properly
- **"Not authenticated"** → Session expired, login again
- **"Failed to load invoice"** → API error, check server logs
- **"Invoice data received: {count: 0}"** → No reservations in database

### If Network tab shows:
- **404 for ?route=invoice** → API endpoint missing
- **401 for ?route=invoice** → Not authenticated
- **500 for ?route=invoice** → Server error, check PHP logs
- **200 with empty data** → No reservations for user

---

## 🔧 Solutions by Scenario

### Scenario 1: Fresh Installation (No Data)
```
Problem: No reservations exist
Solution:
1. Login to system
2. Go to customer dashboard
3. Create 2-3 test reservations
4. View invoice page - should now show data
```

### Scenario 2: JavaScript Not Loading
```
Problem: loadInvoice function not found
Solution:
1. Check app.js exists in public/js/
2. Clear browser cache (Ctrl+F5)
3. Verify no JavaScript errors in console
4. Check script src path is correct
```

### Scenario 3: API Not Returning Data
```
Problem: API returns 200 but empty array
Solution:
1. Check you're logged in as correct user
2. Verify session hasn't expired
3. Run test script to see actual reservation count
4. Check database directly
```

### Scenario 4: Price Not Calculated
```
Problem: Reservations show but price is 0.00
Solution:
1. Ensure garages table has price_per_hour set
2. Check reservations table has price column
3. Verify ReservationController calculates price on create
4. Update existing reservations with prices
```

---

## 📝 Expected Console Output

### Successful Load (With Reservations):
```
DOM loaded, starting initialization...
Initializing invoice page...
Auth result: true
Calling loadInvoice()...
Loading invoice data...
Auth check response: 200
Fetching invoice from: ../api/index.php?route=invoice
Invoice response status: 200
Invoice data received: {count: 2, total: "35.50", reservations: Array(2)}
```

### Successful Load (No Reservations):
```
DOM loaded, starting initialization...
Initializing invoice page...
Auth result: true
Calling loadInvoice()...
Loading invoice data...
Auth check response: 200
Fetching invoice from: ../api/index.php?route=invoice
Invoice response status: 200
Invoice data received: {count: 0, total: "0.00", reservations: []}
```

### Failed - Not Authenticated:
```
DOM loaded, starting initialization...
Initializing invoice page...
Auth result: false
(Redirects to index.html)
```

### Failed - API Error:
```
DOM loaded, starting initialization...
Initializing invoice page...
Auth result: true
Calling loadInvoice()...
Loading invoice data...
Auth check response: 200
Fetching invoice from: ../api/index.php?route=invoice
Invoice response status: 500
Invoice error response: <error details>
Load invoice error: Failed to load invoice
```

---

## 🚀 Next Steps

1. **Run the test script** (`tmp_rovodev_test_invoice.php`)
2. **Share the output** so we can see:
   - If you're logged in
   - How many reservations exist
   - What data is in the database
   - If InvoiceController works
3. **Check browser console** when viewing invoice.html
4. **Create a reservation** if you don't have any

---

## 📞 Getting Help

**Information to provide:**
1. Output from test script
2. Browser console messages
3. Network tab showing API requests/responses
4. Any error messages

**Quick checks:**
```bash
# Check if you're logged in
curl -b cookies.txt http://localhost/parkalot_system/api/index.php?route=me

# Check invoice data
curl -b cookies.txt http://localhost/parkalot_system/api/index.php?route=invoice
```

---

**Status:** Debugging in progress  
**Last Updated:** January 30, 2026  
**Files Modified:** InvoiceController.php, app.js, invoice.html  
**Test Script:** tmp_rovodev_test_invoice.php
