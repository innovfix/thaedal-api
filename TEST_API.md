# Thaedal API Testing Guide

## 🌐 Base URL
```
http://localhost:8000/api/v1/
```

## 📱 Testing with Android App

### Update API Base URL in Android App

Edit this file: `app/src/main/java/com/innovfix/thaedal/data/remote/api/ApiConstants.kt`

```kotlin
object ApiConstants {
    // Change this to your local IP address (not localhost!)
    const val BASE_URL = "http://192.168.1.X:8000/api/v1/"
    
    // API Endpoints
    const val SEND_OTP = "auth/send-otp"
    const val VERIFY_OTP = "auth/verify-otp"
    // ... rest of endpoints
}
```

**⚠️ Important:** Replace `192.168.1.X` with your computer's local IP address.

### Find Your Local IP Address

**On Windows:**
```powershell
ipconfig
# Look for "IPv4 Address" under your active network adapter
```

**Example:** If your IP is `192.168.1.100`, use:
```kotlin
const val BASE_URL = "http://192.168.1.100:8000/api/v1/"
```

---

## 🧪 API Endpoints Testing

### 1. Health Check (Public)
```bash
GET http://localhost:8000/api/health
```

**Expected Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-11-25T12:00:00+05:30"
}
```

---

### 2. Send OTP (Public)
```bash
POST http://localhost:8000/api/v1/auth/send-otp
Content-Type: application/json

{
  "phone_number": "+919876543210"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "data": {
    "expires_at": "2024-11-25T12:10:00+05:30",
    "otp": "123456"  // Only in development mode
  }
}
```

---

### 3. Verify OTP (Public)
```bash
POST http://localhost:8000/api/v1/auth/verify-otp
Content-Type: application/json

{
  "phone_number": "+919876543210",
  "otp": "123456"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "OTP verified successfully",
  "data": {
    "user": {
      "id": "uuid",
      "name": "User Name",
      "phone_number": "+919876543210",
      "is_subscribed": false
    },
    "access_token": "bearer_token_here",
    "token_type": "Bearer"
  }
}
```

---

### 4. Get Categories (Requires Auth)
```bash
GET http://localhost:8000/api/v1/categories
Authorization: Bearer {access_token}
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Share Market",
      "slug": "share-market",
      "icon_url": "https://...",
      "videos_count": 10
    }
  ]
}
```

---

### 5. Get Videos (Requires Auth)
```bash
GET http://localhost:8000/api/v1/videos
Authorization: Bearer {access_token}
```

**Query Parameters:**
- `category_id` - Filter by category
- `is_premium` - Filter premium content (0 or 1)
- `search` - Search videos by title
- `page` - Pagination (default: 1)
- `per_page` - Items per page (default: 20)

---

### 6. Get Video Details (Requires Auth)
```bash
GET http://localhost:8000/api/v1/videos/{video_id}
Authorization: Bearer {access_token}
```

---

### 7. Get Subscription Plans (Requires Auth)
```bash
GET http://localhost:8000/api/v1/subscriptions/plans
Authorization: Bearer {access_token}
```

---

### 8. Create Subscription (Requires Auth)
```bash
POST http://localhost:8000/api/v1/subscriptions
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "plan_id": "uuid",
  "payment_method_id": "uuid"
}
```

---

## 🔑 Authentication Flow

1. **Send OTP** → User enters phone number
2. **Verify OTP** → User enters OTP code
3. **Store Token** → Save `access_token` from response
4. **Use Token** → Include in `Authorization: Bearer {token}` header for all protected endpoints

---

## 🛠️ Testing Tools

### Option 1: PowerShell (Windows)

#### Send OTP:
```powershell
$body = @{
    phone_number = "+919876543210"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/send-otp" `
    -Method Post `
    -ContentType "application/json" `
    -Body $body | ConvertTo-Json
```

#### Verify OTP:
```powershell
$body = @{
    phone_number = "+919876543210"
    otp = "123456"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/verify-otp" `
    -Method Post `
    -ContentType "application/json" `
    -Body $body

# Save token for later use
$token = $response.data.access_token
Write-Host "Token: $token"
```

#### Get Categories (with token):
```powershell
$headers = @{
    Authorization = "Bearer $token"
}

Invoke-RestMethod -Uri "http://localhost:8000/api/v1/categories" `
    -Method Get `
    -Headers $headers | ConvertTo-Json
```

---

### Option 2: Postman

1. Download Postman: https://www.postman.com/downloads/
2. Import the collection (create from endpoints above)
3. Set `{{base_url}}` variable to `http://localhost:8000/api/v1/`
4. Set `{{token}}` variable after login

---

### Option 3: cURL

#### Send OTP:
```bash
curl -X POST http://localhost:8000/api/v1/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{"phone_number": "+919876543210"}'
```

#### Get Categories (with token):
```bash
curl -X GET http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 🔍 Debugging Tips

### Check Laravel Logs:
```
C:\xampp\htdocs\thaedal-api\storage\logs\laravel.log
```

### Check if API is running:
```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/health"
```

### Test from Mobile Device:
1. Find your PC's IP: `ipconfig`
2. Connect phone to same WiFi network
3. Use IP address instead of localhost in Android app
4. Make sure Windows Firewall allows port 8000

---

## ⚠️ Common Issues

### Issue 1: "Connection Refused"
- **Solution:** Make sure Laravel server is running
  ```powershell
  cd C:\xampp\htdocs\thaedal-api
  php artisan serve --host=0.0.0.0 --port=8000
  ```

### Issue 2: "401 Unauthorized"
- **Solution:** Include valid Bearer token in Authorization header
- Token expires after 24 hours (configurable)

### Issue 3: "CORS Error" (from browser)
- **Solution:** Already configured in Laravel, but if issues persist, check `config/cors.php`

### Issue 4: Android app can't connect
- **Solution:** 
  - Use local IP (not localhost)
  - Disable Android app's SSL/HTTPS requirement for development
  - Check firewall settings

---

## 📊 Database Access

### Via phpMyAdmin:
```
http://localhost/phpmyadmin
```

### Database Name: `thaedal`

### Key Tables:
- `users` - App users
- `videos` - Video content
- `categories` - Video categories
- `subscriptions` - User subscriptions
- `payments` - Payment records
- `admins` - Admin panel users

---

## 🎯 Next Steps

1. ✅ Test health endpoint
2. ✅ Test OTP flow (send + verify)
3. ✅ Save access token
4. ✅ Test protected endpoints with token
5. ✅ Update Android app's BASE_URL
6. ✅ Test from Android app
7. ✅ Monitor Laravel logs for errors

---

## 📝 Sample Test Flow

```powershell
# 1. Check API is running
Invoke-RestMethod -Uri "http://localhost:8000/api/health"

# 2. Send OTP
$sendOtp = @{
    phone_number = "+919876543210"
} | ConvertTo-Json

$otpResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/send-otp" `
    -Method Post -ContentType "application/json" -Body $sendOtp

# 3. Verify OTP (use OTP from SMS or check logs)
$verifyOtp = @{
    phone_number = "+919876543210"
    otp = "123456"
} | ConvertTo-Json

$loginResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/verify-otp" `
    -Method Post -ContentType "application/json" -Body $verifyOtp

$token = $loginResponse.data.access_token

# 4. Get categories with token
$headers = @{ Authorization = "Bearer $token" }
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/categories" `
    -Headers $headers | ConvertTo-Json

# 5. Get videos
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/videos" `
    -Headers $headers | ConvertTo-Json
```

---

## 🎉 Success!

If all endpoints return proper JSON responses, your API integration is working correctly! 

Now you can connect your Android app and start testing the full flow.


