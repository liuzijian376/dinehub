# DineHub: Taylor's University Restaurant Events Hub

PHP 8.1+ and SQLite (PDO). No database server needed; `data/dinehub.sqlite` is created and seeded on first request.

## Run
```
cd dinehub
php -S localhost:8000
```
Open http://localhost:8000. With XAMPP, copy the folder into `htdocs` and make sure `pdo_sqlite` is enabled in php.ini.

## Demo accounts
| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@taylors.edu.my | Admin123 |
| Restaurant manager (Lakeside Cafe) | aisha@sd.taylors.edu.my | Chef1234 |
| Restaurant manager (Garden Bistro) | daniel@sd.taylors.edu.my | Chef1234 |
| Student | meiling@sd.taylors.edu.my | Student123 |

Delete `data/dinehub.sqlite` to reset the demo data.

## Features
- Register, log in/out, edit profile, change password
- Browse and search events, join or cancel with live seat counts (no overbooking)
- Follow restaurants; restaurant pages list upcoming events
- Book 1 to 4 seats per event with a dietary preference; managers see a kitchen summary
- Restaurant managers create and edit events for their restaurant, view registered students; events wait for admin approval
- Admin dashboard: approve or reject events, assign restaurant managers, change user roles

## Security measures
password_hash/password_verify, PDO prepared statements, CSRF tokens on every POST form, output escaping with htmlspecialchars, session ID regeneration on login, role checks on each protected page, server-side validation.

## Files
`lib.php` helpers, DB and seed | `schema.sql` tables | `index.php` events | `event.php` details, join, leave, delete | `restaurants.php` | `event_form.php` | `profile.php` | `admin.php` | `register.php`, `login.php`, `logout.php` | `assets/`
