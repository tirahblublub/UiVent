# UiVent — University Event Management System

![PHP](https://img.shields.io/badge/PHP-8.2-blue) ![MySQL](https://img.shields.io/badge/MySQL-8.0-orange) ![XAMPP](https://img.shields.io/badge/XAMPP-Required-red) ![ToyyibPay](https://img.shields.io/badge/Payment-ToyyibPay-green)

UiVent is a web-based event management system built for university students and club administrators. It supports event registration, merchandise orders, payment processing via ToyyibPay, attendance tracking, certificates, merits, and more.

---

## Table of Contents

- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Payment Setup (ToyyibPay)](#payment-setup-toyyibpay)
- [Roles & Access](#roles--access)
- [Project Structure](#project-structure)
- [Known Limitations](#known-limitations)
- [Troubleshooting](#troubleshooting)

---

## Features

**Student Portal**
- Browse and register for events
- Pay registration fees via ToyyibPay (FPX, Card, e-Wallet)
- View payment history and outstanding dues
- Purchase merchandise
- Track attendance
- Download certificates
- Submit event feedback
- View merit points

**Club Admin Portal**
- Create and manage events
- View registrations and attendance
- Manage payment settlements
- Send announcements
- Issue certificates and merits
- Manage merchandise

**Super Admin Portal**
- Manage campuses, clubs, and admins
- View system-wide analytics
- Global broadcast messages
- Audit logs
- Blacklist management
- Global configuration

---

## System Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| MySQL | 8.0+ |
| Apache | 2.4+ |
| XAMPP | Latest |
| Browser | Chrome / Edge (recommended) |

---

## Installation

**Step 1 — Clone or extract project**

Place the `UiVent` folder inside your XAMPP htdocs directory:

```
C:\xampp\htdocs\UiVent\
```

**Step 2 — Start XAMPP**

Open XAMPP Control Panel and start:
- Apache
- MySQL

**Step 3 — Access the system**

```
http://localhost/UiVent/
```

---

## Database Setup

**Step 1 — Open phpMyAdmin**

```
http://localhost/phpmyadmin
```

**Step 2 — Create database**

```sql
CREATE DATABASE uivent_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Step 3 — Import SQL dump**

- Select `uivent_db`
- Click **Import**
- Choose `uivent_db.sql` from the project folder
- Click **Go**

**Step 4 — Verify tables**

You should see 34 tables including: `events`, `students`, `admins`, `registrations`, `payment_transactions`, `merchandise`, `merch_orders`, etc.

---

## Configuration

Edit `config.php` in the project root:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password (empty by default in XAMPP)
define('DB_NAME', 'uivent_db');
```

---

## Payment Setup (ToyyibPay)

UiVent uses **ToyyibPay** as the payment gateway.

**Step 1 — Create sandbox account**

Register at [https://dev.toyyibpay.com](https://dev.toyyibpay.com)

**Step 2 — Get credentials**

- Go to **Settings → Secret Key** — copy your Secret Key
- Go to **My Categories** — note your Category Codes

**Step 3 — Update `users/toyyib_config.php`**

```php
define('TOYYIB_SANDBOX', true);  // change to false for production
define('TOYYIB_SECRET_KEY',              'your-secret-key-here');
define('TOYYIB_CATEGORY_CODE_EVENT',     'your-event-category-code');
define('TOYYIB_CATEGORY_CODE_MERCHANDISE', 'your-merch-category-code');
define('APP_BASE_URL', 'http://localhost/UiVent'); // change when using ngrok
```

### Localhost Callback — ngrok Setup

> ToyyibPay needs to call your callback URL from the internet. `localhost` is not reachable externally, so you need **ngrok** for local testing.

**Step 1 — Download ngrok**

[https://ngrok.com/download](https://ngrok.com/download)

**Step 2 — Add auth token**

```bash
ngrok config add-authtoken YOUR_NGROK_TOKEN
```

**Step 3 — Start ngrok**

```bash
ngrok http 80
```

**Step 4 — Copy the HTTPS URL** (e.g. `https://abc123.ngrok-free.app`)

**Step 5 — Update `toyyib_config.php`**

```php
define('APP_BASE_URL', 'https://abc123.ngrok-free.app/UiVent');
```

**Step 6 — Access UiVent via ngrok URL**

```
https://abc123.ngrok-free.app/UiVent/users/events.php
```

> **Note:** ngrok URL changes every restart. Update `APP_BASE_URL` each time.

---

## Roles & Access

| Role | Login URL | Description |
|------|-----------|-------------|
| Student | `/UiVent/index.php` | Register for events, pay fees, buy merch |
| Club Admin | `/UiVent/index.php` | Manage club events, payments, members |
| Super Admin | `/UiVent/superadmin/` | System-wide management |

---

## Project Structure

```
UiVent/
├── config.php                  # DB connection & session helpers
├── index.php                   # Login page (student & admin)
├── logout.php                  # Logout
├── toyyib_callback.php         # ToyyibPay server callback (auto payment update)
├── toyyib_return.php           # ToyyibPay return redirect after payment
│
├── users/                      # Student portal
│   ├── toyyib_config.php       # ToyyibPay credentials
│   ├── events.php              # Browse & register events
│   ├── payments.php            # Payment history & outstanding dues
│   ├── create_bill.php         # Create ToyyibPay bill & redirect
│   ├── register_event.php      # AJAX event registration handler
│   ├── merchandise.php         # Browse & order merchandise
│   ├── mybookings.php          # My registrations
│   ├── attendance.php          # Attendance history
│   ├── certificates.php        # View & download certificates
│   ├── feedback.php            # Submit event feedback
│   ├── announcements.php       # View announcements
│   ├── profile.php             # Student profile
│   └── images/                 # Merchandise & profile images
│
├── admin/                      # Club admin portal
│   ├── admin_dashboard.php     # Dashboard
│   ├── create_event.php        # Create/edit events
│   ├── my_events.php           # Manage club events
│   ├── registrations.php       # View event registrations
│   ├── attendance.php          # Manage attendance & QR scan
│   ├── payments.php            # Payment settlement
│   ├── payment_details.php     # Print payment receipt
│   ├── merchandise.php         # Manage merchandise
│   ├── certificates.php        # Issue certificates
│   ├── merits.php              # Award merit points
│   ├── announcements.php       # Send announcements
│   ├── reports.php             # Event reports
│   └── partials/               # Sidebar, topbar, styles
│
└── superadmin/                 # Super admin portal
    ├── command_centre.php      # Dashboard
    ├── admin_management.php    # Manage club admins
    ├── student_management.php  # Manage students
    ├── global_events.php       # View all events
    ├── analytics.php           # System analytics
    ├── audit_log.php           # Activity audit log
    ├── global_broadcast.php    # Send broadcast messages
    ├── billing_settlement.php  # All payment settlements
    ├── blacklist_management.php # Blacklist management
    └── global_config.php       # System configuration
```

---

## Known Limitations

- **Localhost callback** — ToyyibPay cannot call `localhost` for payment callbacks. Use ngrok for local development (see [Payment Setup](#payment-setup-toyyibpay)).
- **ngrok URL** — Changes on every restart. Must update `APP_BASE_URL` in `toyyib_config.php` each time.
- **PDO named parameters** — PHP PDO with `ATTR_EMULATE_PREPARES = false` does not allow duplicate named parameters (`:sid`) in a single query. Use positional parameters (`?`) instead.
- **club_id NULL** — Events without a `club_id` will not automatically create payment transactions. Ensure all events have a valid `club_id`.

---

## Troubleshooting

**"Tidak dapat sambung ke pangkalan data" (Demo data showing)**

- Ensure MySQL is running in XAMPP Control Panel
- Check `config.php` DB credentials
- Verify database name is `uivent_db`
- Test: `http://localhost/UiVent/test_db.php`

**"Payment Gateway Error — KEY-DID-NOT-EXIST"**

- Check `users/toyyib_config.php` — ensure `TOYYIB_SECRET_KEY` is correct
- Log in to [dev.toyyibpay.com](https://dev.toyyibpay.com) → Settings → Secret Key
- Make sure you're using **sandbox** key with `TOYYIB_SANDBOX = true`

**Payment successful but button still shows**

- ToyyibPay callback could not reach localhost
- Set up ngrok and update `APP_BASE_URL` in `toyyib_config.php`
- Or manually update in phpMyAdmin:
  ```sql
  UPDATE payment_transactions SET payment_status = 'Paid', paid_at = NOW()
  WHERE student_id = ? AND event_id = ? AND payment_status = 'Pending';
  ```

**"SQLSTATE[HY093]: Invalid parameter number"**

- Do not use the same named parameter (`:sid`) more than once in a PDO query
- Use positional parameters (`?`) instead:
  ```php
  $stmt->execute([$sid, $sid]);
  ```

**Events showing demo data instead of real events**

- Check `users/events.php` — remove `die('DB ERROR...')` debug line if added
- Ensure `payment_transactions` query uses positional `?` not named `:sid`

---

## Credits

Developed as a university project for UiTM event management.
Built with PHP, MySQL, Tailwind CSS, ToyyibPay, and XAMPP.