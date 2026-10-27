# Open-Ended Problem (OEP) Project Report

**Project Title:** FindIt – Community Lost and Found Web Portal  
**Domain:** Web Application Development (PHP, MySQL, HTML5, CSS3, JavaScript)  
**Academic Year:** 2025–2026  

---

## 1. Problem Definition & Objectives

### 1.1 Problem Statement
In public environments such as university campuses, libraries, transport hubs, and community areas, hundreds of valuable personal items (wallets, identification cards, keys, textbooks, and electronic devices) are lost or misplaced every day. Traditional recovery methods—such as physical lost-and-found noticeboards, word-of-mouth inquiries, or fragmented social media groups—are inefficient, disorganized, and lack security verification.

### 1.2 Proposed Solution
**FindIt** is a centralized, secure, community-driven web application designed to reconnect individuals with their lost belongings. The portal allows:
- **Losers** to report missing items with descriptions, photos, and approximate dates/locations.
- **Finders** to post discovered items and coordinate safe returns.
- **Claimants** to submit structured proof-of-ownership inquiries without publicly exposing sensitive personal information.

---

## 2. Technology Stack

| Layer | Technologies Used | Purpose |
|---|---|---|
| **Frontend UI** | HTML5, CSS3 (Custom Responsive Design) | Clean, accessible, semantic structure with mobile-responsive design |
| **Client Scripting** | JavaScript (ES6+ Vanilla) | Real-time DOM updates, interactive modals, client-side field validation, async `fetch()` API calls |
| **Backend Processing** | PHP 8.x | RESTful JSON API (`api.php`), session authorization, file upload handling, and sanitization |
| **Database Layer** | MySQL / SQLite via PHP PDO | Persistent relational storage with full CRUD and parameterized SQL execution |

---

## 3. Database Architecture & CRUD Operations

### 3.1 Database Schema
The database consists of three primary interconnected tables:

1. **`users`**:
   - `id` (INT, Primary Key, Auto Increment)
   - `name` (VARCHAR), `email` (VARCHAR, Unique), `phone` (VARCHAR)
   - `password` (VARCHAR, Bcrypt Hash)
   - `role` (ENUM: `'user'`, `'admin'`)
   - `created_at` (TIMESTAMP)

2. **`items`**:
   - `id` (INT, Primary Key, Auto Increment)
   - `user_id` (INT, Foreign Key referencing `users(id)`, Nullable for guest posts)
   - `type` (ENUM: `'lost'`, `'found'`)
   - `title` (VARCHAR), `category` (VARCHAR), `description` (TEXT), `location` (VARCHAR)
   - `item_date` (DATE), `image_path` (VARCHAR, Nullable)
   - `contact_name` (VARCHAR), `contact_email` (VARCHAR), `contact_phone` (VARCHAR)
   - `status` (ENUM: `'active'`, `'claimed'`, `'returned'`)
   - `security_secret` (VARCHAR)
   - `created_at` (TIMESTAMP)

3. **`claims`**:
   - `id` (INT, Primary Key, Auto Increment)
   - `item_id` (INT, Foreign Key referencing `items(id)` ON DELETE CASCADE)
   - `claimant_name` (VARCHAR), `claimant_email` (VARCHAR), `claimant_phone` (VARCHAR)
   - `claim_message` (TEXT, Proof of Ownership)
   - `status` (ENUM: `'pending'`, `'approved'`, `'rejected'`)
   - `created_at` (TIMESTAMP)

### 3.2 CRUD Implementation Matrix

| Operation | Feature Description | Code Implementation |
|---|---|---|
| **INSERT** | Submitting lost/found item reports with photo uploads; submitting ownership claims; registering new user accounts. | `report.php`, `login.php?action=register`, `api.php?action=create_item`, `item.php?action=submit_claim` |
| **SEARCH** | Multi-attribute keyword search across titles, descriptions, and locations with real-time category filtering and date sorting. | `browse.php`, `index.php`, `api.php?action=get_items` |
| **UPDATE** | Modifying existing listings, changing item lifecycle status (*Active* &rarr; *Claimed* &rarr; *Returned*), and processing claim outcomes. | `report.php?id=...`, `item.php?action=status`, `item.php?action=claim_status`, `dashboard.php` |
| **DELETE** | Permanently deleting reports with automated cascade cleanup of associated claims and uploaded server images. | `item.php?action=delete` |

