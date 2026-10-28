# VCF Donation & Fundraising Management System

## 1. Project Overview

The **VCF Donation & Fundraising Management System** is a web-based application developed to support donation record management and transaction verification for VinaCapital Foundation (VCF).

The system aims to improve the consistency, traceability, and management of donation and transaction records.

## 2. Project Objectives

* Manage donation information.
* Support donation transaction verification.
* Track verification statuses and verification history.
* Provide role-based access to system functions.
* Maintain accurate and consistent donation records.

## 3. Technologies

* **Frontend:** HTML, CSS, JavaScript
* **Backend:** PHP
* **Database:** MySQL
* **Local Development Environment:** XAMPP
* **Database Management:** phpMyAdmin
* **Version Control:** Git and GitHub
* **Hosting:** InfinityFree
* **Domain & Deployment:** https://vcf-transaction-system.wuaze.com/
* **CI/CD:** GitHub Actions

## 4. Project Structure

```text
CapstoneProject2/
├── assets/          # Stylesheets, scripts, and other assets
├── auth/            # Authentication-related files
├── campaign_staff/  # Campaign staff functions
├── config/          # Application configuration
├── database/        # Database scripts
├── donor/           # Donor functions
├── finance_staff/   # Transaction verification functions
├── includes/        # Shared application components
├── manager/         # Management functions
├── index.php        # Application entry point
└── README.md         # Project documentation
```

## 5. Prerequisites

Before running the application, install:

* XAMPP with Apache, PHP, and MySQL.
* A web browser.
* Git (if cloning the repository).
* Composer (to install the PHP mail dependency locally).

## 6. Installation and Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/linhbui58/CapstoneProject2.git
```

### Step 2: Move the Project

Move or clone the project into the XAMPP web directory:

```text
C:\xampp\htdocs\CapstoneProject2
```

### Step 3: Start XAMPP

1. Open the XAMPP Control Panel.
2. Start **Apache**.
3. Start **MySQL**.

### Step 4: Configure the Database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`.
2. Create the database using the database name and settings required by the project.
3. Import the SQL setup script provided in the `database/` directory, if available.
4. Configure the database connection in the appropriate file under `config/`.

Donation creation, status checks, and finance verification use the application's existing database connection from `config/db.php`. On InfinityFree, create `config/vietqr.php` on the server (next to `db.php`) with the following contents, replacing the example values with the fund account details. This file is excluded from Git and deployment uploads so it remains private and is not overwritten:

The application sets PHP and every database connection to Vietnam time (`Asia/Ho_Chi_Minh`, UTC+07:00), so database timestamps and displayed dates use Hanoi local time.

```php
<?php
return [
    'bank_id' => 'your_vietqr_bank_code_or_bin',
    'bank_name' => 'your_bank_name',
    'account_no' => 'your_fund_account_number',
    'account_name' => 'your_fund_account_name',
];
```

The same `VIETQR_BANK_ID`, `VIETQR_BANK_NAME`, `VIETQR_ACCOUNT_NO`, and `VIETQR_ACCOUNT_NAME` PHP environment variables or constants are also supported; environment variables take precedence. Do not commit database credentials or the fund account configuration to the repository.

Bank donations use SePay Payment Gateway checkout and SePay's Payment Gateway IPN. On the SePay merchant page, configure the IPN URL as `https://your-site/donor/sepay_pg_ipn.php`, select JSON, choose Secret Key authentication, and use the same IPN secret configured below. Set the successful, error, and cancel callbacks through the checkout form automatically. SePay calls the IPN after payment; the donor is notified that payment arrived and is awaiting finance verification. The IPN never marks donations as verified.

Create `config/sepay.php` on the server with the credentials for the selected Payment Gateway environment:

```php
<?php
return [
    'merchant_id' => 'SP-TEST-your_sandbox_merchant_id',
    'merchant_secret_key' => 'your_sandbox_merchant_secret',
    'mode' => 'sandbox',
    'ipn_secret_key' => 'the_secret_configured_for_payment_gateway_ipn',
];
```

SePay Test Mode requires the Sandbox merchant ID and secret, and the code submits checkout to `https://pay-sandbox.sepay.vn/v1/checkout/init`. A production merchant ID and secret only work with `mode => 'production'` and `https://pay.sepay.vn/v1/checkout/init`. Do not mix sandbox and production credentials. Rotate any merchant or IPN secret that has been exposed, and never commit credentials.

