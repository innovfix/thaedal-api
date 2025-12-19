# ⚡ QUICK START GUIDE

## 🎯 What You Have Now

✅ **Admin Panel** - Full-featured web dashboard  
✅ **REST API** - 46 endpoints for Android app  
✅ **Authentication** - OTP-based login system  
✅ **Database** - MySQL with 13 tables  
✅ **Premium UI** - Gold & Navy branding  

---

## 🚀 Access Admin Dashboard

### URL
```
http://localhost:8000/admin/login
```

### Login
```
Email: admin@thaedal.com
Password: admin123
```

---

## 📱 Connect Android App to API

### Step 1: Find Your IP
```powershell
ipconfig
```
Look for "IPv4 Address" (e.g., `192.168.1.100`)

### Step 2: Update Android App
File: `app/src/main/java/com/innovfix/thaedal/data/remote/api/ApiConstants.kt`

```kotlin
const val BASE_URL = "http://192.168.1.100:8000/api/v1/"  // Use YOUR IP
```

### Step 3: Test from Phone
- Connect phone to same WiFi
- Run Android app
- Try login with OTP

---

## ✅ Quick Test

### Test 1: Admin Panel
1. Open: http://localhost:8000/admin/login
2. Login with credentials above
3. Check Dashboard stats
4. Add a test video

### Test 2: API Health
```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/health"
```

Should return:
```json
{
  "status": "ok",
  "timestamp": "..."
}
```

### Test 3: API Routes
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan route:list --path=api
```

Should show 46 API routes

---

## 📋 What's Available in Admin Panel

| Section | What You Can Do |
|---------|----------------|
| **Dashboard** | View stats, recent users, payments |
| **Users** | Search, view details, manage subscriptions |
| **Videos** | Create, edit, delete videos |
| **Categories** | Manage video categories |
| **Creators** | View creators and their videos |
| **Subscriptions** | View all subscriptions and plans |
| **Payments** | Track payments and revenue |

---

## 🔧 Server Management

### Start Server
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan serve --host=0.0.0.0 --port=8000
```

### Stop Server
Press `Ctrl+C` in the terminal

### Check if Running
```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/health"
```

---

## 📖 Full Documentation

| Document | Description |
|----------|-------------|
| `ADMIN_PANEL_GUIDE.md` | Complete admin panel & API guide |
| `TEST_API.md` | Detailed API testing instructions |
| `TEST_API_SIMPLE.ps1` | Automated test script |
| `LARAVEL_BACKEND_SETUP.md` | Backend architecture details |

---

## ⚠️ Common Issues

### "Can't access admin panel"
**Solution:** Make sure server is running
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan serve --host=0.0.0.0 --port=8000
```

### "Android app can't connect"
**Solution:** 
1. Use local IP (not localhost)
2. Check same WiFi network
3. Allow port 8000 in firewall

### "401 Unauthorized on API"
**Solution:** Login first to get access token

---

## 🎯 Next Actions

1. **Populate Data**
   - Login to admin panel
   - Add categories
   - Add videos
   - Or run: `php artisan db:seed`

2. **Test Android App**
   - Update BASE_URL with your IP
   - Build and run app
   - Test login and video browsing

3. **Configure Services** (Production)
   - SMS Gateway (Twilio/MSG91)
   - Payment Gateway (Razorpay)
   - Firebase (Push notifications)

---

## 📞 Need Help?

### Check Logs
```
C:\xampp\htdocs\thaedal-api\storage\logs\laravel.log
```

### Database Access
```
http://localhost/phpmyadmin
Database: thaedal
```

### Clear Cache
```powershell
cd C:\xampp\htdocs\thaedal-api
php artisan cache:clear
php artisan config:clear
```

---

## 🎉 You're All Set!

The admin panel is now accessible in your browser, and the API is ready to connect with your Android app!

**Enjoy building your app! 🚀**


