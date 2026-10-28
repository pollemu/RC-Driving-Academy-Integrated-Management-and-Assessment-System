# RC Driving Academy IMAS — Setup Guide

## 1. Install Required Software

### Git
- Download: https://git-scm.com/download/win
- Run installer, click Next through all options (defaults are fine)

### XAMPP
- Download: https://www.apachefriends.org/download.html
- Choose XAMPP for Windows (PHP 8.2+)
- Run installer, install to `C:\xampp`

### Add PHP to System PATH
- Press `Win + S`, search **Environment Variables**
- Click **Edit the system environment variables**
- Click **Environment Variables**
- Under **System variables**, find `Path`, click **Edit**
- Click **New**, add `C:\xampp\php`
- Click **OK** on all dialogs
- **Close and reopen PowerShell** for changes to take effect

### Verify PHP
```powershell
php -v
```
Should show PHP 8.x.x

---

## 2. Install Composer

- Download: https://getcomposer.org/download/
- Run **Composer-Setup.exe**
- Click Next through all options
- When asked for PHP path, use `C:\xampp\php\php.exe`
- Finish the installer
- **Close and reopen PowerShell**

### Verify Composer
```powershell
composer -V
```
Should show Composer version

---

## 3. Clone the Project

```powershell
cd C:\xampp\htdocs
git clone https://github.com/pollemu/RC-Driving-Academy-Integrated-Management-and-Assessment-System.git
cd "RC-Driving-Academy-Integrated-Management-and-Assessment-System"
```

---

## 4. Install Dependencies

```powershell
composer install
```
Wait for it to finish (may take 1-2 minutes)

---

## 5. Configure Environment

```powershell
copy .env.example .env
php artisan key:generate
```
You should see "Application key set successfully"

---

## 6. Create Database & Seed Data

```powershell
New-Item database/database.sqlite -ItemType File -Force
php artisan migrate --seed
```
Should say "Migration complete" and "Seeding database"

---

## 7. Start the Server

```powershell
php artisan serve
```

Then open your browser and go to: **http://127.0.0.1:8000**

---

## Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@rcdriving.ph | admin123 |
| Instructor | mario.santos@rcdriving.ph | instructor123 |
| Student | john.cruz@rcdriving.ph | student123 |

---

## Troubleshooting

**"composer is not recognized"**
Composer is not installed or not in PATH. Reinstall from https://getcomposer.org/download/

**"php is not recognized"**
PHP is not in PATH. Make sure `C:\xampp\php` is added to system environment variables.

**Database errors**
Make sure you ran `New-Item database/database.sqlite -ItemType File -Force` before `php artisan migrate --seed`.

**Port 8000 already in use**
```powershell
php artisan serve --port=8080
```
Then go to http://127.0.0.1:8080 instead.
