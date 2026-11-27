# EduConnect — Online Course Platform (End-term project)

Web Programming project, topic **#3 Online Course Platform**.
A student browses the catalog, chooses a **group with a convenient schedule**, enrolls, watches **video lessons**, downloads **materials** and tracks his progress. The administrator manages courses, groups, lessons, users and enrollments and sees statistical reports.

**Technologies:** HTML5, CSS3, JavaScript (DOM, events, Fetch API / AJAX, JSON), PHP 8 (sessions, PDO), MySQL / MariaDB.
No frameworks: everything is written by hand.

## What changed after the midterm (teacher's remarks)

| Remark | What we did |
|---|---|
| Add a database | The data moved from `localStorage` to **MySQL (13 related tables)**. PHP reads and writes the data through PDO prepared statements. |
| A student must not have two enrollments at the same time (e.g. 14:00 and 14:00) | Courses now have **groups** with lesson times (`course_groups` + `group_sessions`). Before saving an enrollment, `find_schedule_conflicts()` (SQL with 5 JOINs) checks the weekday, the time overlap and the dates. It runs in AJAX when a group is chosen and again on the server inside a **transaction**. |
| Add video | Every course has an **intro video** (local MP4, works offline). Lessons have videos: local MP4 or **YouTube** (freeCodeCamp courses). |
| Add materials for the courses | Every lesson has **materials**: PDF syllabus and practice task, links to the official documentation. Lessons open after the enrollment is confirmed; the first lesson is a free preview. Students mark lessons as completed, and a progress bar shows how far they are. |

## How to run (XAMPP)

1. Copy the `EduConnect` folder into `htdocs`.
   Windows: `C:\xampp\htdocs\`. macOS: `/Applications/XAMPP/xamppfiles/htdocs/`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open http://localhost/phpmyadmin → **Import** → choose `database.sql` → **Go**.
   The file creates the `educonnect` database with 13 tables and test data. Dates are counted from the import day, so groups are always running or starting soon.
4. Open http://localhost/EduConnect/
5. If your MySQL user or password is not `root` with an empty password, change `includes/config.php`.

Before the defense, import `database.sql` again to get fresh demo data.

### Test accounts

| Role | Email | Password |
|---|---|---|
| Administrator | admin@educonnect.kz | admin123 |
| Student (demo) | student@educonnect.kz | student123 |
| Other students | e.g. aigerim.kairatova@mail.kz | password123 |
| Student without enrollments | saltanat.nurpeisova@mail.kz | password123 |

**Demo scenarios:**
- Log in as `student@educonnect.kz`. Courses → Web Design Fundamentals → Enroll → choose **Group A** (Mon/Wed 14:00): you get a schedule conflict with Python. Choose **Group B**: the time is free.
- Courses → UI/UX → Enroll: **Group A** is full (3 of 3).
- Admin → Tests: 24 automated tests run.

## Deployment (free hosting, e.g. InfinityFree)

1. Create an account and a website; in the control panel create a **MySQL database**. Write down the host, the database name, the user and the password.
2. Open the database in phpMyAdmin of the hosting. Remove the first lines of `database.sql` (`DROP DATABASE`, `CREATE DATABASE`, `USE educonnect`), because shared hosting gives you an existing database. Then import the file.
3. Upload all project files to `htdocs` of the hosting (File Manager or FTP, e.g. FileZilla).
4. Put the hosting values into `includes/config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
5. Open the website address and log in with the test accounts.

## Pages (10 user pages + admin panel with 12 pages)

| Page | File | Access |
|---|---|---|
| Home (hero, user info, statistics, popular courses, groups starting soon) | `index.php` | everyone |
| About, team, FAQ, contact form (saved to DB), privacy, terms | `pages/about.php` | everyone |
| Catalog: search, 5 filters, 6 sort options, pagination (AJAX) | `pages/courses.php` | everyone |
| Course details: intro video, curriculum, groups, reviews | `pages/details.php?id=1` | everyone |
| Lessons: video, materials, progress | `pages/learn.php?course=1` | preview: everyone; other lessons: confirmed students |
| Enrollment form (group, phone, comment, terms) | `pages/enroll.php?id=1` | students |
| Confirmation | `pages/confirmation.php?id=…` | owner / admin |
| Login · Register · Logout | `login.php`, `register.php`, `logout.php` | — |
| Profile: edit, photo, password, my courses, weekly schedule | `profile.php` | logged in |
| Admin: dashboard, users, courses (CRUD), lessons & materials, groups & schedule, enrollments, reviews, messages, categories & teachers, SQL reports, tests | `admin/*.php` | admin only |

## Project structure

