-- =====================================================================
-- EduConnect — Online Course Platform (End-term project)
-- Database: MySQL 8 / MariaDB 10.4+ (XAMPP)
-- 13 tables, primary/foreign keys, UNIQUE and CHECK constraints, indexes.
-- Test data uses dates relative to the import day (CURDATE()), so groups
-- are always "running" or "starting soon" when you import the file.
--
-- Import: phpMyAdmin -> Import -> database.sql -> Go
-- Accounts: admin@educonnect.kz / admin123, student@educonnect.kz / student123
--           all other students: password123
-- =====================================================================

DROP DATABASE IF EXISTS educonnect;
CREATE DATABASE educonnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE educonnect;


CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  phone VARCHAR(20) NULL,
  birth_date DATE NULL,
  photo VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE teachers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NULL UNIQUE,
  bio TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL UNIQUE,
  level ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
  price INT UNSIGNED NOT NULL DEFAULT 0,
  duration_weeks TINYINT UNSIGNED NOT NULL,
  image VARCHAR(100) NOT NULL DEFAULT 'default.svg',
  intro_video VARCHAR(255) NULL,
  short_description VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_course_weeks CHECK (duration_weeks BETWEEN 1 AND 52),
  CONSTRAINT fk_course_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_course_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_course_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_features (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  feature VARCHAR(120) NOT NULL,
  position TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_feature_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  name VARCHAR(40) NOT NULL,
  format ENUM('Online','Offline','Hybrid') NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  location VARCHAR(100) NULL,
  CONSTRAINT chk_group_dates CHECK (end_date >= start_date),
  CONSTRAINT chk_group_capacity CHECK (capacity BETWEEN 1 AND 200),
  CONSTRAINT fk_group_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  INDEX idx_group_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE group_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  CONSTRAINT chk_session_day CHECK (weekday BETWEEN 1 AND 7),
  CONSTRAINT chk_session_time CHECK (end_time > start_time),
  CONSTRAINT fk_session_group FOREIGN KEY (group_id) REFERENCES course_groups(id) ON DELETE CASCADE,
  INDEX idx_session_day (weekday, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  status ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  price_paid INT UNSIGNED NOT NULL DEFAULT 0,
  phone VARCHAR(20) NOT NULL,
  comment VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_enrollment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollment_group FOREIGN KEY (group_id) REFERENCES course_groups(id) ON DELETE RESTRICT,
  INDEX idx_enrollment_status (status),
  INDEX idx_enrollment_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10451;

CREATE TABLE lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  position TINYINT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(120) NOT NULL,
  summary TEXT NULL,
  video_url VARCHAR(255) NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_lesson_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  type ENUM('pdf','link') NOT NULL DEFAULT 'link',
  url VARCHAR(255) NOT NULL,
  CONSTRAINT fk_material_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lesson_progress (
  user_id INT UNSIGNED NOT NULL,
  lesson_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_review_user_course UNIQUE (user_id, course_id),
  CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===================== Test data =====================

INSERT INTO users (id, first_name, last_name, email, password_hash, role, phone, birth_date, created_at) VALUES
  (1, 'Admin', 'EduConnect', 'admin@educonnect.kz', '$2y$10$14SPt6bB7wuq8zBzVxCfneQuUTKZdX9dA2M1zohfQyTEdYhCEj5lS', 'admin', '+77001112233', NULL, TIMESTAMP(CURDATE() - INTERVAL 240 DAY, '10:00:00')),
  (2, 'Demo', 'Student', 'student@educonnect.kz', '$2y$10$QFj.sLDiv7ixkvtpGGFBmui3T3EpdVjNB3Smy73RzlGhvnjCCJlQC', 'student', '+77011234567', '2005-04-12', TIMESTAMP(CURDATE() - INTERVAL 200 DAY, '10:00:00')),
  (3, 'Asel', 'Bekova', 'asel.bekova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77016433012', '1997-07-21', TIMESTAMP(CURDATE() - INTERVAL 32 DAY, '10:00:00')),
  (4, 'Dias', 'Nurlanov', 'dias.nurlanov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77012215279', '2003-02-12', TIMESTAMP(CURDATE() - INTERVAL 169 DAY, '10:00:00')),
  (5, 'Aigerim', 'Kairatova', 'aigerim.kairatova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77011973060', '2003-04-02', TIMESTAMP(CURDATE() - INTERVAL 42 DAY, '10:00:00')),
  (6, 'Daniyar', 'Akhmetov', 'daniyar.akhmetov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77018275367', '2001-02-08', TIMESTAMP(CURDATE() - INTERVAL 43 DAY, '10:00:00')),
  (7, 'Madina', 'Serikova', 'madina.serikova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77018122250', '1995-10-04', TIMESTAMP(CURDATE() - INTERVAL 77 DAY, '10:00:00')),
  (8, 'Yerassyl', 'Tokhtarov', 'yerassyl.tokhtarov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77012037872', '2004-10-13', TIMESTAMP(CURDATE() - INTERVAL 32 DAY, '10:00:00')),
  (9, 'Kamila', 'Ismagulova', 'kamila.ismagulova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77014709137', '1995-09-28', TIMESTAMP(CURDATE() - INTERVAL 54 DAY, '10:00:00')),
  (10, 'Alikhan', 'Zhunusov', 'alikhan.zhunusov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77015858837', '2001-03-18', TIMESTAMP(CURDATE() - INTERVAL 50 DAY, '10:00:00')),
  (11, 'Dana', 'Mukhanova', 'dana.mukhanova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77016175466', '2003-11-06', TIMESTAMP(CURDATE() - INTERVAL 46 DAY, '10:00:00')),
  (12, 'Nursultan', 'Abenov', 'nursultan.abenov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77014151952', '2000-02-18', TIMESTAMP(CURDATE() - INTERVAL 202 DAY, '10:00:00')),
  (13, 'Tomiris', 'Kassenova', 'tomiris.kassenova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77012053424', '2004-01-20', TIMESTAMP(CURDATE() - INTERVAL 72 DAY, '10:00:00')),
  (14, 'Ruslan', 'Ibraimov', 'ruslan.ibraimov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77019328453', '2005-09-14', TIMESTAMP(CURDATE() - INTERVAL 218 DAY, '10:00:00')),
  (15, 'Zhanna', 'Omarova', 'zhanna.omarova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77016270514', '2002-10-15', TIMESTAMP(CURDATE() - INTERVAL 112 DAY, '10:00:00')),
  (16, 'Bekzat', 'Sultanov', 'bekzat.sultanov@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77016029255', '1998-03-23', TIMESTAMP(CURDATE() - INTERVAL 219 DAY, '10:00:00')),
  (17, 'Saltanat', 'Nurpeisova', 'saltanat.nurpeisova@mail.kz', '$2y$10$qgERO7j8cw/./n/sRzHx7.QFmBpHHWWctLQTcOVU01sO2AsvSnUF.', 'student', '+77015550101', '2004-09-03', TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '10:00:00'));

INSERT INTO categories (id, name, description) VALUES
  (1, 'Programming', 'Python, JavaScript, PHP and web development'),
  (2, 'Design', 'Web design, UI/UX and prototyping'),
  (3, 'Marketing', 'Social media and digital marketing'),
  (4, 'Data', 'Spreadsheets, SQL and data analysis'),
  (5, 'Languages', 'English for professional communication'),
  (6, 'Business', 'Communication and project management');

INSERT INTO teachers (id, full_name, email, bio) VALUES
  (1, 'Arman Seitkali', 'arman.seitkali@educonnect.kz', 'Python developer with 8 years of experience in data processing and automation.'),
  (2, 'Dana Abenova', 'dana.abenova@educonnect.kz', 'Front-end developer and designer, teaches responsive layout since 2018.'),
  (3, 'Timur Bekov', 'timur.bekov@educonnect.kz', 'Full-stack JavaScript engineer, loves explaining the DOM and asynchronous code.'),
  (4, 'Aliya Nurlanova', 'aliya.nurlanova@educonnect.kz', 'Back-end developer (PHP, MySQL), university lecturer in web programming.'),
  (5, 'Madina Kassymova', 'madina.kassymova@educonnect.kz', 'Product designer working with mobile banking applications.'),
  (6, 'Aigerim Tulegenova', 'aigerim.tulegenova@educonnect.kz', 'SMM manager for local brands with 120k+ followers in total.'),
  (7, 'Nurlan Zhaksybekov', 'nurlan.zhaksybekov@educonnect.kz', 'Financial analyst, Excel and Power BI trainer.'),
  (8, 'Sarah Johnson', 'sarah.johnson@educonnect.kz', 'Native English speaker, IELTS examiner and business English coach.'),
  (9, 'Yerlan Omarov', 'yerlan.omarov@educonnect.kz', 'Database administrator, designs MySQL and PostgreSQL systems.'),
  (10, 'Saule Ismailova', 'saule.ismailova@educonnect.kz', 'Certified project manager (PMP), led IT projects in fintech.');

INSERT INTO courses (id, category_id, teacher_id, title, level, price, duration_weeks, image, intro_video, short_description, description, is_published, created_at) VALUES
  (1, 1, 1, 'Introduction to Python', 'Beginner', 30000, 8, 'python.svg', 'videos/python-intro.mp4', 'Learn Python syntax, data types, loops and functions from zero.', 'This course is a friendly start into programming. You will install Python, write your first programs and understand variables, conditions, loops, lists, dictionaries and functions. Every week ends with a small practical task.', 1, TIMESTAMP(CURDATE() - INTERVAL 221 DAY, '09:00:00')),
  (2, 2, 2, 'Web Design Fundamentals', 'Beginner', 25000, 6, 'webdesign.svg', 'videos/webdesign-intro.mp4', 'Build beautiful pages with HTML5 and CSS3: Flexbox, Grid, responsive layout.', 'Understand how websites are built. You will learn semantic HTML, the CSS box model, Flexbox, CSS Grid, media queries and transitions, and create a responsive landing page by the end of the course.', 1, TIMESTAMP(CURDATE() - INTERVAL 212 DAY, '09:00:00')),
  (3, 1, 3, 'JavaScript for Beginners', 'Beginner', 32000, 8, 'javascript.svg', 'videos/javascript-intro.mp4', 'Make websites interactive: variables, functions, events and the DOM.', 'JavaScript brings web pages to life. In this course you will learn variables, data types, functions, conditions, arrays and objects, then work with the DOM, events and form validation to build interactive pages.', 1, TIMESTAMP(CURDATE() - INTERVAL 203 DAY, '09:00:00')),
  (4, 1, 4, 'PHP & MySQL Backend', 'Intermediate', 40000, 10, 'php.svg', 'videos/php-intro.mp4', 'Server-side development: forms, sessions, databases and CRUD.', 'Learn how the server side of a website works. You will process HTML forms with PHP, work with sessions and cookies, connect to a MySQL database with PDO and build a full CRUD application with login and registration.', 1, TIMESTAMP(CURDATE() - INTERVAL 194 DAY, '09:00:00')),
  (5, 2, 5, 'UI/UX Design Principles', 'Intermediate', 35000, 6, 'uiux.svg', 'videos/uiux-intro.mp4', 'Design user-friendly interfaces: research, wireframes and prototypes in Figma.', 'Master the fundamentals of user interface and user experience design. You will study users, create wireframes, choose colors and fonts, and build a clickable prototype of a mobile application in Figma.', 1, TIMESTAMP(CURDATE() - INTERVAL 185 DAY, '09:00:00')),
  (6, 3, 6, 'Social Media Marketing 101', 'Beginner', 20000, 4, 'smm.svg', 'videos/smm-intro.mp4', 'Create effective campaigns on Instagram, TikTok and Telegram.', 'Learn how brands grow on social media. You will plan content, write engaging posts, understand analytics and launch your first advertising campaign with a small budget.', 1, TIMESTAMP(CURDATE() - INTERVAL 176 DAY, '09:00:00')),
  (7, 4, 7, 'Data Analysis with Excel', 'Beginner', 0, 5, 'excel.svg', 'videos/excel-intro.mp4', 'Free course: formulas, pivot tables and charts for everyday analytics.', 'A free introductory course for everyone who works with numbers. You will learn formulas, sorting and filtering, pivot tables and charts, and analyse a real dataset step by step.', 1, TIMESTAMP(CURDATE() - INTERVAL 167 DAY, '09:00:00')),
  (8, 5, 8, 'English for IT Specialists', 'Intermediate', 28000, 12, 'english.svg', 'videos/english-intro.mp4', 'Technical vocabulary, documentation reading and job interviews in English.', 'Improve your English for a career in IT. You will learn technical vocabulary, read documentation, write clear emails and practise real job interview questions with a native speaker.', 1, TIMESTAMP(CURDATE() - INTERVAL 158 DAY, '09:00:00')),
  (9, 4, 9, 'SQL & Database Design', 'Intermediate', 36000, 8, 'sql.svg', 'videos/sql-intro.mp4', 'Design relational databases and write JOIN, GROUP BY and subqueries.', 'Learn how real applications store data. You will design tables with primary and foreign keys, normalise a schema, and write SELECT queries with JOIN, LEFT JOIN, GROUP BY, HAVING, subqueries and transactions in MySQL.', 1, TIMESTAMP(CURDATE() - INTERVAL 149 DAY, '09:00:00')),
  (10, 1, 3, 'Advanced JavaScript: AJAX & APIs', 'Advanced', 45000, 6, 'ajax.svg', 'videos/ajax-intro.mp4', 'Fetch API, JSON, REST endpoints and asynchronous code with async/await.', 'Take your JavaScript to the next level. You will work with Promises and async/await, send requests with the Fetch API, exchange JSON with a server, handle loading states and errors, and build a single-page dashboard.', 1, TIMESTAMP(CURDATE() - INTERVAL 140 DAY, '09:00:00')),
  (11, 6, 8, 'Business Communication Skills', 'Beginner', 22000, 4, 'business.svg', 'videos/business-intro.mp4', 'Write clear emails, run meetings and present your ideas with confidence.', 'Communication is the most important soft skill in any company. You will practise business emails, short presentations, meetings and negotiations, and get personal feedback from the teacher.', 1, TIMESTAMP(CURDATE() - INTERVAL 131 DAY, '09:00:00')),
  (12, 6, 10, 'Project Management Basics', 'Intermediate', 38000, 6, 'pm.svg', 'videos/pm-intro.mp4', 'Plan projects, manage tasks and teams with Agile, Scrum and Kanban.', 'Learn how projects are planned and delivered. You will define goals and scope, build a schedule, estimate risks, and run a small project with Scrum and a Kanban board.', 1, TIMESTAMP(CURDATE() - INTERVAL 122 DAY, '09:00:00'));

INSERT INTO course_features (id, course_id, feature, position) VALUES
  (1, 1, '24 video lessons', 1),
  (2, 1, 'Weekly practical homework', 2),
  (3, 1, 'Final mini-project', 3),
  (4, 1, 'Certificate of completion', 4),
  (5, 2, 'Semantic HTML5', 1),
  (6, 2, 'Flexbox & CSS Grid', 2),
  (7, 2, 'Responsive design', 3),
  (8, 2, 'Portfolio landing page', 4),
  (9, 3, 'DOM manipulation', 1),
  (10, 3, 'Events & forms', 2),
  (11, 3, 'Fetch API basics', 3),
  (12, 3, '3 practical projects', 4),
  (13, 4, 'Forms with $_GET / $_POST', 1),
  (14, 4, 'Sessions & authentication', 2),
  (15, 4, 'MySQL + PDO', 3),
  (16, 4, 'CRUD application', 4),
  (17, 5, 'User research', 1),
  (18, 5, 'Wireframes & prototypes', 2),
  (19, 5, 'Figma practice', 3),
  (20, 5, 'Design review sessions', 4),
  (21, 6, 'Content planning', 1),
  (22, 6, 'Instagram & TikTok', 2),
  (23, 6, 'Targeted ads basics', 3),
  (24, 6, 'Analytics reports', 4),
  (25, 7, 'Formulas & functions', 1),
  (26, 7, 'Pivot tables', 2),
  (27, 7, 'Charts & dashboards', 3),
  (28, 7, 'Real dataset practice', 4),
  (29, 8, 'IT vocabulary', 1),
  (30, 8, 'Reading documentation', 2),
  (31, 8, 'Writing emails', 3),
  (32, 8, 'Mock job interviews', 4),
  (33, 9, 'ER diagrams & normalisation', 1),
  (34, 9, 'JOIN and subqueries', 2),
  (35, 9, 'GROUP BY & HAVING', 3),
  (36, 9, 'Transactions & indexes', 4),
  (37, 10, 'Promises & async/await', 1),
  (38, 10, 'Fetch API & JSON', 2),
  (39, 10, 'REST endpoints', 3),
  (40, 10, 'Error & loading states', 4),
  (41, 11, 'Business emails', 1),
  (42, 11, 'Presentations', 2),
  (43, 11, 'Meetings & negotiations', 3),
  (44, 11, 'Personal feedback', 4),
  (45, 12, 'Scope & planning', 1),
  (46, 12, 'Agile, Scrum, Kanban', 2),
  (47, 12, 'Risk management', 3),
  (48, 12, 'Team project', 4);

INSERT INTO course_groups (id, course_id, name, format, start_date, end_date, capacity, location) VALUES
  (1, 1, 'Group A', 'Online', (CURDATE() - INTERVAL 21 DAY), (CURDATE() + INTERVAL 34 DAY), 20, 'Zoom'),
  (2, 1, 'Group B', 'Offline', (CURDATE() + INTERVAL 10 DAY), (CURDATE() + INTERVAL 65 DAY), 15, 'KBTU, room 301'),
  (3, 2, 'Group A', 'Offline', (CURDATE() - INTERVAL 14 DAY), (CURDATE() + INTERVAL 27 DAY), 15, 'KBTU, room 214'),
  (4, 2, 'Group B', 'Online', (CURDATE() + INTERVAL 7 DAY), (CURDATE() + INTERVAL 48 DAY), 20, 'Zoom'),
  (5, 3, 'Group A', 'Online', (CURDATE() - INTERVAL 28 DAY), (CURDATE() + INTERVAL 27 DAY), 20, 'Zoom'),
  (6, 3, 'Group B', 'Hybrid', (CURDATE() + INTERVAL 12 DAY), (CURDATE() + INTERVAL 67 DAY), 18, 'KBTU, room 105 + Zoom'),
  (7, 4, 'Group A', 'Online', (CURDATE() - INTERVAL 35 DAY), (CURDATE() + INTERVAL 34 DAY), 20, 'Zoom'),
  (8, 4, 'Group B', 'Offline', (CURDATE() + INTERVAL 14 DAY), (CURDATE() + INTERVAL 83 DAY), 12, 'KBTU, room 402'),
  (9, 5, 'Group A', 'Offline', (CURDATE() - INTERVAL 7 DAY), (CURDATE() + INTERVAL 34 DAY), 3, 'KBTU design lab'),
  (10, 5, 'Group B', 'Online', (CURDATE() + INTERVAL 5 DAY), (CURDATE() + INTERVAL 46 DAY), 20, 'Zoom'),
  (11, 6, 'Group A', 'Online', (CURDATE() - INTERVAL 10 DAY), (CURDATE() + INTERVAL 17 DAY), 25, 'Zoom'),
  (12, 6, 'Group B', 'Online', (CURDATE() + INTERVAL 9 DAY), (CURDATE() + INTERVAL 36 DAY), 25, 'Zoom'),
  (13, 7, 'Group A', 'Online', (CURDATE() - INTERVAL 20 DAY), (CURDATE() + INTERVAL 14 DAY), 30, 'Zoom'),
  (14, 7, 'Group B', 'Online', (CURDATE() + INTERVAL 4 DAY), (CURDATE() + INTERVAL 38 DAY), 30, 'Zoom'),
  (15, 8, 'Group A', 'Offline', (CURDATE() - INTERVAL 100 DAY), (CURDATE() - INTERVAL 17 DAY), 12, 'KBTU, room 118'),
  (16, 8, 'Group B', 'Online', (CURDATE() + INTERVAL 6 DAY), (CURDATE() + INTERVAL 89 DAY), 15, 'Zoom'),
  (17, 9, 'Group A', 'Hybrid', (CURDATE() - INTERVAL 12 DAY), (CURDATE() + INTERVAL 43 DAY), 16, 'KBTU, room 220 + Zoom'),
  (18, 9, 'Group B', 'Online', (CURDATE() + INTERVAL 8 DAY), (CURDATE() + INTERVAL 63 DAY), 20, 'Zoom'),
  (19, 10, 'Group A', 'Online', (CURDATE() - INTERVAL 5 DAY), (CURDATE() + INTERVAL 36 DAY), 15, 'Zoom'),
  (20, 10, 'Group B', 'Hybrid', (CURDATE() + INTERVAL 20 DAY), (CURDATE() + INTERVAL 61 DAY), 15, 'KBTU, room 105 + Zoom'),
  (21, 11, 'Group A', 'Offline', (CURDATE() - INTERVAL 60 DAY), (CURDATE() - INTERVAL 33 DAY), 15, 'KBTU, room 330'),
  (22, 11, 'Group B', 'Online', (CURDATE() + INTERVAL 11 DAY), (CURDATE() + INTERVAL 38 DAY), 20, 'Zoom'),
  (23, 12, 'Group A', 'Online', (CURDATE() - INTERVAL 3 DAY), (CURDATE() + INTERVAL 38 DAY), 20, 'Zoom'),
  (24, 12, 'Group B', 'Offline', (CURDATE() + INTERVAL 15 DAY), (CURDATE() + INTERVAL 56 DAY), 15, 'KBTU, room 410');

INSERT INTO group_sessions (id, group_id, weekday, start_time, end_time) VALUES
  (1, 1, 1, '14:00:00', '15:30:00'),
  (2, 1, 3, '14:00:00', '15:30:00'),
  (3, 2, 6, '10:00:00', '13:00:00'),
  (4, 3, 1, '14:00:00', '15:30:00'),
  (5, 3, 3, '14:00:00', '15:30:00'),
  (6, 4, 2, '19:00:00', '20:30:00'),
  (7, 4, 4, '19:00:00', '20:30:00'),
  (8, 5, 2, '14:00:00', '15:30:00'),
  (9, 5, 4, '14:00:00', '15:30:00'),
  (10, 6, 1, '19:00:00', '20:30:00'),
  (11, 6, 3, '19:00:00', '20:30:00'),
  (12, 7, 2, '19:00:00', '20:30:00'),
  (13, 7, 4, '19:00:00', '20:30:00'),
  (14, 8, 6, '14:00:00', '17:00:00'),
  (15, 9, 3, '10:00:00', '11:30:00'),
  (16, 9, 5, '10:00:00', '11:30:00'),
  (17, 10, 1, '14:30:00', '16:00:00'),
  (18, 10, 3, '14:30:00', '16:00:00'),
  (19, 11, 5, '19:00:00', '21:00:00'),
  (20, 12, 7, '11:00:00', '13:00:00'),
  (21, 13, 1, '19:00:00', '20:30:00'),
  (22, 13, 3, '19:00:00', '20:30:00'),
  (23, 14, 6, '10:00:00', '12:00:00'),
  (24, 15, 2, '09:00:00', '10:30:00'),
  (25, 15, 4, '09:00:00', '10:30:00'),
  (26, 16, 1, '18:00:00', '19:00:00'),
  (27, 16, 3, '18:00:00', '19:00:00'),
  (28, 16, 5, '18:00:00', '19:00:00'),
  (29, 17, 2, '14:00:00', '15:30:00'),
  (30, 17, 4, '14:00:00', '15:30:00'),
  (31, 18, 6, '15:00:00', '18:00:00'),
  (32, 19, 1, '19:00:00', '20:30:00'),
  (33, 19, 3, '19:00:00', '20:30:00'),
  (34, 20, 2, '19:00:00', '20:30:00'),
  (35, 20, 4, '19:00:00', '20:30:00'),
  (36, 21, 5, '14:00:00', '16:00:00'),
  (37, 22, 7, '15:00:00', '17:00:00'),
  (38, 23, 3, '19:00:00', '21:00:00'),
  (39, 24, 6, '11:00:00', '13:00:00');

INSERT INTO enrollments (id, user_id, group_id, status, price_paid, phone, comment, created_at) VALUES
  (10451, 2, 1, 'Confirmed', 30000, '+77011234567', NULL, TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '10:51:00')),
  (10452, 2, 15, 'Completed', 28000, '+77011234567', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 110 DAY, '20:25:00')),
  (10453, 2, 18, 'Pending', 36000, '+77011234567', NULL, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '16:25:00')),
  (10454, 2, 11, 'Cancelled', 20000, '+77011234567', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 15 DAY, '20:05:00')),
  (10455, 3, 9, 'Confirmed', 35000, '+77016433012', NULL, TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '20:10:00')),
  (10456, 4, 9, 'Confirmed', 35000, '+77012215279', NULL, TIMESTAMP(CURDATE() - INTERVAL 19 DAY, '11:08:00')),
  (10457, 5, 9, 'Pending', 35000, '+77011973060', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '09:09:00')),
  (10458, 3, 3, 'Confirmed', 25000, '+77016433012', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 107 DAY, '18:57:00')),
  (10459, 3, 19, 'Pending', 45000, '+77016433012', NULL, TIMESTAMP(CURDATE() - INTERVAL 43 DAY, '16:51:00')),
  (10460, 4, 17, 'Confirmed', 36000, '+77012215279', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 56 DAY, '19:09:00')),
  (10461, 4, 16, 'Pending', 28000, '+77012215279', NULL, TIMESTAMP(CURDATE() - INTERVAL 99 DAY, '18:52:00')),
  (10462, 4, 11, 'Pending', 20000, '+77012215279', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 30 DAY, '18:30:00')),
  (10463, 5, 15, 'Completed', 28000, '+77011973060', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 117 DAY, '19:59:00')),
  (10464, 5, 20, 'Pending', 45000, '+77011973060', NULL, TIMESTAMP(CURDATE() - INTERVAL 73 DAY, '14:09:00')),
  (10465, 5, 3, 'Confirmed', 25000, '+77011973060', NULL, TIMESTAMP(CURDATE() - INTERVAL 149 DAY, '17:35:00')),
  (10466, 6, 17, 'Confirmed', 36000, '+77018275367', NULL, TIMESTAMP(CURDATE() - INTERVAL 111 DAY, '11:01:00')),
  (10467, 6, 14, 'Pending', 0, '+77018275367', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 46 DAY, '09:51:00')),
  (10468, 7, 11, 'Confirmed', 20000, '+77018122250', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 103 DAY, '20:41:00')),
  (10469, 7, 5, 'Confirmed', 32000, '+77018122250', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 133 DAY, '10:33:00')),
  (10470, 8, 14, 'Pending', 0, '+77012037872', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 76 DAY, '20:59:00')),
  (10471, 8, 22, 'Confirmed', 22000, '+77012037872', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 138 DAY, '11:27:00')),
  (10472, 9, 18, 'Pending', 36000, '+77014709137', NULL, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '12:52:00')),
  (10473, 9, 19, 'Pending', 45000, '+77014709137', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 27 DAY, '12:01:00')),
  (10474, 10, 11, 'Pending', 20000, '+77015858837', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 68 DAY, '13:13:00')),
  (10475, 10, 23, 'Confirmed', 38000, '+77015858837', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 47 DAY, '13:32:00')),
  (10476, 11, 16, 'Pending', 28000, '+77016175466', NULL, TIMESTAMP(CURDATE() - INTERVAL 122 DAY, '12:48:00')),
  (10477, 11, 19, 'Confirmed', 45000, '+77016175466', NULL, TIMESTAMP(CURDATE() - INTERVAL 58 DAY, '18:20:00')),
  (10478, 11, 3, 'Confirmed', 25000, '+77016175466', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 83 DAY, '13:34:00')),
  (10479, 12, 16, 'Pending', 28000, '+77014151952', NULL, TIMESTAMP(CURDATE() - INTERVAL 144 DAY, '15:53:00')),
  (10480, 13, 22, 'Pending', 22000, '+77012053424', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 132 DAY, '11:03:00')),
  (10481, 13, 3, 'Confirmed', 25000, '+77012053424', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 77 DAY, '20:22:00')),
  (10482, 13, 2, 'Pending', 30000, '+77012053424', NULL, TIMESTAMP(CURDATE() - INTERVAL 152 DAY, '16:42:00')),
  (10483, 14, 10, 'Confirmed', 35000, '+77019328453', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 132 DAY, '18:52:00')),
  (10484, 14, 21, 'Completed', 22000, '+77019328453', 'Looking forward to the course!', TIMESTAMP(CURDATE() - INTERVAL 126 DAY, '17:26:00')),
  (10485, 14, 19, 'Confirmed', 45000, '+77019328453', NULL, TIMESTAMP(CURDATE() - INTERVAL 49 DAY, '17:08:00')),
  (10486, 15, 10, 'Pending', 35000, '+77016270514', 'I would like to sit near the window :)', TIMESTAMP(CURDATE() - INTERVAL 46 DAY, '17:09:00')),
  (10487, 15, 23, 'Pending', 38000, '+77016270514', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 47 DAY, '17:32:00')),
  (10488, 16, 12, 'Pending', 20000, '+77016029255', 'Can I get the recording of missed lessons?', TIMESTAMP(CURDATE() - INTERVAL 144 DAY, '09:55:00')),
  (10489, 16, 1, 'Confirmed', 30000, '+77016029255', NULL, TIMESTAMP(CURDATE() - INTERVAL 48 DAY, '16:49:00')),
  (10490, 16, 15, 'Completed', 28000, '+77016029255', NULL, TIMESTAMP(CURDATE() - INTERVAL 104 DAY, '11:38:00'));

