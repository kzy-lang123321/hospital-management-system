# 🏥 Hospital Management System (HMS)

> An enterprise, centralized, multi-departmental Hospital Management System built around a single patient-centered clinical and financial workflow.

---

## 🌟 Overview & Core Design Principle

Most hospital software fails because it is designed as disjointed, siloed modules. In this system, every single event, transaction, record, and service is bound to an immutable anchor:

$$\text{PATIENT (MRN)} \longrightarrow \text{ENCOUNTER} \longrightarrow \text{CLINICAL CARE} \longrightarrow \text{SERVICES (Rx / Lab)} \longrightarrow \text{CENTRALIZED BILLING} \longrightarrow \text{DISCHARGE}$$

---

## 🚀 Key Modules Included

1. **Executive & Role Dashboard**: Real-time bed occupancy meter, daily revenue collection counter, active consultation queue, and ward census.
2. **Master Patient Index (MRN)**: Unique sequence (`MRN-YYYY-XXXX`) generated atomically, full demographics, CNIC, and clinical drug allergy tracking.
3. **Patient 360° Profile**: Longitudinal medical history across 5 dedicated tabs (*Encounters, Vitals Charting, Prescriptions, Lab Reports, Invoices*).
4. **Appointments & Scheduling**: Doctor availability schedules, token queue numbers, and one-click patient check-in.
5. **Clinician Workbench (OPD)**: Vitals entry, SOAP clinical notes (with ICD-10 suggestions), CPOE electronic prescription writer with **allergy conflict warnings**, and diagnostic lab orders.
6. **Interactive Bed Rack Matrix (IPD)**: Color-coded visual bed cards (*Available, Occupied, Reserved, Cleaning/Turnaround*) with direct admission and discharge workflows.
7. **Pharmacy & Dispensing**: Closed-loop electronic prescription queue with **First Expiring, First Out (FEFO)** batch deduction.
8. **Laboratory & Pathology**: Specimen intake, barcode labeling, parameter entry with **automatic panic/abnormal value flags**, and pathologist verification.
9. **Centralized Billing & Cashier Desk**: Automated charge aggregation, cash drawer payment settlement, and thermal/A4 printable official fiscal receipts.
10. **Reports & Analytics**: Cashier closeout by tender type, Ward Bed Occupancy Rate (BOR), and doctor consultation revenue share.
11. **Hospital Masters & Settings**: 100% dynamic control panel to configure hospital identity, departments, wards, beds, tariffs, formulary, and test catalogs.
12. **Forensic Security & Audit Trail**: Immutable log recording user logins, record creations/edits, client IP addresses, and JSON diffs.
13. **Universal Patient Search (`Ctrl + /`)**: Topbar instant patient search by MRN, Name, Phone, or National ID.

---

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+ / PHP 8.x (Clean modular MVC architecture with strict PDO prepared statements)
- **Database Engine**: MariaDB 10.4+ / MySQL 8.0+ (InnoDB, UTF8mb4 charset, strict foreign keys)
- **Frontend Layer**: Responsive modern UI using Tailwind CSS, FontAwesome 6, and Inter typography
- **Server Environment**: Apache 2.4+ (XAMPP compatible)

---

## 📦 Database Schema Setup

The database schema and clean operational foundations are located in `/database`:
- `database/schema.sql`: 37 normalized relational tables across 10 architectural domains.
- `database/clean_transactions.sql`: Clean operational foundation with master data (Departments, Wards, Beds, Formulary, Lab Catalog, Tariffs).

### Import via Terminal or phpMyAdmin:
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/clean_transactions.sql
```

---

## 🖥️ Desktop Application

The Hospital Management System can be launched as a native desktop application with two options:

### Option 1: Electron Desktop App (Recommended for packaging & custom native menus)
```bash
# Install dependencies (first time only)
npm install

# Run Desktop Application
npm start
```

### Option 2: 1-Click Instant Windows Desktop Launcher (Zero NPM install required)
- Double click **`HMS-Desktop.bat`** (or **`HMS-Desktop-Silent.vbs`** for borderless silent start).
- Automatically verifies MySQL, starts the local PHP server, and opens in a standalone, dedicated desktop window.
- To create a Windows Desktop icon, run:
  ```powershell
  powershell -ExecutionPolicy Bypass -File .\create-desktop-shortcut.ps1
  ```

---

## 🔑 Default Seeded Accounts

All accounts use the default password: `Admin@123`

| Role | Username | Department |
| :--- | :--- | :--- |
| **Super Administrator** | `superadmin` | Executive Administration |
| **Consultant Cardiologist** | `dr.tariq` | Cardiology |
| **Consultant Physician** | `dr.ayesha` | General Medicine |
| **Consultant Pediatrician** | `dr.bilal` | Pediatrics |
| **Head Ward Nurse** | `nurse.fatima` | Inpatient Care / Wards |
| **Chief Pharmacist** | `pharm.kamran` | Pharmacy & Formulary |
| **Clinical Pathologist** | `lab.usman` | Pathology & Laboratory |
| **Senior Cashier** | `cashier.hassan` | Billing & Finance |
| **Front Desk Receptionist**| `reception.sana` | Front Desk Reception |

*(The login screen includes **Quick Demo Switcher** buttons to instantly test any role with a single click.)*

---

## 📄 License & Compliance

Designed in accordance with modern healthcare information system standards and role-based access control (RBAC) privacy guidelines.
