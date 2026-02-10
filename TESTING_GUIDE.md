# 🧪 Complete Testing Guide - ParkaLot System

## Overview

This guide provides step-by-step testing procedures for all systems: Authentication, Reservations, and Invoices.

**Last Updated:** January 30, 2026  
**Status:** ✅ All Systems Fixed and Ready for Testing

---

## 🎯 Quick Start Testing

### Prerequisites
1. Server running (Apache/Nginx with PHP)
2. Database configured and populated
3. Browser with cookies enabled
4. Developer tools (F12) for debugging

### Test URL
```
http://localhost/parkalot_system/public/index.html
```

---

## 📋 Test Scenarios

### Scenario 1: New User Registration & First Reservation

#### Step 1: Register New User
1. Open `http://localhost/parkalot_system/public/index.html`
2. Click "Register" tab
3. Fill in form:
   - **Name:** Test Customer
   - **Email:** testcustomer@example.com
   - **Password:** TestPass123
4. Click "Register"

**Expected Result:**
- ✅ Success message: "Registration successful! Please verify your email."
- ✅ Verification modal opens automatically
- ✅ No console errors

#### Step 2: Email Verification
1. Check server console/logs for OTP code:
   ```
   [timestamp] OTP EMAIL TO: testcustomer@example.com | CODE: 123456
   ```
2. Enter the 6-digit code in the modal
3. Click "Verify Email"

**Expected Result:**
- ✅ Success message: "Email verified successfully! Redirecting..."
- ✅ Auto-redirect to dashboard after 1.5 seconds
- ✅ User is logged in

#### Step 3: Verify Dashboard Loaded
1. Should be on `customer_dashboard.html`
2. Check page displays:
   - ✅ Welcome message with user name
   - ✅ Quick stats (Total Reservations: 0)
   - ✅ AI Recommendations section
   - ✅ Reservation form
   - ✅ No console errors

#### Step 4: Create First Reservation
1. Select a garage from dropdown
2. Enter start date/time: `2026-02-01 10:00`
3. Enter end date/time: `2026-02-01 14:00`
4. Click "Reserve Parking"

**Expected Result:**
- ✅ Success message with details:
  ```
  Reservation confirmed successfully!
  Duration: 4.0 hours
  Price per hour: £X.XX
  Total price: £XX.XX
  ```
- ✅ Stats update (Total Reservations: 1)

#### Step 5: View Invoice
1. Click "View My Invoice" or navigate to `invoice.html`
2. Check invoice page displays:
   - ✅ Invoice header with date
   - ✅ Total Reservations: 1
   - ✅ Total Amount: £XX.XX
   - ✅ Reservation details in table
   - ✅ Garage name
   - ✅ Start/End times
   - ✅ Duration
   - ✅ Price

#### Step 6: Download PDF
1. Click "Download Invoice PDF"
2. PDF should download with all reservation data

**Expected Result:**
- ✅ PDF file downloads
- ✅ Contains invoice header
- ✅ Lists all reservations
- ✅ Shows totals

---

### Scenario 2: Existing User Login

#### Step 1: Logout
1. On dashboard, click logout (if available) or clear cookies
2. Navigate to `index.html`

#### Step 2: Login
1. Click "Login" tab
2. Enter credentials:
   - **Email:** testcustomer@example.com
   - **Password:** TestPass123
3. Click "Login"

**Expected Result:**
- ✅ Redirect to dashboard
- ✅ Previous reservations visible
- ✅ Stats show correct counts

---

### Scenario 3: Unverified User Login

#### Step 1: Register Without Verifying
1. Register a new user
2. Close verification modal (or skip)

#### Step 2: Try to Login
1. Logout or use new session
2. Try to login with unverified email
3. Enter credentials

**Expected Result:**
- ✅ Info message: "Please verify your email address to continue..."
- ✅ Verification modal opens automatically
- ✅ OTP sent to email

#### Step 3: Complete Verification
1. Enter OTP code
2. Verify

**Expected Result:**
- ✅ Auto-login after verification
- ✅ Redirect to dashboard

---

### Scenario 4: Multiple Reservations

#### Step 1: Create Multiple Reservations
1. Login to dashboard
2. Create reservation 1:
   - Garage: Any
   - Start: `2026-02-01 09:00`
   - End: `2026-02-01 12:00`
   - Submit
3. Create reservation 2:
   - Garage: Any
   - Start: `2026-02-02 14:00`
   - End: `2026-02-02 18:00`
   - Submit
