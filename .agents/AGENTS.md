# GNHS Guidance System — Workspace Rules & File Creation Guidelines

## 1. Directory Structure Standards
All project files MUST adhere to the following organized structure:
- **`index.html`**: Root landing page and login modal.
- **`assets/`**: Static frontend assets only.
  - `assets/css/style.css`: Stylesheets.
  - `assets/js/app.js`: Main JavaScript logic.
  - `assets/images/`: Images and logo assets (`gnhs.jpg`).
- **`includes/`**: Core PHP helper modules.
  - `includes/config.php`: Database connection, session management, and `getBaseUrl()` helper.
  - `includes/layout.php`: Shared HTML layout, navigation sidebar, and topbar modals.
- **`api/`**: All backend API endpoint handlers (e.g. `login.php`, `register.php`, `get_case.php`, `submit_case.php`, `update_case.php`, etc.).
- **`pages/`**: All dashboard page views for Admin, Student, and Teacher roles (e.g. `admin-dashboard.php`, `student-dashboard.php`, `teacher-dashboard.php`, etc.).
- **`database/`**: SQL migration scripts (`*.sql`) and database setup scripts (`setup.php`).

## 2. Rules for New File Creation
- **Backend API Endpoints**:
  - MUST be created inside `api/`.
  - MUST start with `require_once __DIR__ . '/../includes/config.php';`.
  - MUST use `jsonOut([...])` helper for responses.
- **Dashboard / View Pages**:
  - MUST be created inside `pages/`.
  - MUST start with:
    ```php
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/layout.php';
    $user = requireLogin('role_name');
    ```
- **Static Assets**:
  - MUST be placed in `assets/css/`, `assets/js/`, or `assets/images/`.
- **Prohibited Files**:
  - NEVER create temporary test files (like `ai-test.php`, `test_ai.php`, or `hash-tool.php`) in the root or production directories.

## 3. Multi-Device & Laptop Compatibility (Versioning & Path Resolution)
- **Dynamic Base URLs**:
  - Avoid hardcoding static root folder names (e.g. `/gnhs-guidance/`).
  - PHP scripts MUST use the `getBaseUrl()` helper function from `includes/config.php` to generate relative URLs (`getBaseUrl() . '/pages/admin-dashboard.php'`).
  - JavaScript MUST use `getApiUrl('/api/...')` or `APP_BASE` to remain compatible regardless of whether the project is hosted in `http://localhost/gnhs/`, `http://localhost/gnhs-guidance/`, or root `/`.
- **Version Compatibility**:
  - Requires PHP 8.0+ (PDO, Bcrypt password hashing).
  - MySQL 5.7+ / MariaDB 10.2+ (supports `IF NOT EXISTS` columns and InnoDB).
