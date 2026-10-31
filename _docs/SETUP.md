# AgriTrace+ - Setup and Access Guide

## Quick Start - Testing the Panels

### Option 1: Quick Test Access (RECOMMENDED FOR DEVELOPMENT)
1. Navigate to: `http://localhost/AG/test-login.php`
2. Click on any panel button:
   - **Farmer Panel** - Access farmer dashboard
   - **Admin Panel** - Access administrator dashboard
   - **Official Panel** - Access agriculture official dashboard
3. You will be instantly logged in and redirected to the selected panel

### Option 2: Using Production Login
1. Navigate to: `http://localhost/AG/`
2. Click "Login" button
3. Enter your credentials (must have account in database)
4. After login, you'll see the dashboard with quick access links

## Panel Access URLs

| Panel | Direct URL | Test URL |
|-------|-----------|----------|
| Farmer | `http://localhost/AG/farmer.php` | Create session at test-login.php |
| Admin | `http://localhost/AG/admin.php` | Create session at test-login.php |
| Agriculture Official | `http://localhost/AG/agriDA.php` | Create session at test-login.php |
| Dashboard | `http://localhost/AG/dashboard.php` | After login or test access |

## File Structure

```
/AG
├── pages/                       # User-facing application views & panels
│   ├── index.php                # Landing page
│   ├── login.php                # Production login
│   ├── dashboard.php            # User dashboard after login
│   ├── logout.php               # Logout handler
│   ├── farmer.php               # Farmer panel
│   ├── admin.php                # Admin panel
│   ├── agriDA.php               # Agriculture official panel
│   └── ...                      # Other views & forms
├── api/                         # Backend API endpoints
│   ├── db.php
│   ├── approve_farm.php
│   ├── clean_otps.php
│   └── ...
├── assets/                      # Stylesheets and frontend scripts
│   ├── js/                      # JavaScript assets (farm-map-enhanced.js)
│   └── ...
├── database/                    # SQL bootstrap schema
│   └── database.sql             # Clean database schema
├── maps/                        # Geomapping views
├── uploads/                     # User-uploaded documents and images
├── _docs/                       # Documentation and audit guides
├── index.php                    # Root entry router (loads pages/index.php)
├── init.php                     # Central initialization script
├── db_connect.php               # Database connection
└── .htaccess                    # URL rewriting rules for /pages/ and routing
```

## What's New

### 1. **test-login.php** - Development Fast Access
- Instantly create test sessions for any role
- Bypasses login for faster testing
- Perfect for development and testing
- Accessible from home page

### 2. **dashboard.php** - User Dashboard
- Centralized hub after login
- Role-based quick access links
- Shows user information
- Easy navigation to all panels

### 3. **.htaccess** - URL Configuration
- Enables proper URL routing
- Handles MIME types correctly
- Production-ready configuration

## How to Access Panels

### Method A: Quick Test (Recommended)
```
1. Go to: http://localhost/AG/test-login.php
2. Click any role button
3. Your panel opens instantly
```

### Method B: Regular Login
```
1. Go to: http://localhost/AG/
2. Click "Login"
3. Enter credentials
4. Land on dashboard
5. Click your panel shortcut
```

### Method C: Direct Access
```
- http://localhost/AG/farmer.php (requires active session)
- http://localhost/AG/admin.php (requires active session)
- http://localhost/AG/agriDA.php (requires active session)
```

## Session Info

After test login or regular login, your session contains:
- `user_id` - Your user ID
- `user_role` - Your role (Farmer, Admin, Agriculture Official)
- `firstName` - First name
- `lastName` - Last name
- `user_email` - Email address
- `logged_in` - Session validity flag

## Troubleshooting

### "Access Denied" Error
- You may have wrong credentials
- Use test-login.php for instant access
- Check if your database is running

### "Database Connection Failed"
- Verify XAMPP MySQL is running
- Check `db_connect.php` database credentials
- Default: myproject4 database

### Panel Not Loading
- Make sure you're logged in (session active)
- Check browser console for JavaScript errors
- Try clearing browser cache and cookies

### SESSION Not Starting
- XAMPP must be running
- Temporary files folder must be writable
- Check php.ini session.save_path

## Security Notes

⚠️ **Development Only:**
- `test-login.php` bypasses password verification (development only)
- Remove or disable this file before production
- Use proper authentication in production
- Never expose test credentials

## Environment Variables (Optional)

You can set these in `db_connect.php`:
```php
DB_HOST = 'localhost'      # Database host
DB_NAME = 'myproject4'     # Database name
DB_USER = 'root'           # Database user
DB_PASS = ''               # Database password
```

## API Endpoints

Administrative API available at `/api/`:
- `db.php` - Generic database operations
- `approve_farm.php` - Farm approval
- `reject_farm.php` - Farm rejection
- `generate_report.php` - Report generation
- `update_profile.php` - Profile management

## Support

For issues or questions, check:
1. Browser console (F12) for errors
2. XAMPP Apache/MySQL status
3. File permissions on `/api` and `/maps` directories
4. Database backup in `database/database.sql`

## Next Steps

1. ✅ Start with `test-login.php` for quick testing
2. ✅ Explore the farmer, admin, and official panels
3. ✅ Check API functionality
4. ✅ Customize as needed
5. ✅ Before deployment, disable `test-login.php`

---

**Happy Testing! 🚀**
