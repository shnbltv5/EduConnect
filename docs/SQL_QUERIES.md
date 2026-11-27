# EduConnect — SQL: tables and non-trivial queries

## Data model (13 tables)

```
categories 1──* courses *──1 teachers
courses 1──* course_features
courses 1──* course_groups 1──* group_sessions      (weekday, start_time, end_time)
courses 1──* lessons 1──* materials
users 1──* enrollments *──1 course_groups            (status, price_paid)
users 1──* lesson_progress *──1 lessons              (PRIMARY KEY (user_id, lesson_id))
users 1──* reviews *──1 courses                      (UNIQUE (user_id, course_id), CHECK rating 1..5)
users 1──* contact_messages                          (ON DELETE SET NULL)
```

Integrity rules:

| Foreign key | Rule | Why |
|---|---|---|
| `courses → categories`, `courses → teachers` | **RESTRICT** | a used category or teacher cannot be deleted |
| `course_groups`, `lessons`, `materials`, `features`, `sessions` | **CASCADE** | they are deleted together with their parent |
| `enrollments → course_groups` | **RESTRICT** | a group or course with students cannot be deleted |
| `enrollments`, `progress`, `reviews → users` | **CASCADE** | deleting a user removes all his data |

## Where the queries are used

| # | Query (technique) | File / function | Page |
|---|---|---|---|
| 1 | **Catalog search**: 4 tables + 2 derived subqueries (rating, students), several WHERE conditions (LIKE, category, level, price range, rating), ORDER BY from a whitelist, LIMIT/OFFSET + COUNT for pagination | `includes/courses.php` → `search_courses()` | Catalog, `api/courses.php` |
| 2 | **Schedule conflict**: JOIN of 6 tables (group → sessions → enrollments → other group → its sessions → course), overlap of weekday, time and dates | `includes/enrollment.php` → `find_schedule_conflicts()` | Enroll, `api/check_group.php`, admin status change |
| 3 | **Groups with taken seats**: LEFT JOIN + COUNT + GROUP BY | `courses.php` → `course_groups()` | Details, Enroll |
| 4 | **Rating distribution**: GROUP BY rating | `rating_distribution()` | Details |
| 5 | **Enrollments of a student**: JOIN 3 tables + 2 correlated subqueries (lessons total / completed) | `enrollment.php` → `user_enrollments()` | Profile |
| 6 | **Popular courses**: JOIN 5 tables, LEFT JOIN, COUNT, SUM, subquery AVG | `admin/reports.php` #1 | Reports |
| 7 | **Revenue by category**: SUM, AVG, MAX, GROUP BY, **HAVING** | reports #2 | Reports |
| 8 | **Enrollments by month**: DATE_FORMAT, CASE inside SUM, date filter (last 6 months) | reports #3 | Reports |
| 9 | **Teachers**: LEFT JOIN 4 tables, COUNT(DISTINCT), correlated subquery | reports #4 | Reports |
| 10 | **Fill rate of groups**: LEFT JOIN, COUNT, percent, date filter | reports #5 | Reports |
| 11 | **Students without enrollments**: LEFT JOIN … IS NULL | reports #6 | Reports |
| 12 | **Courses above the average**: subquery inside HAVING | reports #7 | Reports |
| 13 | **Learning progress**: JOIN 4 + LEFT JOIN 2, COUNT(DISTINCT), NULLIF | reports #8 | Reports |
| 14 | **Busiest time slots**: JOIN 3, GROUP BY weekday + time, HAVING | reports #9 | Reports |
| 15 | **Dashboard**: several subqueries in one SELECT, revenue of the current month (date) | `admin/index.php` | Dashboard |
| 16 | **Groups starting soon**: BETWEEN today AND +30 days, subquery for free seats | `index.php` | Home |
| 17 | **Users with enrollments**: LEFT JOIN + COUNT + SUM(CASE) + search + pagination | `admin/users.php` | Admin users |

At least 3 queries combine several tables with aggregate functions: #1, #6, #7, #9, #13, #14, #17.

## Transactions

| Where | What happens |
|---|---|
| `enroll_user()` | `BEGIN` → `SELECT … FOR UPDATE` (locks the group row) → checks (exists, not finished, not enrolled, no conflict, free seat) → `INSERT` → `COMMIT`; any problem → `ROLLBACK` |
| `admin/course_form.php` | `UPDATE courses` + `DELETE` old features + `INSERT` new features in one transaction |
| `admin/groups.php` | the group and its lesson times are saved together |
| `api/admin_enrollment.php` | reactivation checks seats and conflicts inside a transaction |

## INSERT / SELECT / UPDATE / DELETE in the application

| Operation | Examples |
|---|---|
| INSERT | users (register), enrollments, reviews, lesson_progress, contact_messages, courses, groups, lessons, materials, categories, teachers |
| UPDATE | profile, password, photo, enrollment status, cancel, course, group, lesson, publish / hide, role, message read |
| DELETE | users, courses, groups, lessons, materials, reviews, messages, categories, teachers, lesson progress |
