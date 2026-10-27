# FindIt - Lost & Found Web Application

A modern, clean, and responsive **Lost & Found Portal** built with **HTML5, CSS3, JavaScript, and lightweight PHP**.

The frontend is structured around **clean HTML files** (`index.html`, `browse.html`, `report.html`, etc.) without overloading the templates with server-side PHP tags, while PHP is kept strictly for backend services (`api.php`, database operations, and authentication).

---

## 🌟 Key Architecture & Highlights

### 1. HTML5-First Frontend (Clean, No PHP Overload)
- **`index.html`**:
  - The flagship entry page written in pure semantic HTML5.
  - Interactive hero section with instant search and category select.
  - Real-time live statistics counters (Total Reports, Active Lost, Active Found, Resolved).
  - Quick category filter pills (Electronics, Wallets, Keys, Documents, Clothing, Pets, etc.).
  - Dynamic filter tabs (*All Recent*, *Lost Items*, *Found Items*, *Resolved*).
  - Instant live search input with dynamic cards grid.
  - Interactive "How It Works" 3-step community guide and safety guidelines.
  - Embedded claim dialog modal and item details modal.
  - Includes seed fallback data so it renders immediately even when double-clicked directly as a local file (`file:///`)!
- **`browse.html`**:
  - Dedicated search and filter catalog in pure HTML.
  - Live client-side filtering by keywords, item status, categories, and date sorting.
- **`report.html`**:
  - Clean HTML form with interactive "Lost" vs "Found" radio selector.
  - Drag-and-drop photo upload with live image preview and removal.
  - Client-side validation (`js/validation.js`) with instant feedback.
- **`login.html` & `register.html`**:
  - User-friendly authentication forms with client-side validation.

### 2. Lightweight Backend Services & RESTful API
- **`api.php`**:
  - Lightweight RESTful API returning structured JSON.
  - `action=get_items`: Fetch items filtered by type, category, query keyword, and sort order.
  - `action=get_stats`: Live counts of total, lost, found, and resolved items.
  - `action=submit_claim`: Secure processing of ownership claim inquiries.
  - `action=create_item`: Post new lost/found reports directly via AJAX or FormData.
  - `action=get_item`: Fetch single item details by ID.
- **`index.php`**:
  - Flagship entry point with compulsory login verification, live statistics counters, and recent reports.

### 3. Database Operations (Full CRUD)
- **INSERT**: Report items (`report.php`), submit claims (`item.php`), and create accounts (`login.php?action=register`).
- **SEARCH**: Live search across titles, descriptions, and locations (`browse.php`, `index.php`).
- **UPDATE**: Edit reports, upload photos, and update status (`report.php?id=...`, `item.php?action=status`).
- **DELETE**: Remove reports with cascade cleanup of associated claims and uploaded images (`item.php?action=delete`).

---

## 🚀 How to Run the Application

### Option A: Open `index.html` Directly in Any Browser
Double-click `index.html` in your file explorer (or open with Chrome, Edge, Firefox, or VS Code Live Server).
> The page renders instantly with fallback items and offline styling!

---

### Option B: Start Local Server with 1-Click
Double-click **`start-server.bat`** in the project folder. It will start the local server and automatically open:
```
http://localhost:8000/index.html
```

Or from PowerShell / Command Prompt:
```bash
cd d:\downloadsD\antigravity\lost_and_found
php -S localhost:8000
```
Then navigate to:
```
http://localhost:8000
```

> **Database Note:** Uses **SQLite** (`lost_and_found.db`) out-of-the-box with zero setup required. You can also switch to MySQL in `db.php` if desired.

---

## 📁 Project Directory Structure (Consolidated & Streamlined)

```
lost_and_found/
├── index.php                   # Flagship Portal Home (Compulsory login, stats, hero, category feed)
├── login.php                   # Unified Authentication (Sign In, Registration, Logout)
├── browse.php                  # Advanced Catalog Search & Multi-filter browser
├── report.php                  # Unified Form: Post New Item (INSERT) & Edit Listing (UPDATE)
├── item.php                    # Detailed Item View, Claim Submissions, Status Toggling & Deletions (CRUD)
├── dashboard.php               # Member Dashboard (Manage reports, review claims)
├── admin.php                   # Administrator Control Panel (Platform metrics, oversight)
├── api.php                     # RESTful JSON API endpoints (Secured with session authentication)
├── db.php                      # Unified Backend Core (Config, SQLite/PDO, Auth, Layout renderers)
├── database.sql                # MySQL schema backup
├── start-server.bat            # 1-click startup script (Launches local server to index.php)
├── css/
│   └── style.css               # Clean, modern, responsive CSS styling
├── js/
│   ├── app.js                  # Modals, live filtering, dynamic API fetch, toasts
│   └── validation.js           # Client-side form validation & live image preview
└── uploads/                    # User-uploaded photos
```
