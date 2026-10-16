# FindIt - Lost & Found Web Application

A full-featured, secure, and modern **Lost & Found Portal** built with **PHP, HTML5, CSS3, and JavaScript**.

---

## 🌟 Key Features & Requirements Coverage

### 1. Database Operations (Full CRUD & Search)
- **INSERT**:
  - Report Lost or Found items with image uploads (`report.php`).
  - Submit proof of ownership claims or contact messages (`item.php`).
  - Register new user accounts (`register.php`).
- **SEARCH**:
  - Live search across titles, descriptions, and locations (`browse.php` & `index.php`).
  - Multi-criteria filtering by item type (*All*, *Lost*, *Found*, *Resolved*).
  - Category filtering (*Electronics*, *Wallets*, *Keys*, *Documents*, *Clothing*, *Pets*, *Books*, etc.).
  - Date sorting (*Newest First*, *Oldest First*).
- **UPDATE**:
  - Edit existing reports, replace images, and update contact information (`edit_item.php`).
  - Update report status (*Active* &rarr; *Claimed* &rarr; *Returned*) (`update_status.php`).
  - Approve or Reject ownership claims (`dashboard.php` & `admin.php`).
- **DELETE**:
  - Permanently remove item reports with cascade cleanup of associated claims and server image files (`delete_item.php`).
  - Security token (CSRF) verification on all deletions.

### 2. Form Field Validation & Styling
- **Client-Side Validation (`js/validation.js`)**:
  - Instant validation on `input` and `blur`.
  - Email format verification, phone number regex check, minimum string lengths, password confirmation check, future date prevention.
  - File upload validation (format check for JPG/PNG/WEBP/GIF and 5MB size limit) with **live image preview**.
- **Server-Side Validation**:
  - Rigorous PHP sanitization (`htmlspecialchars`, `filter_var`), required field validation, and error arrays displayed clearly on forms.
- **Modern Responsive Design (`css/style.css`)**:
  - CSS custom properties, cards, hero banner, status badges, sticky navigation, modal popups, interactive toast alerts, and responsive grid layouts.

### 3. Session & Role Management
- Session initialization (`session_start()`) with secure session regeneration (`session_regenerate_id`).
- User authentication (*Sign In*, *Registration*, *Sign Out*).
- Role-based authorization:
  - **Regular Users**: Can submit items, edit/delete their own reports, and approve/reject claims on their items.
  - **Administrators**: Complete oversight to moderate all items, process all claims, and view all registered users.
- Protected routes: Non-logged-in users are redirected to `login.php` when attempting restricted actions.
- CSRF Protection: Cryptographic tokens (`$_SESSION['csrf_token']`) verified on form submissions.

### 4. Interlinked Whole Working Application
- **Pages Included**:
  - `index.php`: Home page with hero, live statistics counters, quick action buttons, and latest reports.
  - `browse.php`: Dedicated search and filter catalog with result counts.
  - `report.php`: Multi-field submission form for Lost & Found items.
  - `item.php`: Detailed view with item photo, reporter contact, management controls, and claim submission modal.
  - `edit_item.php`: Full edit form with existing data pre-filled.
  - `delete_item.php`: Authorized deletion handler with image removal.
  - `update_status.php`: One-click status toggler.
  - `login.php`: Secure login with demo account autofill buttons.
  - `register.php`: New account creation with duplicate email checks.
  - `logout.php`: Session termination and clean redirect.
  - `dashboard.php`: User management portal (My Items, Status controls, Claims Received).
  - `admin.php`: Administrator oversight panel (System statistics, moderation table, all claims, user directory).
  - `api.php`: RESTful JSON API for async queries and claims.

---

## 🚀 How to Run the Application

### Option A: Using Built-in PHP Server (Zero Setup)
1. Open PowerShell or Terminal in this project directory:
   ```bash
   cd d:\downloadsD\antigravity\lost_and_found
   ```
2. Start the PHP server:
   ```bash
   php -S localhost:8000
   ```
3. Open your browser and navigate to:
   ```
   http://localhost:8000
   ```
> **Note:** By default, the application uses **SQLite** (`lost_and_found.db`), which automatically creates all tables and populates sample data on the first launch without needing MySQL!

---

### Option B: Using XAMPP / WAMP / Laragon
1. Copy or move the `lost_and_found` folder into your server's root folder:
   - **XAMPP**: `C:\xampp\htdocs\lost_and_found`
   - **WAMP**: `C:\wamp64\www\lost_and_found`
   - **Laragon**: `C:\laragon\www\lost_and_found`
2. Open your browser and navigate to:
   ```
   http://localhost/lost_and_found/
   ```

#### Switching to MySQL (Optional):
1. Open `config.php` and change `DB_DRIVER`:
   ```php
   define('DB_DRIVER', 'mysql');
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'lost_and_found');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) and import the included `database.sql` file.

---

## 🔑 Demo Login Accounts

| Role | Email | Password |
|---|---|---|
| **Regular User** | `john@example.com` | `password123` |
| **Administrator** | `admin@findit.local` | `admin123` |

*(You can also register a new account on the `register.php` page anytime).*

---

## 📁 Project Architecture

```
lost_and_found/
├── config.php                  # Configuration (database, uploads, session start)
├── db.php                      # PDO connection helper & auto-table creator
├── database.sql                # MySQL database schema & sample seed data
├── index.php                   # Home page (Hero, live stats, latest cards)
├── browse.php                  # Search & Filter catalog (SEARCH)
├── item.php                    # Item details & claim form (READ & INSERT claim)
├── report.php                  # Post lost/found item (INSERT)
├── edit_item.php               # Edit item details (UPDATE)
├── delete_item.php             # Delete item handler (DELETE)
├── update_status.php           # Status toggler (UPDATE)
├── login.php                   # Session login with validation
├── register.php                # Session registration with validation
├── logout.php                  # Session logout
├── dashboard.php               # User dashboard (My Items & Claims)
├── admin.php                   # Admin moderation panel
├── api.php                     # RESTful JSON API
├── includes/
│   ├── header.php              # Global navigation bar & session badges
│   ├── footer.php              # Global footer & quick links
│   ├── auth.php                # Authentication & role protection
│   └── functions.php           # Flash messages, CSRF, sanitization, image upload
├── css/
│   └── style.css               # Modern responsive styling
├── js/
│   ├── validation.js           # Client-side validation & live image preview
│   └── app.js                  # Modals, toasts, delete confirmation
└── uploads/                    # User uploaded photos
```
