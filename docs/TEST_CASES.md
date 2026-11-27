# EduConnect — Test cases

Automated tests (24) run on **Admin → Tests** (`admin/tests.php`). The table below has the **manual test cases** for the defense: valid input, invalid input, missing data, boundary cases, business logic, database operations and permissions.

Before testing, import `database.sql` again.

| # | Type | Test case | Steps / input | Expected result | Status |
|---|---|---|---|---|---|
| TC-01 | Valid | Registration | Register: Alina / Testova / alina@mail.kz / +7 701 222 33 44 / alina2026 ×2 | Redirect to Login, green message "Account created", email already filled in | ✅ |
| TC-02 | Missing data | Empty registration form | Press Register without data | Message at the top + error under 5 required fields; the form is not sent | ✅ |
| TC-03 | Invalid | Wrong email / short password / different passwords | `student@`, `1234567`, `abcd1234` + `abcd1235` | "Please enter a valid email", "at least 8 characters", "Passwords do not match" | ✅ |
| TC-04 | Boundary | Password length | 7 characters `abc1234` → 8 characters `abcd1234` | 7: error; 8: accepted | ✅ |
| TC-05 | Invalid (DB) | Email that already exists | `student@educonnect.kz` | AJAX hint "already registered" while typing; PHP error after Register | ✅ |
| TC-06 | Invalid | Login: wrong password, unknown email, 5 wrong attempts | wrong password; nobody@mail.kz; 5 wrong passwords in a row | "Incorrect password"; "No account found"; after the 5th attempt the form is locked for 60 s | ✅ |
| TC-07 | Business logic | Schedule conflict | student@educonnect.kz → Web Design → Enroll → Group A (Mon/Wed 14:00) | Red message: conflict with "Introduction to Python" Mon/Wed 14:00–15:30, the Confirm button is disabled | ✅ |
| TC-08 | Business logic | Partial overlap | same student → UI/UX → Group B (Mon/Wed 14:30–16:00) | Conflict: 14:30 is inside 14:00–15:30 | ✅ |
| TC-09 | Valid + DB | Enrollment in a free group | Web Design → Group B → phone → terms → Confirm | Confirmation page with ID, group, schedule, price; enrollment is Pending in the profile and in the admin panel | ✅ |
| TC-10 | Boundary | Full group | any student → UI/UX Group A (3 of 3) | The group is disabled ("Group is full"); a direct POST is rejected by PHP: "this group is full" | ✅ |
| TC-11 | Invalid | Enrollment form errors | no group / phone `123` / comment 301 characters / terms not checked | 4 different error messages; comment 300 characters is accepted | ✅ |
| TC-12 | Business logic | The same course twice | open Enroll of a course where you are already enrolled | Redirect to the course: "You are already enrolled (#id)" | ✅ |
| TC-13 | Permissions | Lessons before confirmation | student with a Pending enrollment (SQL course) → lesson 2 | 🔒 "waiting for confirmation"; the free preview lesson works | ✅ |
| TC-14 | AJAX + DB | Lesson progress | confirmed course → lesson → "Mark as completed" | Button turns green, ✓ in the list, progress bar 2/3 → 3/3 without reload | ✅ |
| TC-15 | AJAX + validation | Review | student with a confirmed course → stars + comment → Send; then the same request again | Review appears at the top, the average and the count change; second time: 409 "already reviewed" | ✅ |
| TC-16 | Unauthorized | Student opens the admin panel | student → /admin/index.php | 403 "Access denied" | ✅ |
| TC-17 | Unauthorized | Another user's data | student → `pages/confirmation.php?id=<not his>`; cancel with another enrollment_id | 403 "You can see only your own enrollments"; "This enrollment cannot be cancelled" | ✅ |
| TC-18 | Unauthorized API | API without login / not admin | guest: POST api/progress.php → 401; student: POST api/admin_enrollment.php → 403 | JSON error with the right HTTP code | ✅ |
| TC-19 | Security | CSRF | send a form without `csrf_token` (e.g. with Postman) | 403 "Request blocked" | ✅ |
| TC-20 | Security | SQL injection / XSS | email `' OR '1'='1` in Login; name `<script>alert(1)</script>` in the contact form | Login: "No account found"; the script is shown as text in Admin → Messages | ✅ |
| TC-21 | Security | Upload a fake image | profile photo = a PHP file renamed to `.png` | "Please choose an image file": the real MIME type is checked | ✅ |
| TC-22 | DB error handling | Delete a course with enrollments | Admin → Courses → Delete "Introduction to Python" | "Cannot be deleted: students are enrolled… Unpublish instead"; nothing is deleted (FOREIGN KEY RESTRICT) | ✅ |
| TC-23 | CRUD | Course → group → lesson → material | Admin: add course → add group (end before start = error) → add lesson → add material → delete course | All steps work; the new course without enrollments is deleted with its groups and lessons (CASCADE) | ✅ |
| TC-24 | AJAX | Admin changes status | Enrollments → Pending → Confirmed | Row flashes green, counters at the top change, no reload; Cancelled → Pending checks seats and conflicts again | ✅ |
| TC-25 | Errors / empty result | Catalog | search `zzzz`; stop Apache and change a filter | "No courses found"; "Could not load courses" + Try again button | ✅ |
| TC-26 | Errors | Database is down | stop MySQL in XAMPP, open any page | Friendly page "Database error: check that MySQL is running…" (no PHP error text) | ✅ |
| TC-27 | Responsive | Phone 390 px | DevTools → iPhone: home, catalog, details, lessons, profile | No horizontal scrolling; burger menu; wide tables scroll inside their block | ✅ |

## 3 errors / edge cases to show at the defense (and how they are handled)

1. **Schedule conflict (TC-07).**
   - JavaScript asks `api/check_group.php` and shows the conflict before sending.
   - PHP checks again in `enroll_user()`: the transaction is rolled back and the student sees a clear message.
2. **Deleting a course with enrollments (TC-22).**
   - MySQL refuses because of the FOREIGN KEY (SQLSTATE 23000).
   - PHP catches the `PDOException` and shows advice instead of an error.
3. **Unauthorized access (TC-16, TC-17, TC-18).**
   - Pages answer 403 and the API answers 401/403 JSON.
   - The checks are on the server, so hiding a button is not the only protection.