Deploy the application before configuring the IPN. The callback URL must be publicly reachable over HTTPS. The separate SePay bank-account Webhook endpoint at `donor/sepay_webhook.php` is retained for direct bank-account transaction notifications; it uses `api_key` in `config/sepay.php` and is not the Payment Gateway IPN. Configure that key only if using the separate bank-account webhook integration.

Credit card donations are processed by VNPay in VND. Create `config/vnpay.php` on the server with the merchant credentials provided by VNPay:

```php
<?php
return [
    'tmn_code' => 'YOUR_VNPAY_TMN_CODE',
    'hash_secret' => 'YOUR_VNPAY_HASH_SECRET',
    'mode' => 'sandbox',
];
```

Use the sandbox credentials and test gateway first. Before going live, replace them with the live merchant credentials and set `mode` to `live`. Register `https://vcf-transaction-system.wuaze.com/donor/vnpay_ipn.php` as the IPN URL in the VNPay merchant portal. The return URL is generated from the application's `SITE_URL`; use the site's public HTTPS address. The private config file is excluded from Git and deployment uploads. Card entry and processing take place on VNPay; this application never collects or stores card numbers. The card option requests VNPay's domestic bank-card method (`VNBANK`); payment methods depend on the merchant account. VNPay card donations must be at least 5,000 VND.

Finance verification sends a thank-you email to the donor after a donation is verified. Install PHP dependencies locally with `composer install`; the deployment workflow installs them before uploading the application. On the hosting server, create `config/mailer.php` (or `config.php` at the site root) with the SMTP credentials below. The root `config.php` location is supported for existing hosting setups; `config/mailer.php` is preferred.

```php
<?php
return [
    'SMTP_USER' => 'your-sender@gmail.com',
    'SMTP_PASS' => 'your-Google-app-password',
];
```

For Gmail, use a Google App Password with 2-Step Verification enabled, not the account password. Do not commit the SMTP config. If verification succeeds but mail delivery fails, the finance staff page shows a warning; check the server's PHP error log for the SMTP failure. Gmail SMTP uses STARTTLS on port 587.

Use the actual database script and configuration required by the repository. Do not commit database passwords or other secrets to GitHub.

For an existing installation, import `database/migrations/20261026_disbursement_requests.sql` and `database/migrations/20261027_campaign_updates.sql` into the application's selected database using phpMyAdmin. These add the disbursement request and campaign update tables and evidence columns for completed disbursements. Import `database/migrations/20261028_sepay_webhook.sql` only if you use the separate SePay bank-account Webhook endpoint. New installations get this schema from `database/full_setup.sql`.

### Step 5: Run the Application

Open the following URL in your browser:

```text
http://localhost/CapstoneProject2/
```

## 7. Main Modules

| Module         | Purpose                                  |
| -------------- | ---------------------------------------- |
| Authentication | User login and access control            |
| Donor          | Donation-related functions               |
| Campaign Staff | Campaign and programme-related functions |
| Finance Staff  | Donation transaction verification        |
| Manager        | Management functions                     |

The available functions depend on the current implementation.

Managers can export campaign performance, and finance staff can export transactions and verification history as UTF-8 CSV files that open in Excel. Finance exports retain the selected page filters.

## 8. Git Workflow

To keep the `main` branch stable, team members should work on separate feature branches.

Create a branch:

```bash
git switch -c feature/your-feature
```

Commit and push changes:

```bash
git add .
git commit -m "Describe your changes"
git push -u origin feature/your-feature
```

Then create a **Pull Request (PR)** on GitHub to merge the feature branch into `main`.

Before merging, review the changes and run the relevant tests.

## 9. Security Guidelines

* Do not commit passwords, API keys, or other sensitive information.
* Keep environment-specific configuration separate from application code.
* Validate user input on the server side.
* Restrict functions according to user roles.
* Test authentication and authorization before deployment.

## 10. Current Development Status

The project is under development. Features, testing evidence, and deployment instructions should be updated as implementation progresses.

## 11. Team Contributions

Record each member's responsibilities and contributions through Git commits, issues, task assignments, and Pull Requests.
