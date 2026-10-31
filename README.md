# AgriTrace+ | Agricultural & Livestock Biosecurity Monitoring System

AgriTrace+ is a full-featured web-based livestock tracking, farm registry, biosecurity incident management, and public reporting platform tailored for municipal and regional agriculture offices (specifically Department of Agriculture & Municipal Agriculture Offices).

---

## 🌟 Key Features

- **Multi-Role Portal Access**:
  - **Admin**: Complete system governance, user approvals, farm approvals, role assignments, audit logging, and data exports.
  - **Agriculture Official (DA / MAO)**: Incident resolution, geo-spatial quarantine mapping, real-time analytics, outbreak inspection, and live farmer communication.
  - **Farmer**: Farm registration, animal health logs, live vet consultations, interactive chat with DA officials, and incident reporting.
  - **Public Citizen**: Anonymous/public geo-tagged disease and stray animal reporting with photo/ID verification.
- **Biosecurity & Incident Tracking**: Real-time quarantine zone buffer rings, disease outbreak status tracking, and automated DA notifications.
- **Interactive Mapping**: Leaflet-based geospatial maps with farm pins, heatmaps, and quarantine radius rings.
- **Real-Time Notification Engine**: Instant polling and alert badges for approvals, critical alerts, and public submissions.
- **Responsive Mobile & Desktop Design**: Native-feeling mobile layout with optimized tables, hidden scrollbars on touch devices, and accessible touch controls.

---

## 🚀 Tech Stack

- **Backend**: PHP 8.x (Native PDO, REST API endpoints)
- **Database**: MySQL / MariaDB (InnoDB, spatial coords)
- **Frontend**: HTML5, CSS3 (Modern Glassmorphism & Responsive Grids), Vanilla JavaScript, Bootstrap Icons
- **Mapping**: Leaflet.js / OpenStreetMap
- **Web Server**: Apache (XAMPP / Production LAMP)

---

## 📦 Local Installation & Setup

### 1. Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.0+ and MySQL)
- Git (or download as ZIP)

### 2. Setup in XAMPP
1. Clone or extract this repository into your XAMPP web root:
   ```bash
   cd C:\xampp\htdocs
   git clone <your-repository-url> AG
   ```
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 3. Database Setup
1. Open **phpMyAdmin** at `http://localhost/phpmyadmin/`.
2. Create a database named `myproject4` (or `agritrace` according to your `db_connect.php`).
3. Import the schema file located at:
   ```
   database/database.sql
   ```
4. Verify your database connection credentials in `db_connect.php`:
   ```php
   $host = 'localhost';
   $db   = 'myproject4';
   $user = 'root';
   $pass = '';
   ```

### 4. Launch the System
Open your web browser and navigate to:
```
http://localhost/AG/
```
- **Login Portal**: `http://localhost/AG/pages/login.php`
- **Public Report Form**: `http://localhost/AG/pages/public-report-form.php`

---

## 📁 Project Structure

```
├── api/                   # Backend JSON REST endpoints
├── assets/                # CSS styles, images, translator JS
├── database/              # SQL schema bootstrap scripts
├── maps/                  # GeoJSON boundary data
├── pages/                 # Role portals & core views
│   ├── admin.php          # Central Admin Governance Portal
│   ├── agriDA.php         # Agriculture Official Dashboard & Mapping
│   ├── farmer.php         # Farmer Management Dashboard & Chat
│   ├── login.php          # Unified Authentication Portal
│   ├── public-report-form.php # Citizen Incident Submission Form
│   └── register-form.php  # Public Citizen & Farmer Onboarding
├── uploads/               # User media & evidence storage
├── db_connect.php         # PDO Database Connection
├── .gitignore             # Git ignored assets & temporary files
└── README.md              # Documentation & quickstart
```

---

## 🔒 License & Security
Developed for Department of Agriculture Camarines Sur — 1st District. All rights reserved &copy; 2026 AgriTrace Technologies.

