# LifeLink Web Project

LifeLink is a PHP + MySQL web application that helps blood requesters find compatible donors in the same city, while keeping donor contact details private until an offer is accepted.

## Authors

* **Muhammad Ali** - *Roll No: BCSF24A022*
* **Babur Ali** - *Roll No: BCSF24A006*

## Features

- Role-based flows for **donor**, **requester**, and **admin**
- Donor eligibility checks (18+ age, 90-day wait window, availability toggle)
- Blood-group compatibility matching
- City-based request matching
- Request lifecycle: open → accepted offers → donation confirmation → fulfilled/expired/cancelled
- Privacy-first contact sharing (phone revealed only after acceptance)
- Hospital verification workflow for admins
- CSRF protection, session handling, and output escaping helpers

## Tech Stack

- PHP 8+
- MySQL 8+
- Apache (configured to expose only `public/`)
- Vanilla JavaScript + CSS

## Project Structure

- `/public` — web root and all route entry points
- `/includes` — shared auth/session/business helpers
- `/config` — database connection and Apache vhost example
- `/database` — schema and seed SQL
- `/scripts` — CLI scripts for admin/demo data setup

## Quick Start

### 1) Clone and place project

Put the project where Apache can serve it (example in this repo uses `/var/www/lifelink`).

### 2) Create database schema and base seed

```bash
sudo mysql < database/schema.sql
sudo mysql < database/seed.sql
```

### 3) Configure database credentials

Edit:

- `/home/runner/work/lifelink-web-project/lifelink-web-project/config/database.php`

Set:

- `$dbHost`
- `$dbName`
- `$dbUser`
- `$dbPass`

Make sure `$dbPass` matches the user password created in `database/schema.sql`.

### 4) Configure Apache

Use:

- `/home/runner/work/lifelink-web-project/lifelink-web-project/config/lifelink.conf`

Enable the site, then reload Apache.

### 5) Create an admin account

```bash
php scripts/create_admin.php
```

### 6) (Optional) Seed demo users

```bash
php scripts/seed_demo.php
```

Demo script default password: `Password123!`

## Main User Flows

### Requester

1. Register as requester
2. Create blood request
3. Review donor offers
4. Accept/decline offers
5. Confirm donation to update fulfillment

### Donor

1. Register as donor (blood group, DOB, weight)
2. Set availability
3. View compatible open requests
4. Offer donation
5. Track offer status and donation history

### Admin

- Monitor high-level stats
- Verify/unverify hospitals
- Manage cities and user activation status

## Security Notes

- `public/` is intended to be the only web-accessible directory
- CSRF tokens are required on POST forms
- Passwords are hashed with PHP password hashing APIs
- Query inputs are parameterized (prepared statements)

## Useful Commands

```bash
# Create admin account
php scripts/create_admin.php

# Insert optional demo records
php scripts/seed_demo.php
```