4. Create reservation 3:
   - Garage: Any
   - Start: `2026-02-03 10:00`
   - End: `2026-02-03 15:00`
   - Submit

**Expected Result:**
- ✅ All reservations created successfully
- ✅ Stats show: Total Reservations: 3
- ✅ Total spent increases with each reservation

#### Step 2: View All on Invoice
1. Navigate to invoice page
2. Check table shows all 3 reservations
3. Verify prices are calculated correctly
4. Check total is sum of all prices

**Expected Result:**
- ✅ All reservations listed
- ✅ Correct duration calculations
- ✅ Correct price calculations
- ✅ Correct total sum

---

### Scenario 5: Session Timeout

#### Step 1: Login and Wait
1. Login to dashboard
2. Wait for session timeout (1 hour)
   - Or modify `SESSION_TIMEOUT` in `config/security.php` to 60 seconds for testing

#### Step 2: Try to Access Protected Resource
1. After timeout, try to create reservation
2. Or refresh dashboard

**Expected Result:**
- ✅ Session expired message
- ✅ Redirect to login page
- ✅ HTTP 401 status code

---

### Scenario 6: Rate Limiting

#### Step 1: Trigger Login Rate Limit
1. Try to login with wrong password 5 times

**Expected Result:**
- ✅ After 5 attempts: "Too many failed login attempts. Please try again in 15 minutes."
- ✅ HTTP 429 status code

#### Step 2: Trigger OTP Rate Limit
1. Request OTP 3 times within 5 minutes

**Expected Result:**
- ✅ After 3 requests: "Too many requests. Please wait 5 minutes..."

---

## 🔍 API Testing

### Using cURL

#### Register
```bash
curl -X POST http://localhost/parkalot_system/api/index.php?route=register \
  -H "Content-Type: application/json" \
  -d '{"name":"API Test","email":"apitest@example.com","password":"TestPass123"}' \
  -c cookies.txt
```

#### Login
```bash
curl -X POST http://localhost/parkalot_system/api/index.php?route=login \
  -H "Content-Type: application/json" \
  -d '{"email":"apitest@example.com","password":"TestPass123"}' \
  -c cookies.txt
```

#### Get Current User
```bash
curl http://localhost/parkalot_system/api/index.php?route=me \
  -b cookies.txt
```

#### List Garages
```bash
curl http://localhost/parkalot_system/api/index.php?route=garages
```

#### Create Reservation
```bash
curl -X POST http://localhost/parkalot_system/api/index.php?route=reserve \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "garage_id": 1,
    "start_time": "2026-02-01 10:00:00",
    "end_time": "2026-02-01 14:00:00"
  }'
```

#### Get Reservations
```bash
curl http://localhost/parkalot_system/api/index.php?route=reserve \
  -b cookies.txt
```

#### Get Invoice
```bash
curl http://localhost/parkalot_system/api/index.php?route=invoice \
  -b cookies.txt
```

#### Download Invoice PDF
```bash
curl http://localhost/parkalot_system/api/index.php?route=invoice/pdf \
  -b cookies.txt \
  -o invoice.pdf
```

---

## 🐛 Troubleshooting

### Problem: "Not authenticated" error

**Possible Causes:**
- Not logged in
- Session expired (1 hour timeout)
- Cookies disabled
- Wrong credentials

**Solution:**
1. Check browser cookies are enabled
2. Login again
3. Check session hasn't expired
4. Verify credentials

**Check in DevTools:**
```javascript
// Console:
fetch('/parkalot_system/api/index.php?route=me')
  .then(r => r.json())
  .then(console.log)
```

---

### Problem: Reservations not showing

**Possible Causes:**
- No reservations created yet
- Wrong user logged in
- API endpoint error
- Route format incorrect

**Solution:**
1. Create at least one reservation
2. Verify correct user is logged in
3. Check Network tab for API responses
4. Verify route is `reserve` not `/reserve`

**Check Database:**
```sql
SELECT * FROM reservations WHERE user_id = X;
```

---

### Problem: Invoice shows no data

**Possible Causes:**
- No reservations for user
- Database query error
- Price not calculated

**Solution:**
1. Create reservations first
2. Check API response: `/api/index.php?route=invoice`
3. Verify garage has `price_per_hour` set
4. Check ReservationDAO calculates price

**Manual Test:**
```bash
curl -b cookies.txt \
  http://localhost/parkalot_system/api/index.php?route=invoice | jq
```