---

## 4. Key Functional Modules

### 4.1 Field Validations (Client & Server Side)
- **Email Validation**: RFC-compliant regular expression checks on client-side (`js/validation.js`) and `filter_var(..., FILTER_VALIDATE_EMAIL)` on server-side.
- **Date Verification**: Prevents future date selection for lost/found occurrences.
- **Secure File Upload**:
  - Restricts uploads to permitted MIME types (`image/jpeg`, `image/png`, `image/webp`, `image/gif`).
  - Limits file size to 5 MB maximum.
  - Implements randomized, sanitized filenames on disk to avoid directory traversal.
  - Includes a live JavaScript image preview with a 1-click removal option.

### 4.2 Session & Security Management
- **Session Lifecycle**: Secure session startup via `session_start()` and session regeneration (`session_regenerate_id()`) upon privilege elevation.
- **Password Security**: Passwords hashed using industry-standard `PASSWORD_BCRYPT`.
- **Role-Based Authorization**:
  - Regular authenticated users manage their own reported items and view claims received on them (`dashboard.php`).
  - Administrators maintain comprehensive oversight across all listings and claim requests (`admin.php`).
- **CSRF & Injection Defenses**:
  - Cryptographic tokens protect form submissions.
  - 100% of SQL operations utilize prepared statements with PDO parameter binding.
  - Input sanitization through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` mitigates Cross-Site Scripting (XSS).

---

## 5. Application Sitemap & Interlinking

```
[index.html / index.php] (Home Feed, Live Stats, Category Quick-Filters)
  ├── [browse.html / browse.php] (Full Search Catalog, Filters & Sorting)
  │     └── [Item Details & Claim Modal Dialog]
  ├── [report.html / report.php] (Post Lost or Found Report + Photo Upload)
  ├── [login.html / login.php] (User Authentication)
  │     └── [dashboard.php] (Manage Personal Items & Received Claims)
  │           └── [edit_item.php / delete_item.php]
  ├── [register.html / register.php] (Account Registration)
  └── [admin.php] (Administrative Moderation Panel)
```

---

## 6. Testing & Results

| Test Scenario | Input / Action | Expected Result | Actual Result | Status |
|---|---|---|---|:---:|
| **User Registration** | Valid name, unique email, 6+ char password | Account created, redirected to login | Success flash message shown | **PASS** |
| **Duplicate Email** | Existing registered email address | Form rejects with specific error | "Email is already registered" displayed | **PASS** |
| **Item Report** | Valid title, category, date, location, image | Item inserted into DB, image saved to `uploads/` | New card appears in catalog | **PASS** |
| **Invalid Image** | `.exe` or `.pdf` file upload | Blocked on both client & server | "Invalid file type. Allowed: JPG, PNG, WEBP, GIF" | **PASS** |
| **Keyword Search** | Typing "keys" in search bar | Results filter dynamically without full reload | Only matching key records displayed | **PASS** |
| **Claim Submission** | Submitting proof on found item | Record stored in `claims` table, toast alert | Reporter alerted in dashboard | **PASS** |
| **Unauthorized Access**| Accessing `dashboard.php` as guest | Immediate redirect to login | Redirected to `login.php` safely | **PASS** |

---

## 7. Conclusion & Future Enhancements

The **FindIt** portal demonstrates a complete, secure, and production-grade web application meeting all academic Open-Ended Problem requirements. It eliminates frontend PHP clutter through an HTML5/JavaScript architectural pattern while utilizing PHP and MySQL for resilient backend services.

**Future Scope:**
1. Real-time in-app notifications and email alerts via SMTP.
2. Geolocation mapping (Google Maps / Leaflet integration) to pin the exact recovery radius.
3. AI-based automated image matching between lost and found submissions.
