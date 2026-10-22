Folio Online Bookstore — Database Backup
=========================================
Export Date: 2026-10-21 19:35:58
Database Name: online_bookstore
DBMS: MySQL (XAMPP / MariaDB compatible)
Engine: InnoDB, utf8mb4_unicode_ci

Method Used:
mysqldump -u root online_bookstore > database-backup/folio.sql

Restore Instructions:
1. Ensure MySQL is running on localhost:3306.
2. Create the database:
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS online_bookstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
3. Import the backup:
   mysql -u root online_bookstore < database-backup/folio.sql

Included Data:
- 185 Enterprise-grade Books with real titles, prices, descriptions, ratings, and covers
- 32 Renowned Authors
- 10 Curated Book Categories
- 168 Verified Customer Reviews
- 7 Demo User Accounts:
    * Admin: admin@folio.app (Password: admin123)
    * Customer: reader@folio.app (Password: password123)
    * Admin Alias: admin@bookstore.test (Password: password)
    * Customer Alias: customer@bookstore.test (Password: password)
- 14 Database Tables with foreign keys, unique constraints, and indices
- Full order tracking, cart items, wishlist, addresses, payment metadata, notifications, and FAQs.
