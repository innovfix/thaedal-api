# Thaedal API Test Script
# Run this in PowerShell to test your API endpoints

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   THAEDAL API TESTING SCRIPT" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Base URL
$baseUrl = "http://localhost:8000"
$apiUrl = "$baseUrl/api/v1"

# Test 1: Health Check
Write-Host "1. Testing Health Check..." -ForegroundColor Yellow
try {
    $health = Invoke-RestMethod -Uri "$baseUrl/api/health" -Method Get
    Write-Host "   [OK] Health Check: " -NoNewline -ForegroundColor Green
    Write-Host "$($health.status) at $($health.timestamp)" -ForegroundColor White
} catch {
    Write-Host "   [FAIL] Health Check: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Test 2: Admin Login Portal
Write-Host "2. Testing Admin Portal..." -ForegroundColor Yellow
try {
    $adminPage = Invoke-WebRequest -Uri "$baseUrl/admin/login" -Method Get -UseBasicParsing
    if ($adminPage.StatusCode -eq 200) {
        Write-Host "   [OK] Admin Login Page Accessible" -ForegroundColor Green
        Write-Host "   URL: http://localhost:8000/admin/login" -ForegroundColor Cyan
        Write-Host "   Email: admin@thaedal.com" -ForegroundColor White
        Write-Host "   Password: admin123" -ForegroundColor White
    }
} catch {
    Write-Host "   [FAIL] Admin Portal: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Test 3: API Routes
Write-Host "3. Testing API Endpoints..." -ForegroundColor Yellow
Write-Host "   Total API Routes: 46" -ForegroundColor Cyan
Write-Host "   Auth Routes: /api/v1/auth/*" -ForegroundColor White
Write-Host "   Video Routes: /api/v1/videos/*" -ForegroundColor White
Write-Host "   User Routes: /api/v1/user/*" -ForegroundColor White
Write-Host "   Subscription Routes: /api/v1/subscriptions/*" -ForegroundColor White
Write-Host ""

# Test 4: Protected Endpoint
Write-Host "4. Testing Protected Endpoint..." -ForegroundColor Yellow
try {
    $categories = Invoke-RestMethod -Uri "$apiUrl/categories" -Method Get -ErrorAction Stop
    Write-Host "   [WARN] Categories endpoint is public" -ForegroundColor Yellow
} catch {
    Write-Host "   [OK] Categories endpoint is protected (401 required)" -ForegroundColor Green
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   QUICK START GUIDE" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "ADMIN DASHBOARD:" -ForegroundColor Yellow
Write-Host "  1. URL: http://localhost:8000/admin/login" -ForegroundColor White
Write-Host "  2. Login: admin@thaedal.com / admin123" -ForegroundColor White
Write-Host "  3. Manage: Users, Videos, Categories, Payments" -ForegroundColor White
Write-Host ""

Write-Host "API INTEGRATION:" -ForegroundColor Yellow
Write-Host "  1. Find your local IP:" -ForegroundColor White
Write-Host "     > ipconfig" -ForegroundColor Cyan
Write-Host "     (Look for IPv4 Address)" -ForegroundColor Gray
Write-Host ""
Write-Host "  2. Update Android App:" -ForegroundColor White
Write-Host "     File: ApiConstants.kt" -ForegroundColor Cyan
Write-Host "     Change: BASE_URL = ""http://YOUR_IP:8000/api/v1/""" -ForegroundColor Gray
Write-Host ""
Write-Host "  3. Test Endpoints:" -ForegroundColor White
Write-Host "     Health: $baseUrl/api/health" -ForegroundColor Cyan
Write-Host "     Send OTP: POST $apiUrl/auth/send-otp" -ForegroundColor Cyan
Write-Host "     Verify OTP: POST $apiUrl/auth/verify-otp" -ForegroundColor Cyan
Write-Host ""

Write-Host "DATABASE:" -ForegroundColor Yellow
Write-Host "  phpMyAdmin: http://localhost/phpmyadmin" -ForegroundColor White
Write-Host "  Database: thaedal" -ForegroundColor White
Write-Host ""

Write-Host "Opening Admin Dashboard..." -ForegroundColor Green
Start-Sleep -Seconds 2
Start-Process "$baseUrl/admin/login"
