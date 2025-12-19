# Thaedal API

Backend API for Thaedal - Tamil Educational Video Platform

## 🛠️ Tech Stack

- **Framework:** Laravel 11
- **PHP:** 8.2+
- **Database:** MySQL 8.0+
- **Authentication:** Laravel Sanctum
- **Payments:** Razorpay
- **Push Notifications:** Firebase Cloud Messaging

## 📦 Installation

### 1. Clone & Install Dependencies

```bash
cd thaedal-api
composer install
```

### 2. Environment Setup

```bash
# Copy environment file
copy env.example.txt .env

# Generate application key
php artisan key:generate
```

### 3. Database Setup

```bash
# Create database in MySQL
mysql -u root -p
CREATE DATABASE thaedal;
exit;

# Run migrations
php artisan migrate

# Seed sample data
php artisan db:seed
```

### 4. Start Development Server

```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api/v1/`

## 🔌 API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/send-otp` | Send OTP to phone |
| POST | `/api/v1/auth/verify-otp` | Verify OTP & login |
| POST | `/api/v1/auth/logout` | Logout user |

### User
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/user/profile` | Get user profile |
| PUT | `/api/v1/user/profile/update` | Update profile |
| POST | `/api/v1/user/avatar` | Upload avatar |
| GET | `/api/v1/user/saved-videos` | Get saved videos |
| GET | `/api/v1/user/watch-history` | Get watch history |

### Videos
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/videos` | List all videos |
| GET | `/api/v1/videos/top` | Get top videos |
| GET | `/api/v1/videos/new` | Get new releases |
| GET | `/api/v1/videos/today` | Get today's videos |
| GET | `/api/v1/videos/search` | Search videos |
| GET | `/api/v1/videos/{id}` | Get video details |
| GET | `/api/v1/videos/category/{id}` | Videos by category |
| POST | `/api/v1/videos/{id}/like` | Like video |
| DELETE | `/api/v1/videos/{id}/like` | Unlike video |
| POST | `/api/v1/videos/{id}/save` | Save video |
| DELETE | `/api/v1/videos/{id}/unsave` | Unsave video |

### Categories
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/categories` | List all categories |

### Subscriptions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/subscriptions/plans` | Get subscription plans |
| GET | `/api/v1/subscriptions/my` | Get my subscription |
| POST | `/api/v1/subscriptions/subscribe` | Subscribe to plan |
| POST | `/api/v1/subscriptions/cancel` | Cancel subscription |
| POST | `/api/v1/subscriptions/autopay/enable` | Enable autopay |
| POST | `/api/v1/subscriptions/autopay/disable` | Disable autopay |

### Payments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/payments/methods` | Get payment methods |
| POST | `/api/v1/payments/methods/add` | Add payment method |
| DELETE | `/api/v1/payments/methods/{id}/remove` | Remove method |
| GET | `/api/v1/payments/history` | Payment history |
| POST | `/api/v1/payments/initiate` | Initiate payment |
| POST | `/api/v1/payments/verify` | Verify payment |

## 📁 Project Structure

```
thaedal-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/    # API Controllers
│   │   └── Resources/          # API Resources (JSON transformers)
│   ├── Models/                 # Eloquent Models
│   └── Services/               # Business Logic Services
├── config/                     # Configuration files
├── database/
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── routes/
│   └── api.php                 # API routes
└── storage/
    └── app/
        └── firebase-credentials.json  # Firebase config
```

## 🔧 Configuration

### Firebase (Push Notifications)
1. Create Firebase project
2. Download service account JSON
3. Save as `storage/app/firebase-credentials.json`
4. Update `.env`:
```
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=your-project-id
```

### Razorpay (Payments)
1. Create Razorpay account
2. Get API keys from Dashboard
3. Update `.env`:
```
RAZORPAY_KEY_ID=rzp_test_xxxxx
RAZORPAY_KEY_SECRET=your_secret
```

### SMS Gateway
For development, SMS is logged. For production:

**Twilio:**
```
SMS_GATEWAY=twilio
TWILIO_SID=your_sid
TWILIO_AUTH_TOKEN=your_token
TWILIO_PHONE_NUMBER=+1234567890
```

**MSG91 (India):**
```
SMS_GATEWAY=msg91
MSG91_AUTH_KEY=your_key
MSG91_SENDER_ID=THAEDL
MSG91_TEMPLATE_ID=your_template
```

## 🚀 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure MySQL credentials
- [ ] Set up SSL certificate
- [ ] Configure Firebase
- [ ] Configure Razorpay (live keys)
- [ ] Configure SMS gateway
- [ ] Set up queue worker
- [ ] Configure cron for scheduled tasks

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name api.thaedal.com;
    root /var/www/thaedal-api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📞 Support

- Email: support@thaedal.com
- Documentation: See `LARAVEL_BACKEND_SETUP.md` in Android project

---

Built with ❤️ for Thaedal

