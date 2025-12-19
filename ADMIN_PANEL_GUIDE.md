# 🎯 Thaedal Admin Panel & API Integration Guide

## ✅ Current Status
- ✅ Laravel Backend: Running on http://localhost:8000
- ✅ Admin Panel: Accessible
- ✅ API Endpoints: 46 routes configured
- ✅ Database: MySQL (thaedal)
- ✅ Authentication: Working

---

## 🔐 Admin Panel Access

### Login URL
```
http://localhost:8000/admin/login
```

### Default Credentials

| Role | Email | Password |
|------|-------|----------|
| **Super Admin** | admin@thaedal.com | admin123 |
| Regular Admin | admin2@thaedal.com | admin123 |
| Moderator | moderator@thaedal.com | moderator123 |

---

## 📊 Admin Panel Features

### 1. Dashboard
- **Real-time Statistics**
  - Total Users
  - Active Subscriptions
  - Total Videos
  - Total Revenue
- **Recent Activity**
  - New users today
  - Recent payments
  - Popular videos

### 2. User Management (`/admin/users`)
- View all users with pagination
- Search users by name/phone/email
- View user details (subscriptions, payments, watch history)
- Toggle subscription status
- Delete users

### 3. Video Management (`/admin/videos`)
- **List Videos**
  - Search by title/description/creator
  - Filter by category
  - View thumbnails and stats
- **Create Video**
  - Title, description, duration
  - Video URL (YouTube/Vimeo/custom)
  - Thumbnail URL
  - Category assignment
  - Creator information
  - Premium/Free toggle
  - Tags
- **Edit Video**
  - Update all video details
- **Delete Video**

### 4. Category Management (`/admin/categories`)
- List all categories with video counts
- Create new categories
- Edit category details
- Delete categories (if no videos)

### 5. Creator Management (`/admin/creators`)
- View all creators
- Creator statistics (videos, views, likes)
- View creator's videos

### 6. Subscription Management (`/admin/subscriptions`)
- View all subscriptions
- Filter by status (active/cancelled/expired)
- Search by user
- View subscription details
- Manage subscription plans

### 7. Payment Management (`/admin/payments`)
- View all payments
- Revenue statistics
- Filter by status (success/pending/failed)
- Search by transaction ID or user
- View payment details

---

## 🔧 API Integration with Android App

### Step 1: Find Your Local IP Address

**Windows PowerShell:**
```powershell
ipconfig
```

Look for **IPv4 Address** under your active network adapter (WiFi or Ethernet).

**Example Output:**
```
Wireless LAN adapter Wi-Fi:
   IPv4 Address. . . . . . : 192.168.1.100
```

### Step 2: Update Android App Configuration

Open this file in Android Studio:
```
app/src/main/java/com/innovfix/thaedal/data/remote/api/ApiConstants.kt
```

**Change from:**
```kotlin
object ApiConstants {
    const val BASE_URL = "http://localhost:8000/api/v1/"
    // ...
}
```

**Change to:**
```kotlin
object ApiConstants {
    const val BASE_URL = "http://192.168.1.100:8000/api/v1/"  // Use YOUR IP
    // ...
}
```

⚠️ **Important:** Replace `192.168.1.100` with YOUR actual local IP address!

### Step 3: Allow Network Access (If Needed)

If your phone can't connect, check:

1. **Same WiFi Network**
   - Phone and PC must be on the same network

2. **Windows Firewall**
   ```powershell
   # Run as Administrator
   New-NetFirewallRule -DisplayName "Laravel Dev Server" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
   ```

3. **Android Network Security Config**
   File: `app/src/main/res/xml/network_security_config.xml`
   (Already configured to allow HTTP for localhost and local IPs)

---

## 🧪 Testing API Endpoints

### Quick Health Check

**PowerShell:**
```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/health"
```

