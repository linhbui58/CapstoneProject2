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

Use the actual database script and configuration required by the repository. Do not commit database passwords or other secrets to GitHub.

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