---

### Problem: PDF not generating

**Possible Causes:**
- Route incorrect
- Not authenticated
- SimplePDF class error

**Solution:**
1. Verify route: `invoice/pdf`
2. Check authentication
3. Look at server error logs
4. Test endpoint directly:
   ```
   http://localhost/parkalot_system/api/index.php?route=invoice/pdf
   ```

---

### Problem: Price calculation wrong

**Possible Causes:**
- End time before start time
- Invalid date format
- Garage missing price_per_hour

**Solution:**
1. Verify start_time < end_time
2. Check date format: `YYYY-MM-DD HH:MM:SS`
3. Verify garage has price set:
   ```sql
   SELECT price_per_hour FROM garages WHERE garage_id = X;
   ```

---

### Problem: Dashboard not loading

**Possible Causes:**
- Not authenticated
- JavaScript error
- API endpoint down

**Solution:**
1. Check browser console for errors
2. Verify authentication
3. Check Network tab for failed requests
4. Test API endpoints individually

---

## ✅ Verification Checklist

### Authentication System
- [ ] Register new user
- [ ] Email verification modal appears
- [ ] OTP code sent (check logs)
- [ ] OTP verification works
- [ ] Auto-login after verification
- [ ] Login with existing user
- [ ] Login with unverified user prompts for verification
- [ ] Wrong password shows error
- [ ] Rate limiting after 5 failed attempts
- [ ] Logout works
- [ ] Session timeout (1 hour)

### Reservation System
- [ ] Dashboard loads after login
- [ ] Garage list loads in dropdown
- [ ] Can select garage
- [ ] Can select dates
- [ ] Create reservation succeeds
- [ ] Success message shows price
- [ ] Stats update after reservation
- [ ] Can view reservations via API
- [ ] Multiple reservations work
- [ ] Date validation works (end > start)
- [ ] Past date rejected
- [ ] Garage capacity checked

### Invoice System
- [ ] Invoice page loads
- [ ] Authentication checked on load
- [ ] Redirect to login if not authenticated
- [ ] All reservations displayed
- [ ] Garage names shown
- [ ] Start/End times correct
- [ ] Duration calculated correctly
- [ ] Prices calculated correctly
- [ ] Total sum correct
- [ ] PDF download works
- [ ] PDF contains all data
- [ ] Back to dashboard button works

### Security
- [ ] Passwords hashed (bcrypt)
- [ ] Email verification required
- [ ] Session-based authentication
- [ ] HTTP 401 for unauthenticated
- [ ] HTTP 403 for unauthorized
- [ ] SQL injection prevented (PDO)
- [ ] XSS protection
- [ ] CSRF tokens available
- [ ] Activity logging works
- [ ] Rate limiting works

---

## 📊 Expected Results Summary

### After Registration
- User created with `email_verified = 0`
- Verification modal shown
- Pending session created

### After Email Verification
- `email_verified = 1` in database
- Full session created
- Redirect to dashboard

### After Login (Verified User)
- Session created with user_id, role, user_name
- Redirect to appropriate dashboard
- Stats loaded

### After Creating Reservation
- Reservation record in database
- Price calculated automatically
- Status = 'active'
- Success response with details

### On Invoice Page
- All user reservations displayed
- Prices from database
- Duration calculated from dates
- Total summed correctly

### On PDF Download
- PDF file generated
- All reservations included
- Professional formatting

---

## 🎓 Testing Tips

1. **Use Browser DevTools**
   - Network tab to see API calls
   - Console for JavaScript errors
   - Application tab to check cookies/session

2. **Check Server Logs**
   - PHP error log for backend errors
   - Look for OTP codes
   - Check SQL queries

3. **Test Edge Cases**
   - Empty fields
   - Invalid dates
   - Very long names
   - Special characters
   - SQL injection attempts

4. **Test Multiple Users**
   - Create several users
   - Verify data isolation
   - Check each sees only their data

5. **Test Different Roles**
   - Customer
   - Employee
   - Senior Employee
   - Manager

---

## 📞 Support

If tests fail:
1. Check this guide's troubleshooting section
2. Review `AUTHENTICATION_RESERVATION_INVOICE_FIXES.md`
3. Check `EMAIL_VERIFICATION_GUIDE.md`
4. Inspect browser console and network tab
5. Check server error logs

---

**Testing Status:** ✅ Ready for Complete Testing  
**Systems:** All Fixed and Operational  
**Last Updated:** January 30, 2026