**Expected Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-11-25T18:00:00+05:30"
}
```

### Test Authentication Flow

#### 1. Send OTP
```powershell
$body = @{
    phone_number = "+919876543210"
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/send-otp" `
    -Method Post `
    -ContentType "application/json" `
    -Body $body | ConvertTo-Json
```

#### 2. Verify OTP
```powershell
$body = @{
    phone_number = "+919876543210"
    otp = "123456"  # Use the OTP from step 1 or SMS
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/verify-otp" `
    -Method Post `
    -ContentType "application/json" `
    -Body $body

# Save token
$token = $response.data.access_token
Write-Host "Access Token: $token"
```

#### 3. Get Protected Data
```powershell
$headers = @{
    Authorization = "Bearer $token"
}

# Get categories
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/categories" `
    -Headers $headers | ConvertTo-Json

# Get videos
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/videos" `
    -Headers $headers | ConvertTo-Json

# Get subscription plans
Invoke-RestMethod -Uri "http://localhost:8000/api/v1/subscriptions/plans" `
    -Headers $headers | ConvertTo-Json
```

---

## 📱 Complete Integration Flow

### 1. Start Laravel Server
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Populate Sample Data

**Option A: Via Admin Panel**
1. Login to admin panel
2. Go to Categories → Create categories
3. Go to Videos → Create videos
4. Go to Subscriptions → View plans

**Option B: Via Database Seeder**
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan db:seed
```

### 3. Test API from Android App
1. Update `ApiConstants.kt` with your local IP
2. Build and run the Android app
3. Try login with OTP
4. Browse videos
5. Test subscriptions

---

## 🗄️ Database Access

### phpMyAdmin
```
URL: http://localhost/phpmyadmin
Database: thaedal
```

### Key Tables

| Table | Description |
|-------|-------------|
| `users` | App users (phone auth) |
| `admins` | Admin panel users |
| `videos` | Video content |
| `categories` | Video categories |
| `subscriptions` | User subscriptions |
| `subscription_plans` | Available plans |
| `payments` | Payment records |
| `payment_methods` | User payment methods |
| `video_interactions` | Likes/dislikes |
| `comments` | Video comments |
| `watch_history` | User watch history |
| `fcm_tokens` | Firebase tokens |
| `otps` | OTP verification |

---

## 🛠️ Troubleshooting

### Issue 1: Admin Panel Shows 500 Error
**Solution:**
```powershell
# Clear cache
cd C:\xampp\htdocs\thaedal-api
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Issue 2: Android App Can't Connect
**Checklist:**
- [ ] Used local IP (not localhost)
- [ ] Phone and PC on same WiFi
- [ ] Windows Firewall allows port 8000
- [ ] Laravel server is running
- [ ] No typos in IP address

### Issue 3: "401 Unauthorized" on API
**Solution:**
- Get fresh token by logging in again
- Tokens expire after 24 hours (configurable)
- Check `Authorization: Bearer {token}` header is included

### Issue 4: Videos Not Showing
**Solution:**
1. Login to admin panel
2. Go to Videos section
3. Create sample videos
4. Or run: `php artisan db:seed`

### Issue 5: OTP Not Sending
**Check:**
- SMS service configuration in `.env`
- Or check Laravel logs for OTP code
- File: `C:\xampp\htdocs\thaedal-api\storage\logs\laravel.log`

---

## 📋 API Endpoints Quick Reference

### Authentication
- `POST /api/v1/auth/send-otp` - Send OTP
- `POST /api/v1/auth/verify-otp` - Verify OTP & Login
- `POST /api/v1/auth/logout` - Logout
- `POST /api/v1/auth/refresh` - Refresh token

### Videos
- `GET /api/v1/videos` - List videos
- `GET /api/v1/videos/{id}` - Video details
- `GET /api/v1/videos/category/{id}` - Videos by category
- `GET /api/v1/videos/search?q=` - Search videos
- `GET /api/v1/videos/new` - New releases
- `GET /api/v1/videos/top` - Top videos
- `GET /api/v1/videos/today` - Today's videos
- `POST /api/v1/videos/{id}/like` - Like video
- `POST /api/v1/videos/{id}/save` - Save video

### Categories
- `GET /api/v1/categories` - List categories

### User Profile
- `GET /api/v1/user/profile` - Get profile
- `PUT /api/v1/user/profile/update` - Update profile
- `GET /api/v1/user/watch-history` - Watch history
- `GET /api/v1/user/saved-videos` - Saved videos

### Subscriptions
- `GET /api/v1/subscriptions/plans` - List plans
- `GET /api/v1/subscriptions/my` - My subscription
- `POST /api/v1/subscriptions/subscribe` - Subscribe
- `POST /api/v1/subscriptions/cancel` - Cancel
- `POST /api/v1/subscriptions/autopay/enable` - Enable autopay
- `POST /api/v1/subscriptions/autopay/disable` - Disable autopay

### Payments
- `GET /api/v1/payments/methods` - Payment methods
- `POST /api/v1/payments/initiate` - Initiate payment
- `POST /api/v1/payments/verify` - Verify payment
- `GET /api/v1/payments/history` - Payment history

---

## 🎯 Next Steps

### For Development
1. ✅ Admin panel is ready
2. ✅ API endpoints are configured
3. ⏭️ Update Android app BASE_URL
4. ⏭️ Test login flow from app
5. ⏭️ Add real videos via admin panel
6. ⏭️ Configure payment gateway (Razorpay)
7. ⏭️ Configure SMS gateway (Twilio/MSG91)
8. ⏭️ Configure Firebase for push notifications

### For Production
1. Update `.env` with production values
2. Set `APP_DEBUG=false`
3. Configure proper database
4. Set up SSL/HTTPS
5. Configure payment gateway credentials
6. Configure SMS gateway credentials
7. Set up backup system
8. Configure monitoring & logging

---

## 📞 Support

### Laravel Logs
```
C:\xampp\htdocs\thaedal-api\storage\logs\laravel.log
```

### Useful Commands
```powershell
# Start server
php artisan serve --host=0.0.0.0 --port=8000

# View routes
php artisan route:list

# Clear cache
php artisan cache:clear

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Create admin
php artisan db:seed --class=AdminSeeder
```

---

## 🎉 Success!

Your Thaedal app now has:
- ✅ Full-featured admin panel
- ✅ RESTful API with 46 endpoints
- ✅ Authentication system
- ✅ Database structure
- ✅ Premium branding (Gold & Navy theme)

**Ready to manage your app content and integrate with Android!** 🚀