INSERT INTO lessons (id, course_id, position, title, summary, video_url, duration_min, is_preview) VALUES
  (1, 1, 1, 'Welcome to Introduction to Python', 'Meet the teacher, see how the course is organised and what you will build. Learn Python syntax, data types, loops and functions from zero.', 'videos/python-intro.mp4', 1, 1),
  (2, 1, 2, 'Python basics: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=eWRfhZUzrAc', 240, 0),
  (3, 1, 3, 'Practice: loops, lists and functions', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (4, 2, 1, 'Welcome to Web Design Fundamentals', 'Meet the teacher, see how the course is organised and what you will build. Build beautiful pages with HTML5 and CSS3: Flexbox, Grid, responsive layout.', 'videos/webdesign-intro.mp4', 1, 1),
  (5, 2, 2, 'HTML & CSS: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=a_iQb1lnAEQ', 240, 0),
  (6, 2, 3, 'Practice: a responsive landing page', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (7, 3, 1, 'Welcome to JavaScript for Beginners', 'Meet the teacher, see how the course is organised and what you will build. Make websites interactive: variables, functions, events and the DOM.', 'videos/javascript-intro.mp4', 1, 1),
  (8, 3, 2, 'JavaScript basics: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=jS4aFq5-91M', 240, 0),
  (9, 3, 3, 'Practice: DOM, events and form validation', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (10, 4, 1, 'Welcome to PHP & MySQL Backend', 'Meet the teacher, see how the course is organised and what you will build. Server-side development: forms, sessions, databases and CRUD.', 'videos/php-intro.mp4', 1, 1),
  (11, 4, 2, 'PHP programming: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=OK_JCtrrv-c', 240, 0),
  (12, 4, 3, 'Practice: login, sessions and CRUD with PDO', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (13, 5, 1, 'Welcome to UI/UX Design Principles', 'Meet the teacher, see how the course is organised and what you will build. Design user-friendly interfaces: research, wireframes and prototypes in Figma.', 'videos/uiux-intro.mp4', 1, 1),
  (14, 5, 2, 'Figma for UI/UX: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=mT_Jjn8RJdo', 240, 0),
  (15, 5, 3, 'Practice: wireframes and a clickable prototype', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (16, 6, 1, 'Welcome to Social Media Marketing 101', 'Meet the teacher, see how the course is organised and what you will build. Create effective campaigns on Instagram, TikTok and Telegram.', 'videos/smm-intro.mp4', 1, 1),
  (17, 6, 2, 'Content planning: key ideas', 'A short video with the key ideas of the topic, followed by examples from real companies.', 'videos/smm-lesson2.mp4', 1, 0),
  (18, 6, 3, 'Practice: a 2-week content plan', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (19, 7, 1, 'Welcome to Data Analysis with Excel', 'Meet the teacher, see how the course is organised and what you will build. Free course: formulas, pivot tables and charts for everyday analytics.', 'videos/excel-intro.mp4', 1, 1),
  (20, 7, 2, 'Excel: full video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=Vl0H-qTclOg', 240, 0),
  (21, 7, 3, 'Practice: pivot tables and a dashboard', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (22, 8, 1, 'Welcome to English for IT Specialists', 'Meet the teacher, see how the course is organised and what you will build. Technical vocabulary, documentation reading and job interviews in English.', 'videos/english-intro.mp4', 1, 1),
  (23, 8, 2, 'IT vocabulary: key ideas', 'A short video with the key ideas of the topic, followed by examples from real companies.', 'videos/english-lesson2.mp4', 1, 0),
  (24, 8, 3, 'Practice: reading documentation and writing emails', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (25, 9, 1, 'Welcome to SQL & Database Design', 'Meet the teacher, see how the course is organised and what you will build. Design relational databases and write JOIN, GROUP BY and subqueries.', 'videos/sql-intro.mp4', 1, 1),
  (26, 9, 2, 'SQL: full database course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=HXV3zeQKqGY', 240, 0),
  (27, 9, 3, 'Practice: JOIN, GROUP BY and subqueries', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (28, 10, 1, 'Welcome to Advanced JavaScript: AJAX & APIs', 'Meet the teacher, see how the course is organised and what you will build. Fetch API, JSON, REST endpoints and asynchronous code with async/await.', 'videos/ajax-intro.mp4', 1, 1),
  (29, 10, 2, 'HTTP and the Fetch API: video course', 'A complete video lesson by freeCodeCamp that covers the main topics of the course. Watch it in parts and take notes: we discuss every part in class.', 'https://www.youtube.com/watch?v=2JYT5f2isg4', 240, 0),
  (30, 10, 3, 'Practice: a dashboard with fetch and JSON', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (31, 11, 1, 'Welcome to Business Communication Skills', 'Meet the teacher, see how the course is organised and what you will build. Write clear emails, run meetings and present your ideas with confidence.', 'videos/business-intro.mp4', 1, 1),
  (32, 11, 2, 'Business emails: key ideas', 'A short video with the key ideas of the topic, followed by examples from real companies.', 'videos/business-lesson2.mp4', 1, 0),
  (33, 11, 3, 'Practice: an email and a short presentation', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0),
  (34, 12, 1, 'Welcome to Project Management Basics', 'Meet the teacher, see how the course is organised and what you will build. Plan projects, manage tasks and teams with Agile, Scrum and Kanban.', 'videos/pm-intro.mp4', 1, 1),
  (35, 12, 2, 'Scope & planning: key ideas', 'A short video with the key ideas of the topic, followed by examples from real companies.', 'videos/pm-lesson2.mp4', 1, 0),
  (36, 12, 3, 'Practice: a Scrum sprint on a Kanban board', 'Practical task for the topics of the course. Download the PDF to see the task, the checklist and the grading criteria, and use the documentation link while you work.', NULL, 45, 0);

INSERT INTO materials (id, lesson_id, title, type, url) VALUES
  (1, 1, 'Course syllabus (PDF)', 'pdf', 'materials/python-syllabus.pdf'),
  (2, 2, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/python-programming-course/'),
  (3, 2, 'Official documentation / reference', 'link', 'https://docs.python.org/3/tutorial/'),
  (4, 3, 'Practice task and checklist (PDF)', 'pdf', 'materials/python-syllabus.pdf'),
  (5, 4, 'Course syllabus (PDF)', 'pdf', 'materials/webdesign-syllabus.pdf'),
  (6, 5, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/html-and-css-course/'),
  (7, 5, 'Official documentation / reference', 'link', 'https://developer.mozilla.org/en-US/docs/Web/CSS'),
  (8, 6, 'Practice task and checklist (PDF)', 'pdf', 'materials/webdesign-syllabus.pdf'),
  (9, 7, 'Course syllabus (PDF)', 'pdf', 'materials/javascript-syllabus.pdf'),
  (10, 8, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/full-javascript-course-for-beginners/'),
  (11, 8, 'Official documentation / reference', 'link', 'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide'),
  (12, 9, 'Practice task and checklist (PDF)', 'pdf', 'materials/javascript-syllabus.pdf'),
  (13, 10, 'Course syllabus (PDF)', 'pdf', 'materials/php-syllabus.pdf'),
  (14, 11, 'Official documentation / reference', 'link', 'https://www.php.net/manual/en/book.pdo.php'),
  (15, 12, 'Practice task and checklist (PDF)', 'pdf', 'materials/php-syllabus.pdf'),
  (16, 13, 'Course syllabus (PDF)', 'pdf', 'materials/uiux-syllabus.pdf'),
  (17, 14, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/learn-figma-for-ui-ux-design'),
  (18, 14, 'Official documentation / reference', 'link', 'https://help.figma.com/'),
  (19, 15, 'Practice task and checklist (PDF)', 'pdf', 'materials/uiux-syllabus.pdf'),
  (20, 16, 'Course syllabus (PDF)', 'pdf', 'materials/smm-syllabus.pdf'),
  (21, 17, 'Official documentation / reference', 'link', 'https://en.wikipedia.org/wiki/Social_media_marketing'),
  (22, 18, 'Practice task and checklist (PDF)', 'pdf', 'materials/smm-syllabus.pdf'),
  (23, 19, 'Course syllabus (PDF)', 'pdf', 'materials/excel-syllabus.pdf'),
  (24, 20, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/learn-microsoft-excel'),
  (25, 20, 'Official documentation / reference', 'link', 'https://support.microsoft.com/en-us/excel'),
  (26, 21, 'Practice task and checklist (PDF)', 'pdf', 'materials/excel-syllabus.pdf'),
  (27, 22, 'Course syllabus (PDF)', 'pdf', 'materials/english-syllabus.pdf'),
  (28, 23, 'Official documentation / reference', 'link', 'https://dictionary.cambridge.org/'),
  (29, 24, 'Practice task and checklist (PDF)', 'pdf', 'materials/english-syllabus.pdf'),
  (30, 25, 'Course syllabus (PDF)', 'pdf', 'materials/sql-syllabus.pdf'),
  (31, 26, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/learn-sql-full-course/'),
  (32, 26, 'Official documentation / reference', 'link', 'https://dev.mysql.com/doc/refman/8.0/en/tutorial.html'),
  (33, 27, 'Practice task and checklist (PDF)', 'pdf', 'materials/sql-syllabus.pdf'),
  (34, 28, 'Course syllabus (PDF)', 'pdf', 'materials/ajax-syllabus.pdf'),
  (35, 29, 'Lesson notes on freeCodeCamp', 'link', 'https://www.freecodecamp.org/news/http-full-course/'),
  (36, 29, 'Official documentation / reference', 'link', 'https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch'),
  (37, 30, 'Practice task and checklist (PDF)', 'pdf', 'materials/ajax-syllabus.pdf'),
  (38, 31, 'Course syllabus (PDF)', 'pdf', 'materials/business-syllabus.pdf'),
  (39, 32, 'Official documentation / reference', 'link', 'https://en.wikipedia.org/wiki/Business_communication'),
  (40, 33, 'Practice task and checklist (PDF)', 'pdf', 'materials/business-syllabus.pdf'),
  (41, 34, 'Course syllabus (PDF)', 'pdf', 'materials/pm-syllabus.pdf'),
  (42, 35, 'Official documentation / reference', 'link', 'https://en.wikipedia.org/wiki/Project_management'),
  (43, 36, 'Practice task and checklist (PDF)', 'pdf', 'materials/pm-syllabus.pdf');

INSERT INTO lesson_progress (user_id, lesson_id, completed_at) VALUES
  (2, 1, TIMESTAMP(CURDATE() - INTERVAL 26 DAY, '20:00:00')),
  (2, 2, TIMESTAMP(CURDATE() - INTERVAL 25 DAY, '20:00:00')),
  (2, 22, TIMESTAMP(CURDATE() - INTERVAL 106 DAY, '20:00:00')),
  (2, 23, TIMESTAMP(CURDATE() - INTERVAL 105 DAY, '20:00:00')),
  (2, 24, TIMESTAMP(CURDATE() - INTERVAL 104 DAY, '20:00:00')),
  (4, 13, TIMESTAMP(CURDATE() - INTERVAL 15 DAY, '20:00:00')),
  (4, 14, TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '20:00:00')),
  (3, 4, TIMESTAMP(CURDATE() - INTERVAL 103 DAY, '20:00:00')),
  (5, 22, TIMESTAMP(CURDATE() - INTERVAL 113 DAY, '20:00:00')),
  (5, 23, TIMESTAMP(CURDATE() - INTERVAL 112 DAY, '20:00:00')),
  (5, 24, TIMESTAMP(CURDATE() - INTERVAL 111 DAY, '20:00:00')),
  (5, 4, TIMESTAMP(CURDATE() - INTERVAL 145 DAY, '20:00:00')),
  (5, 5, TIMESTAMP(CURDATE() - INTERVAL 144 DAY, '20:00:00')),
  (6, 25, TIMESTAMP(CURDATE() - INTERVAL 107 DAY, '20:00:00')),
  (6, 26, TIMESTAMP(CURDATE() - INTERVAL 106 DAY, '20:00:00')),
  (7, 7, TIMESTAMP(CURDATE() - INTERVAL 129 DAY, '20:00:00')),
  (7, 8, TIMESTAMP(CURDATE() - INTERVAL 128 DAY, '20:00:00')),
  (8, 31, TIMESTAMP(CURDATE() - INTERVAL 134 DAY, '20:00:00')),
  (10, 34, TIMESTAMP(CURDATE() - INTERVAL 43 DAY, '20:00:00')),
  (10, 35, TIMESTAMP(CURDATE() - INTERVAL 42 DAY, '20:00:00')),
  (11, 4, TIMESTAMP(CURDATE() - INTERVAL 79 DAY, '20:00:00')),
  (11, 5, TIMESTAMP(CURDATE() - INTERVAL 78 DAY, '20:00:00')),
  (13, 4, TIMESTAMP(CURDATE() - INTERVAL 73 DAY, '20:00:00')),
  (14, 13, TIMESTAMP(CURDATE() - INTERVAL 128 DAY, '20:00:00')),
  (14, 14, TIMESTAMP(CURDATE() - INTERVAL 127 DAY, '20:00:00')),
  (14, 31, TIMESTAMP(CURDATE() - INTERVAL 122 DAY, '20:00:00')),
  (14, 32, TIMESTAMP(CURDATE() - INTERVAL 121 DAY, '20:00:00')),
  (14, 33, TIMESTAMP(CURDATE() - INTERVAL 120 DAY, '20:00:00')),
  (14, 28, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '20:00:00')),
  (16, 22, TIMESTAMP(CURDATE() - INTERVAL 100 DAY, '20:00:00')),
  (16, 23, TIMESTAMP(CURDATE() - INTERVAL 99 DAY, '20:00:00')),
  (16, 24, TIMESTAMP(CURDATE() - INTERVAL 98 DAY, '20:00:00'));

INSERT INTO reviews (id, user_id, course_id, rating, comment, created_at) VALUES
  (1, 2, 1, 5, 'Excellent course, very practical!', TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '18:30:00')),
  (2, 2, 8, 5, 'Great structure and useful homework.', TIMESTAMP(CURDATE() - INTERVAL 100 DAY, '18:30:00')),
  (3, 3, 5, 4, 'Useful materials, sometimes a bit fast.', TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '18:30:00')),
  (4, 4, 5, 4, 'Good course, but I wanted more practice.', TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '18:30:00')),
  (5, 3, 2, 4, 'Good course, but I wanted more practice.', TIMESTAMP(CURDATE() - INTERVAL 97 DAY, '18:30:00')),
  (6, 4, 9, 4, 'Useful materials, sometimes a bit fast.', TIMESTAMP(CURDATE() - INTERVAL 46 DAY, '18:30:00')),
  (7, 5, 8, 3, 'Okay for beginners, nothing special.', TIMESTAMP(CURDATE() - INTERVAL 107 DAY, '18:30:00')),
  (8, 6, 9, 4, 'Useful materials, sometimes a bit fast.', TIMESTAMP(CURDATE() - INTERVAL 101 DAY, '18:30:00')),
  (9, 7, 6, 4, 'Useful materials, sometimes a bit fast.', TIMESTAMP(CURDATE() - INTERVAL 93 DAY, '18:30:00')),
  (10, 7, 3, 3, 'Okay for beginners, nothing special.', TIMESTAMP(CURDATE() - INTERVAL 123 DAY, '18:30:00')),
  (11, 10, 12, 5, 'Excellent course, very practical!', TIMESTAMP(CURDATE() - INTERVAL 37 DAY, '18:30:00')),
  (12, 11, 10, 5, 'The teacher explains everything clearly.', TIMESTAMP(CURDATE() - INTERVAL 48 DAY, '18:30:00')),
  (13, 11, 2, 4, 'Good course, but I wanted more practice.', TIMESTAMP(CURDATE() - INTERVAL 73 DAY, '18:30:00')),
  (14, 13, 2, 3, 'Okay for beginners, nothing special.', TIMESTAMP(CURDATE() - INTERVAL 67 DAY, '18:30:00')),
  (15, 14, 5, 5, 'Great structure and useful homework.', TIMESTAMP(CURDATE() - INTERVAL 122 DAY, '18:30:00')),
  (16, 14, 11, 5, 'The teacher explains everything clearly.', TIMESTAMP(CURDATE() - INTERVAL 116 DAY, '18:30:00')),
  (17, 14, 10, 5, 'The teacher explains everything clearly.', TIMESTAMP(CURDATE() - INTERVAL 39 DAY, '18:30:00')),
  (18, 16, 8, 4, 'Useful materials, sometimes a bit fast.', TIMESTAMP(CURDATE() - INTERVAL 94 DAY, '18:30:00'));

INSERT INTO contact_messages (id, user_id, name, email, message, is_read, created_at) VALUES
  (1, NULL, 'Aliya Karimova', 'aliya.k@mail.kz', 'Hello! Do you have courses for school students (14 years old)?', 0, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '12:00:00')),
  (2, 5, 'Aigerim Kairatova', 'aigerim.kairatova@mail.kz', 'Is it possible to move to another group of the same course?', 1, TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '12:00:00')),
  (3, NULL, 'Marat Suleimen', 'marat.s@mail.kz', 'Do you issue invoices for companies? We want to train 5 employees.', 0, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '12:00:00'));
