# Folio — Online Bookstore Enterprise Project (Aptech / TechWiz)

[![Flutter](https://img.shields.io/badge/Flutter-3.x-02569B?logo=flutter&logoColor=white)](bookstore_app)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](online-bookstore-api)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](database-backup)
[![Dart Analyzer](https://img.shields.io/badge/Analyzer-0%20Issues-brightgreen)](bookstore_app)
[![Flutter Tests](https://img.shields.io/badge/Flutter%20Tests-85%2F85%20Passed-brightgreen)](bookstore_app)
[![Laravel Tests](https://img.shields.io/badge/Laravel%20Tests-36%2F36%20Passed-brightgreen)](online-bookstore-api)

Folio is an enterprise-grade mobile bookstore application developed for the Aptech eProject curriculum. The project combines a **Flutter mobile application** (clean layered architecture with Provider and go_router) with a **Laravel 12 REST API** and **MySQL database**.

---

## 📁 Repository Structure

```
online_bookstore/
├── bookstore_app/                 # Flutter mobile client (iOS, Android, Desktop)
│   ├── lib/                       # Clean architecture (core/, data/, logic/, presentation/)
│   ├── test/                      # 85 automated unit, widget, and contract tests
│   └── docs/                      # Comprehensive technical and user documentation
│       ├── user-guide.md          # End-user handbook
│       ├── developer-guide.md     # Architecture and engineering reference
│       ├── api-contract.md        # Complete REST API specification
│       ├── video-script.md        # Demonstration video script
│       └── submission-notes.md    # Packaging & evaluation checklist
│
├── online-bookstore-api/          # Laravel 12 REST API backend
│   ├── app/                       # Controllers, Requests, Resources, Models, Middleware
│   ├── database/                  # Migrations (14 tables) and Enterprise Seeders
│   ├── routes/api.php             # 80 REST endpoints with Rate Limiting and Sanctum
│   └── tests/                     # 36 feature & unit tests
│
├── database-backup/               # SQL dumps for database restoration
│   ├── folio.sql                  # MySQL database dump (185 books, 32 authors, 10 categories)
│   └── README.txt                 # Export metadata and restore commands
│
└── PRODUCTION_AUDIT_REPORT.md     # Official deep audit report and TechWiz verification matrix
```

---

## ⚡ Quick Start

### Option A: Standalone Demo Mode (Zero Setup)
No database or server required. The Flutter app runs against its bundled reference mock engine:
```bash
cd bookstore_app
flutter pub get
flutter run --dart-define=API_MODE=mock
```

### Option B: Live Production Mode (Laravel API + MySQL)
1. **Start MySQL** via XAMPP (port 3306).
2. **Start the Laravel API**:
   ```bash
   cd online-bookstore-api
   php artisan serve --host=127.0.0.1 --port=8000
   ```
3. **Launch the Flutter Application**:
   ```bash
   cd bookstore_app
   # Windows Desktop / macOS / Linux:
   flutter run --dart-define=API_MODE=live --dart-define=API_BASE_URL=http://127.0.0.1:8000/api

   # Android Emulator:
   flutter run --dart-define=API_MODE=live --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
   ```

---

## 🔐 Demo Credentials

| Role | Email | Password | Access Rights |
|---|---|---|---|
| **Administrator** | `admin@folio.app` | `admin123` | Full Admin Dashboard, Book & Category CRUD, Order Status Management, User Access Control |
| **Customer** | `reader@folio.app` | `password123` | Book Discovery, Cart & Checkout, Order Tracking, Book Reviews, Wishlist |
| **Admin Alias** | `admin@bookstore.test` | `password` | Additional admin testing account |
| **Customer Alias** | `customer@bookstore.test` | `password` | Additional customer testing account |

---

## 🧪 Automated Test Verification

### Flutter Test Suite
```bash
cd bookstore_app
flutter test
# Result: 85 passed (01:13 +85: All tests passed!)
dart analyze
# Result: Analyzing bookstore_app... No issues found!
```

### Laravel Test Suite
```bash
cd online-bookstore-api
php artisan test
# Result: Tests: 36 passed (124 assertions), Duration: 5.77s
```

---

## 📋 Comprehensive Audit Report
For the complete technical audit, endpoint parity matrix, security hardening, and Aptech SRS compliance breakdown, read [PRODUCTION_AUDIT_REPORT.md](PRODUCTION_AUDIT_REPORT.md).
"# Boook_store" 
