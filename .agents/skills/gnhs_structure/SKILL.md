---
name: gnhs_structure
description: Standards and guidelines for GNHS Guidance System folder structure, file creation, multi-device compatibility, and versioning.
---

# GNHS Structure & Multi-Device Compatibility Skill

This skill provides guidelines for adding new files, maintaining the project structure, and ensuring 100% cross-device compatibility across multiple laptops/environments.

## Directory Mapping
- Root: `index.html` only.
- `assets/css/`: Web stylesheets (`style.css`).
- `assets/js/`: Client JavaScript (`app.js`).
- `assets/images/`: Images and icons (`gnhs.jpg`).
- `includes/`: PHP core (`config.php`, `layout.php`).
- `api/`: PHP backend endpoints (`login.php`, `register.php`, `get_case.php`, `submit_case.php`, `update_case.php`, etc.).
- `pages/`: PHP role views (`admin-*.php`, `student-*.php`, `teacher-*.php`).
- `database/`: Database schema and seed scripts (`*.sql`, `setup.php`).

## Multi-Laptop & Environment Compatibility
1. **Dynamic Path Resolution**:
   - Always use `getBaseUrl()` in PHP for headers and links.
   - Always use `getApiUrl('/api/...')` or `APP_BASE` in JS for API fetches.
2. **Database & PHP Version Compatibility**:
   - PHP Version: 8.0 or higher.
   - Database: MySQL 5.7+ / MariaDB 10.2+.