```
EduConnect/
├── index.php  login.php  register.php  logout.php  profile.php
├── pages/        about, courses, details, enroll, confirmation, learn
├── admin/        index (dashboard), users, courses, course_form, lessons, groups,
│                 enrollments, reviews, messages, catalog, reports, tests
├── api/          JSON web services (AJAX):
│   ├── courses.php           GET  catalog: search, filters, sort, pagination
│   ├── check_group.php       GET  free seats + schedule conflicts of a group
│   ├── check_email.php       GET  is the email free (registration)
│   ├── reviews.php           POST add a review
│   ├── progress.php          POST mark a lesson as completed
│   └── admin_enrollment.php  POST change enrollment status (admin)
├── includes/
│   ├── config.php  db.php  bootstrap.php  functions.php  validation.php  auth.php
│   ├── courses.php       catalog queries, course page queries
│   ├── enrollment.php    business logic: conflicts, seats, transaction, access, progress
│   ├── header.php  footer.php  admin_layout.php
├── js/           main.js (apiRequest, cards, toast), validation.js, auth.js, courses.js,
│                 details.js, enroll.js, learn.js, profile.js, contact.js, admin.js
├── css/style.css one stylesheet (variables, flexbox, grid, media queries)
├── images/       logo, hero, course pictures
├── videos/       intro and lesson videos (MP4)
├── materials/    PDF syllabi and practice tasks
├── uploads/avatars/  profile photos (.htaccess forbids running PHP there)
├── database.sql  schema + test data
└── docs/         TEST_CASES.md, SQL_QUERIES.md, defense preparation
```

## Database (13 tables)

`users`, `categories`, `teachers`, `courses`, `course_features`, `course_groups`, `group_sessions`, `enrollments`, `lessons`, `materials`, `lesson_progress`, `reviews`, `contact_messages`.

- Primary keys, foreign keys with `CASCADE` / `RESTRICT` / `SET NULL`, `UNIQUE` (email, course title, one review per user and course) and `CHECK` (rating 1–5, weekday 1–7, end time after start, capacity).
- **Transactions:**
  - enrollment (`enroll_user`: `SELECT … FOR UPDATE`, checks, `INSERT`, commit or rollBack);
  - saving a course with its features;
  - saving a group with its lesson times;
  - changing an enrollment status.
- More than 8 non-trivial queries, used on real pages. The list is in `docs/SQL_QUERIES.md`; the reports page shows 9 of them with their SQL.

## Security

- PDO prepared statements everywhere. The sort order is chosen from a whitelist.
- `password_hash` / `password_verify` (bcrypt).
- XSS: every value is printed through `h()` (`htmlspecialchars`); JavaScript uses `escapeHtml` / `textContent`.
- CSRF token in every form and in the `X-CSRF-Token` header for AJAX.
- Sessions:
  - `session_regenerate_id` on login;
  - cookie with `httponly` and `SameSite=Lax`;
  - logout only with POST.
- Roles: `require_login()`, `require_admin()`, and API answers 401 / 403. A student sees and cancels only his own enrollments (`WHERE … AND user_id = ?`).
- Login lock after 5 wrong passwords (60 s). Redirect after login only to local pages.
- Photo upload: size limit, real MIME type (`finfo`), random file name, no PHP execution in `uploads/`.

## Team and contributions

| Member | Role | Main files |
|---|---|---|
| **Aidana Shynbulatova** (team lead) | JavaScript, AJAX, JSON API | `js/main.js`, `js/courses.js`, `js/enroll.js`, `js/details.js`, `js/learn.js`, `api/courses.php`, `api/check_group.php`, `api/reviews.php`, `api/progress.php`, `includes/courses.php` |
| **Nazerke Kenzhieva** | Forms, validation, authentication, security | `register.php`, `login.php`, `logout.php`, `profile.php`, `includes/validation.php`, `includes/auth.php`, `includes/functions.php` (CSRF, h()), `js/validation.js`, `js/auth.js`, `js/profile.js`, `api/check_email.php` |
| **Aruzhan Sakenova** | CSS, responsive design, video lessons and materials | `css/style.css`, `includes/header.php`, `includes/footer.php`, `index.php`, `pages/about.php`, `pages/learn.php`, `pages/details.php`, `videos/`, `materials/` |
| **Kirill Link** | Database, admin panel, CRUD, SQL reports | `database.sql`, `includes/db.php`, `includes/enrollment.php`, `admin/*`, `api/admin_enrollment.php`, `js/admin.js`, `docs/SQL_QUERIES.md` |

Development history: create a GitHub repository, and every member commits his or her own files from the table above (`git add <files>`, `git commit -m "..."`, `git push`). This way the commit history shows the individual contributions.
