:

# 🛒 E-Commerce Website
This is a feature-rich E-Commerce Website built with Laravel and Oracle Database designed to support a marketplace for customers and traders. It includes user management, product listings, shopping cart, order placement, PayPal payment simulation, and admin control.

## 🔧 Features
### 👥 User Roles
- Customer: Browse products, manage wishlist/cart, place orders, choose collection slots.
- Trader: Add/manage products, view and complete orders, access business reports.
- Admin: Verify traders, view statistics and reports, manage users and shops.

### 🛍️ Core Functionalities
- Product Catalog: Browse, search, and filter products.
- Wishlist & Cart: Add/remove items, adjust quantities.
- Order System: Place orders and select collection slots (Wed–Fri: 10–13, 13–16, 16–19, 24+ hours ahead).
- Payment Integration: PayPal Sandbox for simulated payments.
- Email Notifications: Confirmation email sent after successful payment.
- OTP Verification: Login requires OTP verification via email.
- Reports: View daily, weekly, and monthly sales reports (traders & admin).
- IoT Support: Traders can update stock via connected IoT systems.

##⚙️ Technologies Used
- Backend: Laravel 10+
- Database: Oracle
- Frontend: Blade, Bulma CSS
- Email & OTP: Smtp Mail
- Payment: Srmklive PayPal (Sandbox)
- Authentication: Custom session-based with email OTP
- IoT: Arduino Uno with RFID reader 

##🚀 Getting Started
### Prerequisites
- PHP 8.x
- Composer
- Oracle DB & PHP OCI8 driver

### 📦 Packages Used
- srmklive/paypal – PayPal integration
- yajra/laravel-oci8 – Oracle DB support



