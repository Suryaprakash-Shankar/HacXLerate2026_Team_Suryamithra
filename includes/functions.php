<?php
require_once __DIR__ . '/../config/config.php';

function auto_seed_database(PDO $pdo): void {
    mt_srand(2026);
    $hash = password_hash('password', PASSWORD_DEFAULT);

    // Create database schema if not already present
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $queries = explode(';', $sql);
        foreach ($queries as $q) {
            $q = trim($q);
            if (!empty($q)) {
                try { $pdo->exec($q); } catch (Exception $e) {}
            }
        }
    }

    // Insert Roles if empty
    $rolesCount = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($rolesCount === 0) {
        $pdo->exec("INSERT INTO roles (code,label) VALUES 
            ('admin','Administrator'),
            ('hod','HOD / Head of Dept'),
            ('faculty','Teacher / Faculty'),
            ('placement','Placement Officer'),
            ('student','Student')");
    }

    // Insert Default Staff Users (Admin, 19 HODs, Faculty, Placement) if not present
    $uStmt = $pdo->prepare('INSERT IGNORE INTO users (id,name,email,password_hash,role,department,added_by) VALUES (?,?,?,?,?,?,?)');
    
    // Admin
    $uStmt->execute([1, 'Dr. Meenakshi Rao', 'admin@suryamithra.local', $hash, 'admin', 'Management', null]);
    $adminId = 1;

    // 19 HODs from PDF
    $hodsSeed = [
        [2, 'Dr. Aravind Kumar', 'hod_aero@suryamithra.local', password_hash('aero@2026', PASSWORD_DEFAULT), 'hod', 'AERO', $adminId],
        [3, 'Dr. Meena Krishnan', 'hod_agri@suryamithra.local', password_hash('agri@2026', PASSWORD_DEFAULT), 'hod', 'AGRI', $adminId],
        [4, 'Dr. Karthik Raj', 'hod_aids@suryamithra.local', password_hash('aids@2026', PASSWORD_DEFAULT), 'hod', 'AIDS', $adminId],
        [5, 'Dr. Priya Natarajan', 'hod_bme@suryamithra.local', password_hash('bme@2026', PASSWORD_DEFAULT), 'hod', 'BME', $adminId],
        [6, 'Dr. S. Kavitha', 'hod_bt@suryamithra.local', password_hash('bt@2026', PASSWORD_DEFAULT), 'hod', 'BT', $adminId],
        [7, 'Dr. V. Raghavan', 'hod_chem@suryamithra.local', password_hash('chem@2026', PASSWORD_DEFAULT), 'hod', 'CHEM', $adminId],
        [8, 'Dr. Arun Prakash', 'hod_civil@suryamithra.local', password_hash('civil@2026', PASSWORD_DEFAULT), 'hod', 'CIVIL', $adminId],
        [9, 'Dr. Suresh Babu', 'hod_cse@suryamithra.local', password_hash('cse@2026', PASSWORD_DEFAULT), 'hod', 'CSE', $adminId],
        [10, 'Dr. P. Srinivas', 'hod_aiml@suryamithra.local', password_hash('aiml@2026', PASSWORD_DEFAULT), 'hod', 'AIML', $adminId],
        [11, 'Dr. M. Surya', 'hod_iot@suryamithra.local', password_hash('iot@2026', PASSWORD_DEFAULT), 'hod', 'IOT', $adminId],
        [12, 'Dr. Naveen Kumar', 'hod_cyber@suryamithra.local', password_hash('cyber@2026', PASSWORD_DEFAULT), 'hod', 'CYBER', $adminId],
        [13, 'Dr. R. Mahendran', 'hod_eee@suryamithra.local', password_hash('eee@2026', PASSWORD_DEFAULT), 'hod', 'EEE', $adminId],
        [14, 'Dr. Anitha Devi', 'hod_ece@suryamithra.local', password_hash('ece@2026', PASSWORD_DEFAULT), 'hod', 'ECE', $adminId],
        [15, 'Dr. Gokul Raj', 'hod_ft@suryamithra.local', password_hash('ft@2026', PASSWORD_DEFAULT), 'hod', 'FT', $adminId],
        [16, 'Dr. S. Pradeep', 'hod_it@suryamithra.local', password_hash('it@2026', PASSWORD_DEFAULT), 'hod', 'IT', $adminId],
        [17, 'Dr. Ramesh Kumar', 'hod_mech@suryamithra.local', password_hash('mech@2026', PASSWORD_DEFAULT), 'hod', 'MECH', $adminId],
        [18, 'Dr. Lakshmi Narayanan', 'hod_mct@suryamithra.local', password_hash('mct@2026', PASSWORD_DEFAULT), 'hod', 'MCT', $adminId],
        [19, 'Dr. Divya Mohan', 'hod_pt@suryamithra.local', password_hash('pt@2026', PASSWORD_DEFAULT), 'hod', 'PT', $adminId],
        [20, 'Dr. Vignesh Kumar', 'hod_ra@suryamithra.local', password_hash('ra@2026', PASSWORD_DEFAULT), 'hod', 'RA', $adminId],
    ];

    foreach ($hodsSeed as $hRow) {
        $uStmt->execute($hRow);
    }
    $hodCseId = 9;

    // Class Tutors / Faculty Users across departments
    $facultySeed = [
        [21, 'Prof. Karthik Subramanian', 'faculty@suryamithra.local', $hash, 'faculty', 'CSE', 9],
        [22, 'Prof. Sunita Rao', 'teacher_cse2@suryamithra.local', $hash, 'faculty', 'CSE', 9],
        [23, 'Prof. R. Anand', 'teacher_aero@suryamithra.local', $hash, 'faculty', 'AERO', 2],
        [24, 'Prof. S. Malathi', 'teacher_agri@suryamithra.local', $hash, 'faculty', 'AGRI', 3],
        [25, 'Prof. K. Venkatesh', 'teacher_aids@suryamithra.local', $hash, 'faculty', 'AIDS', 4],
        [26, 'Prof. G. Shalini', 'teacher_aiml@suryamithra.local', $hash, 'faculty', 'AIML', 10],
        [27, 'Prof. N. Balaji', 'teacher_bme@suryamithra.local', $hash, 'faculty', 'BME', 5],
        [28, 'Prof. R. Deepa', 'teacher_bt@suryamithra.local', $hash, 'faculty', 'BT', 6],
        [29, 'Prof. M. Selvam', 'teacher_chem@suryamithra.local', $hash, 'faculty', 'CHEM', 7],
        [30, 'Prof. T. Vijay', 'teacher_civil@suryamithra.local', $hash, 'faculty', 'CIVIL', 8],
        [31, 'Prof. P. Harini', 'teacher_cyber@suryamithra.local', $hash, 'faculty', 'CYBER', 12],
        [32, 'Prof. V. Rajesh', 'teacher_ece@suryamithra.local', $hash, 'faculty', 'ECE', 14],
        [33, 'Prof. S. Jayanti', 'teacher_eee@suryamithra.local', $hash, 'faculty', 'EEE', 13],
        [34, 'Prof. K. Mohan', 'teacher_ft@suryamithra.local', $hash, 'faculty', 'FT', 15],
        [35, 'Prof. A. Dinesh', 'teacher_iot@suryamithra.local', $hash, 'faculty', 'IOT', 11],
        [36, 'Prof. Amit Varma', 'teacher_it@suryamithra.local', $hash, 'faculty', 'IT', 16],
        [37, 'Prof. B. Saravanan', 'teacher_mct@suryamithra.local', $hash, 'faculty', 'MCT', 18],
        [38, 'Prof. C. Ganesh', 'teacher_mech@suryamithra.local', $hash, 'faculty', 'MECH', 17],
        [39, 'Prof. D. Swathi', 'teacher_pt@suryamithra.local', $hash, 'faculty', 'PT', 19],
        [40, 'Prof. E. Murali', 'teacher_ra@suryamithra.local', $hash, 'faculty', 'RA', 20],
        [41, 'Divya Nair', 'placement@suryamithra.local', $hash, 'placement', 'Placement Cell', $adminId],
    ];

    foreach ($facultySeed as $fRow) {
        $uStmt->execute($fRow);
    }

    // Default Class Assignments
    $caStmt = $pdo->prepare('INSERT IGNORE INTO class_assignments (department, class_name, teacher_id) VALUES (?,?,?)');
    $classAssignSeed = [
        ['AERO', 'Class A', 23], ['AGRI', 'Class A', 24], ['AIDS', 'Class A', 25], ['AIML', 'Class A', 26],
        ['BME', 'Class A', 27], ['BT', 'Class A', 28], ['CHEM', 'Class A', 29], ['CIVIL', 'Class A', 30],
        ['CSE', 'Class A', 21], ['CSE', 'Class B', 22], ['CYBER', 'Class A', 31], ['ECE', 'Class A', 32],
        ['EEE', 'Class A', 33], ['FT', 'Class A', 34], ['IOT', 'Class A', 35], ['IT', 'Class A', 36],
        ['MCT', 'Class A', 37], ['MECH', 'Class A', 38], ['PT', 'Class A', 39], ['RA', 'Class A', 40]
    ];
    foreach ($classAssignSeed as $caRow) {
        $caStmt->execute($caRow);
    }

    // Job Roles and required skill levels
    seed_default_job_roles($pdo);
    $roleRows = $pdo->query('SELECT id, name FROM job_roles')->fetchAll();
    $roleIds = [];
    foreach ($roleRows as $r) {
        $roleIds[$r['name']] = (int)$r['id'];
    }

    $techSkills = ['Java', 'SQL', 'Spring Boot', 'REST API', 'Git', 'Docker', 'Python', 'Excel', 'Statistics', 'Power BI', 'JavaScript', 'React', 'Node.js', 'Linux', 'AWS'];
    $softSkills = ['Communication', 'Problem Solving', 'Teamwork', 'Leadership', 'Time Management', 'Critical Thinking'];

    $first = ['Arun', 'Priya', 'Karthik', 'Divya', 'Rahul', 'Sneha', 'Vignesh', 'Ananya', 'Surya', 'Lakshmi', 'Harish', 'Meera', 'Naveen', 'Pooja', 'Deepak', 'Kavya', 'Manoj', 'Revathi', 'Sanjay', 'Nithya', 'Ajay', 'Swetha', 'Gokul', 'Bhavana', 'Dinesh', 'Keerthi', 'Prakash', 'Janani', 'Ramesh', 'Shruthi'];
    $last  = ['Kumar', 'Raj', 'Iyer', 'Nair', 'Reddy', 'Sharma', 'Pillai', 'Menon', 'Das', 'Gupta', 'Patel', 'Rao'];
    $depts = ['AERO', 'AGRI', 'AIDS', 'AIML', 'BME', 'BT', 'CHEM', 'CIVIL', 'CSE', 'CYBER', 'ECE', 'EEE', 'FT', 'IOT', 'IT', 'MCT', 'MECH', 'PT', 'RA'];
    $teachersList = [21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40];

    $arch = [
      'star'     => ['n' => 8,  'cgpa' => [8.3, 9.6], 'bk' => [0, 0], 'int' => [78, 95], 'att' => [88, 98], 'login' => [18, 30], 'asg' => [85, 100], 'ev' => [4, 8], 'cl' => [1, 3], 'hk' => [1, 3], 'ce' => [2, 4], 'apt' => [75, 95], 'cod' => [72, 95], 'mock' => [70, 90], 'res' => [75, 95], 'app' => [3, 6], 'sat' => [4.2, 5], 'fac' => [4.2, 5], 'sk' => [68, 88], 'boost' => 8],
      'good'     => ['n' => 14, 'cgpa' => [7.3, 8.4], 'bk' => [0, 0], 'int' => [65, 82], 'att' => [78, 92], 'login' => [12, 22], 'asg' => [70, 92], 'ev' => [2, 6], 'cl' => [1, 2], 'hk' => [0, 2], 'ce' => [1, 3], 'apt' => [60, 82], 'cod' => [58, 82], 'mock' => [55, 80], 'res' => [60, 85], 'app' => [2, 5], 'sat' => [3.6, 4.6], 'fac' => [3.6, 4.6], 'sk' => [55, 76], 'boost' => 6],
      'avg'      => ['n' => 14, 'cgpa' => [6.6, 7.6], 'bk' => [0, 1], 'int' => [55, 72], 'att' => [70, 84], 'login' => [8, 16], 'asg' => [55, 78], 'ev' => [1, 4], 'cl' => [0, 2], 'hk' => [0, 1], 'ce' => [0, 2], 'apt' => [48, 70], 'cod' => [45, 68], 'mock' => [45, 68], 'res' => [50, 72], 'app' => [1, 3], 'sat' => [3.2, 4.2], 'fac' => [3.2, 4.2], 'sk' => [45, 65], 'boost' => 5],
      'weak'     => ['n' => 8,  'cgpa' => [5.4, 6.4], 'bk' => [1, 3], 'int' => [38, 55], 'att' => [52, 66], 'login' => [2, 8], 'asg' => [20, 45], 'ev' => [0, 1], 'cl' => [0, 1], 'hk' => [0, 0], 'ce' => [0, 1], 'apt' => [30, 52], 'cod' => [28, 48], 'mock' => [28, 48], 'res' => [35, 55], 'app' => [0, 1], 'sat' => [2.4, 3.4], 'fac' => [2.4, 3.4], 'sk' => [28, 45], 'boost' => 3],
      'placegap' => ['n' => 6,  'cgpa' => [8.0, 9.2], 'bk' => [0, 0], 'int' => [72, 90], 'att' => [85, 95], 'login' => [14, 24], 'asg' => [80, 98], 'ev' => [2, 5], 'cl' => [1, 2], 'hk' => [0, 1], 'ce' => [1, 2], 'apt' => [35, 55], 'cod' => [30, 52], 'mock' => [30, 50], 'res' => [40, 60], 'app' => [0, 1], 'sat' => [3.8, 4.6], 'fac' => [3.8, 4.6], 'sk' => [40, 55], 'boost' => 2],
      'attrisk'  => ['n' => 5,  'cgpa' => [6.8, 8.0], 'bk' => [0, 1], 'int' => [55, 75], 'att' => [50, 68], 'login' => [8, 16], 'asg' => [55, 80], 'ev' => [1, 4], 'cl' => [0, 2], 'hk' => [0, 1], 'ce' => [0, 2], 'apt' => [50, 72], 'cod' => [48, 70], 'mock' => [45, 68], 'res' => [50, 72], 'app' => [1, 3], 'sat' => [3.0, 4.0], 'fac' => [3.0, 4.0], 'sk' => [48, 66], 'boost' => 5],
      'skillgap' => ['n' => 5,  'cgpa' => [7.0, 8.2], 'bk' => [0, 0], 'int' => [62, 80], 'att' => [78, 92], 'login' => [10, 20], 'asg' => [65, 90], 'ev' => [1, 4], 'cl' => [0, 2], 'hk' => [0, 1], 'ce' => [0, 2], 'apt' => [55, 75], 'cod' => [50, 70], 'mock' => [50, 70], 'res' => [55, 75], 'app' => [1, 3], 'sat' => [3.4, 4.4], 'fac' => [3.4, 4.4], 'sk' => [25, 42], 'boost' => 2],
    ];

    $R = function ($rng, $int = false) { 
        $v = $rng[0] + mt_rand(0, 1000) / 1000 * ($rng[1] - $rng[0]); 
        return $int ? (int)round($v) : round($v, 1); 
    };

    $stU = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,department,added_by) VALUES (?,?,?,?,?,?)');
    $stS = $pdo->prepare('INSERT INTO students (user_id,teacher_id,student_code,name,email,department,semester,bio,interests,projects) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stSk = $pdo->prepare('INSERT INTO student_skills (student_id,skill,level,category) VALUES (?,?,?,?)');
    
    $i = 0; 
    $roleNames = array_keys($roleIds);
    
    foreach ($arch as $type => $a) {
        for ($n = 0; $n < $a['n']; $n++) {
            $i++; 
            $code = 'ST' . (1000 + $i);
            $assignedTeacher = $teachersList[$i % count($teachersList)];

            if ($i === 1) { // Hero demo student
                $name = 'Arun Kumar'; 
                $email = 'student@suryamithra.local'; 
                $dept = 'CSE'; 
                $role = 'Java Backend Developer';
                $assignedTeacher = 21;
                $d = ['cgpa' => 6.2, 'bk' => 2, 'int' => 48, 'att' => 58, 'login' => 5, 'asg' => 35, 'ev' => 1, 'cl' => 0, 'hk' => 0, 'ce' => 0, 'apt' => 45, 'cod' => 43, 'mock' => 40, 'res' => 50, 'app' => 1, 'sat' => 3.0, 'fac' => 3.2];
                $fixedSk = ['Java' => 55, 'SQL' => 62, 'Spring Boot' => 38, 'REST API' => 42, 'Git' => 65, 'Docker' => 30, 'Communication' => 50, 'Problem Solving' => 45];
                $bio = 'Computer Science undergraduate passionate about backend engineering and system design.';
                $interests = 'Backend systems, Algorithms, Open source';
                $projects = 'Campus Event Management Portal (PHP/MySQL), Task Tracker CLI (Java)';
            } else {
                $name = $first[($i * 7) % count($first)] . ' ' . $last[($i * 5) % count($last)]; 
                $email = strtolower($code) . '@suryamithra.local';
                $dept = $depts[$i % count($depts)]; 
                $role = $roleNames[$i % count($roleNames)]; 
                $fixedSk = [];
                $d = ['cgpa' => $R($a['cgpa']), 'bk' => $R($a['bk'], true), 'int' => $R($a['int']), 'att' => $R($a['att']), 'login' => $R($a['login'], true), 'asg' => $R($a['asg']),
                      'ev' => $R($a['ev'], true), 'cl' => $R($a['cl'], true), 'hk' => $R($a['hk'], true), 'ce' => $R($a['ce'], true), 'apt' => $R($a['apt']), 'cod' => $R($a['cod']),
                      'mock' => $R($a['mock']), 'res' => $R($a['res']), 'app' => $R($a['app'], true), 'sat' => $R($a['sat']), 'fac' => $R($a['fac'])];
                $bio = "Enthusiastic $dept student working on technical projects and career preparation.";
                $interests = "Software development, $dept technologies, Analytics";
                $projects = "Academic Mini Project on $dept Domain";
            }

            $stU->execute([$name, $email, $hash, 'student', $dept, $assignedTeacher]); 
            $uid = (int)$pdo->lastInsertId();

            $stS->execute([$uid, $assignedTeacher, $code, $name, $email, $dept, 3 + ($i % 6), $bio, $interests, $projects]); 
            $sid = (int)$pdo->lastInsertId();

            $pdo->prepare('INSERT INTO academic_records VALUES (?,?,?,?)')->execute([$sid, $d['cgpa'], $d['bk'], $d['int']]);
            $pdo->prepare('INSERT INTO attendance VALUES (?,?)')->execute([$sid, $d['att']]);
            $pdo->prepare('INSERT INTO lms_activity VALUES (?,?,?)')->execute([$sid, $d['login'], $d['asg']]);
            $pdo->prepare('INSERT INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, $d['ev'], $d['cl'], $d['hk'], $d['ce']]);
            $pdo->prepare('INSERT INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, $d['apt'], $d['cod'], $d['mock'], $d['res'], $d['app'], $roleIds[$role]]);
            $pdo->prepare('INSERT INTO feedback VALUES (?,?,?,?)')->execute([$sid, $d['sat'], $d['fac'], 'Regular evaluation note']);

            // Insert Technical Skills
            foreach ($techSkills as $sk) {
                $lv = $i === 1 ? ($fixedSk[$sk] ?? mt_rand(28, 42)) : (max(10, min(98, $R($a['sk'], true) + mt_rand(-10, 10))));
                $stSk->execute([$sid, $sk, $lv, 'technical']);
            }
            // Insert Soft Skills
            foreach ($softSkills as $sk) {
                $lv = $i === 1 ? ($fixedSk[$sk] ?? mt_rand(40, 60)) : (max(15, min(95, $R($a['sk'], true) + mt_rand(-5, 12))));
                $stSk->execute([$sid, $sk, $lv, 'soft']);
            }

            // Notifications for students
            $pdo->prepare('INSERT INTO notifications (user_id,title,body,type) VALUES (?,?,?,?)')
                ->execute([$uid, 'Welcome to SURYAMITHRA', 'Your unified success profile is active. Check "My success profile" to explore your score, risk flags, and placement readiness.', 'info']);
            
            if ($i === 1) {
                $pdo->prepare('INSERT INTO notifications (user_id,title,body,type) VALUES (?,?,?,?)')
                    ->execute([$uid, 'Attendance & Placement Alert', 'Your attendance is 58% and coding score needs practice. Reach out to your teacher Prof. Karthik for a mentorship plan.', 'warn']);
            }
        }
    }

    // Default Interventions
    $pdo->exec("INSERT INTO interventions (student_id,type,mentor,priority,status,outcome,notes,created_by) VALUES
      (1,'Attendance improvement plan','Prof. Karthik Subramanian','High','In Progress',NULL,'Weekly check-in; target 75% by month end', 21),
      (1,'Coding & placement training','Divya Nair','High','Planned',NULL,'Enrol in Java + DSA practice batch', 21),
      (2,'Academic mentoring','Prof. Karthik Subramanian','Medium','Completed','CGPA trend improved after remedial classes','Backlog revision completed', 21),
      (3,'Weekly LMS targets','Prof. Sunita Rao','Medium','In Progress',NULL,'Assignments reviewed every Friday', 22)");

    // Jobs postings
    $pdo->exec("INSERT INTO jobs (company,title,role_id,min_readiness,package_lpa,deadline) VALUES
      ('KPMG India','Technology Consulting Analyst',2,65,8.5,DATE_ADD(CURDATE(), INTERVAL 20 DAY)),
      ('Zoho','Software Developer',1,60,7.0,DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
      ('Freshworks','Full Stack Engineer',3,65,9.0,DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
      ('Infosys','Systems Engineer',1,50,4.5,DATE_ADD(CURDATE(), INTERVAL 10 DAY)),
      ('Amazon','Cloud Support Associate',4,60,6.5,DATE_ADD(CURDATE(), INTERVAL 25 DAY))");
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // If local environment, create DB if needed
            if (defined('DB_HOST') && (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost')) {
                try {
                    $initPdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                    ]);
                    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                } catch (Exception $e) {}
            }

            // Connect directly to database
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Check if users table exists and contains accounts, if empty seed database automatically
            $hasUsers = false;
            try {
                $uCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $hasUsers = ($uCount > 0);
            } catch (Exception $e) {
                $hasUsers = false;
            }

            if (!$hasUsers) {
                auto_seed_database($pdo);
            } else {
                // Ensure all updated schema columns exist unconditionally on existing databases
                $alters = [
                    "ALTER TABLE users ADD COLUMN department VARCHAR(60) NULL",
                    "ALTER TABLE users ADD COLUMN added_by INT NULL",
                    "ALTER TABLE students ADD COLUMN teacher_id INT NULL",
                    "ALTER TABLE students ADD COLUMN class_name VARCHAR(30) NOT NULL DEFAULT 'Class A'",
                    "ALTER TABLE students ADD COLUMN bio TEXT NULL",
                    "ALTER TABLE students ADD COLUMN interests VARCHAR(255) NULL",
                    "ALTER TABLE students ADD COLUMN projects TEXT NULL",
                    "ALTER TABLE student_skills ADD COLUMN category ENUM('technical','soft') DEFAULT 'technical'",
                    "ALTER TABLE feedback ADD COLUMN notes TEXT NULL",
                    "ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL",
                    "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL",
                    "CREATE TABLE IF NOT EXISTS departments (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(60) NOT NULL UNIQUE, code VARCHAR(20) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
                    "CREATE TABLE IF NOT EXISTS class_assignments (id INT PRIMARY KEY AUTO_INCREMENT, department VARCHAR(60) NOT NULL, class_name VARCHAR(30) NOT NULL, teacher_id INT NOT NULL, academic_year VARCHAR(20) DEFAULT '2025-2026', UNIQUE KEY (department, class_name), FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE)",
                    "CREATE TABLE IF NOT EXISTS subject_marks (id INT PRIMARY KEY AUTO_INCREMENT, student_id INT NOT NULL, subject_code VARCHAR(20) NOT NULL, subject_name VARCHAR(100) NOT NULL, staff_id INT NULL, internal_mark DECIMAL(5,2) DEFAULT 0, exam_mark DECIMAL(5,2) DEFAULT 0, total_pct DECIMAL(5,2) DEFAULT 0, grade VARCHAR(5) DEFAULT 'A', semester TINYINT DEFAULT 5, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL)",
                    "CREATE TABLE IF NOT EXISTS period_attendance (id INT PRIMARY KEY AUTO_INCREMENT, student_id INT NOT NULL, subject_code VARCHAR(20) NOT NULL DEFAULT 'GEN', staff_id INT NULL, date DATE NOT NULL, period_number TINYINT DEFAULT 1, status ENUM('Present','Absent','Late','OD') DEFAULT 'Present', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (student_id, date, period_number), FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL)",
                    "ALTER TABLE period_attendance MODIFY COLUMN status ENUM('Present','Absent','Late','OD') DEFAULT 'Present'"
                ];
                foreach ($alters as $q) {
                    try { $pdo->exec($q); } catch (Exception $ex) {}
                }
            }
        } catch (PDOException $ex) {
            die('<div style="font-family:sans-serif;padding:40px;background:#f8d7da;color:#721c24;border-radius:12px;margin:40px auto;max-width:600px">
                <h2>Database Connection Failed</h2>
                <p>Could not connect to MySQL server at <code>' . e(DB_HOST) . '</code>.</p>
                <p>Please make sure your MySQL database credentials and server are running.</p>
                <p><small>Error detail: ' . e($ex->getMessage()) . '</small></p>
            </div>');
        }
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): void { header('Location: ' . url($p)); exit; }
function f1($n): string { return number_format((float)$n, 1); }
function f0($n): string { return number_format((float)$n, 0); }

// ---------- Session / Auth ----------
if (session_status() === PHP_SESSION_NONE) session_start();

function current_user(): ?array {
    if (empty($_SESSION['user']['id'])) return null;
    static $cachedUser = null;
    if ($cachedUser === null) {
        try {
            $st = db()->prepare("SELECT id, name, email, role, department, avatar FROM users WHERE id=?");
            $st->execute([$_SESSION['user']['id']]);
            $fetched = $st->fetch();
            if ($fetched) {
                $cachedUser = $fetched;
                $_SESSION['user'] = $fetched;
            } else {
                $cachedUser = $_SESSION['user'];
            }
        } catch (Exception $e) {
            $cachedUser = $_SESSION['user'];
        }
    }
    return $cachedUser;
}

function upload_avatar(array $file, int $userId): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes, true)) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'png';
    $targetDir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $relPath = 'uploads/avatars/' . $filename;
        db()->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$relPath, $userId]);
        // Reset static session user cache
        if (!empty($_SESSION['user']['id']) && $_SESSION['user']['id'] == $userId) {
            $_SESSION['user']['avatar'] = $relPath;
        }
        return $relPath;
    }
    return null;
}

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('index.php');
    return $u;
}

function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px"><h2>No Access</h2><p>Your role (' . e($u['role']) . ') cannot open this page.</p><a href="' . url('dashboard.php') . '">Back to Dashboard</a></div>');
    }
    return $u;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }

function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); die('Session expired. Go back and try again.');
    }
}

function audit(string $action, string $detail = ''): void {
    $u = current_user();
    db()->prepare('INSERT INTO audit_logs (user_id, action, detail) VALUES (?,?,?)')->execute([$u['id'] ?? null, $action, $detail]);
}

function notify(int $userId, string $title, string $body, string $type = 'info'): void {
    db()->prepare('INSERT INTO notifications (user_id,title,body,type) VALUES (?,?,?,?)')->execute([$userId, $title, $body, $type]);
}

function unread_count(): int {
    $u = current_user(); if (!$u) return 0;
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $st->execute([$u['id']]);
    return (int)$st->fetchColumn();
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ---------- Analytics Engine ----------
const WEIGHTS = ['academic' => 30, 'attendance' => 15, 'lms' => 10, 'engagement' => 10, 'placement' => 20, 'skills' => 10, 'feedback' => 5];
const LABELS  = ['academic' => 'Academic', 'attendance' => 'Attendance', 'lms' => 'LMS activity', 'engagement' => 'Engagement',
                 'placement' => 'Placement readiness', 'skills' => 'Skills', 'feedback' => 'Feedback'];

function clamp($v, $lo = 0, $hi = 100) { return max($lo, min($hi, $v)); }

function placement_readiness(array $s): float {
    return clamp(0.25 * $s['aptitude'] + 0.35 * $s['coding'] + 0.20 * $s['mock_interview'] + 0.10 * $s['resume'] + 0.10 * min(100, $s['applications'] * 20));
}

function score_components(array $s, array $skills): array {
    $academic = clamp(0.7 * $s['cgpa'] * 10 + 0.3 * $s['internal_avg'] - 5 * $s['backlogs']);
    $lms = clamp(0.5 * min(100, $s['login_freq'] / 20 * 100) + 0.5 * $s['assignment_completion']);
    $eng = clamp($s['events'] * 8 + $s['clubs'] * 10 + $s['hackathons'] * 15 + $s['certifications'] * 12);
    $sk  = $skills ? array_sum($skills) / count($skills) : 0;
    $fb  = clamp(($s['satisfaction'] + $s['faculty_rating']) / 10 * 100);
    return [
        'academic' => round($academic, 1), 'attendance' => round(clamp($s['attendance']), 1), 'lms' => round($lms, 1),
        'engagement' => round($eng, 1), 'placement' => round(placement_readiness($s), 1),
        'skills' => round($sk, 1), 'feedback' => round($fb, 1),
    ];
}

function total_score(array $c): float {
    $t = 0; foreach (WEIGHTS as $k => $w) $t += $c[$k] * $w / 100;
    return round($t, 1);
}

function rating(float $sc): string { return $sc >= 80 ? 'Excellent' : ($sc >= 65 ? 'Good' : ($sc >= 45 ? 'Medium' : 'High Risk')); }
function lvl($v, $hi, $med): string { return $v < $hi ? 'HIGH' : ($v < $med ? 'MEDIUM' : 'LOW'); }

function risk_flags(array $s, array $c, float $score): array {
    $acad = lvl($c['academic'], 50, 65);
    if ($s['backlogs'] >= 2) $acad = 'HIGH';
    return [
        'academic' => $acad, 'attendance' => lvl($c['attendance'], 65, 75), 'placement' => lvl($c['placement'], 50, 65),
        'lms' => lvl($c['lms'], 40, 55), 'skills' => lvl($c['skills'], 45, 60), 'overall' => lvl($score, 45, 65),
    ];
}

function explain(array $c): array {
    $out = []; $tot = 0;
    foreach (WEIGHTS as $k => $w) { $r = $w * (100 - $c[$k]) / 100; $out[$k] = $r; $tot += $r; }
    $res = [];
    foreach ($out as $k => $r) {
        $res[] = ['key' => $k, 'label' => LABELS[$k], 'risk_share' => $tot > 0 ? round($r / $tot * 100, 1) : 0,
                  'points' => round($c[$k] * WEIGHTS[$k] / 100, 1), 'max' => WEIGHTS[$k], 'value' => $c[$k]];
    }
    usort($res, fn($a, $b) => $b['risk_share'] <=> $a['risk_share']);
    return $res;
}

function segment_of(array $c, float $score, array $s): array {
    if ($score >= 80 && $c['placement'] >= 65) return ['High Performer', 'green'];
    if ($score < 45) return ['Critical Intervention', 'red'];
    if ($c['academic'] >= 70 && $c['placement'] < 60) return ['Placement Gap', 'amber'];
    if ($c['attendance'] < 70) return ['Attendance Risk', 'amber'];
    if ($c['skills'] < 55) return ['Skill Gap', 'violet'];
    if ($score < 65 && ($c['lms'] >= 60 || $c['engagement'] >= 50)) return ['Potential Improver', 'blue'];
    return ['Balanced', 'slate'];
}

function skill_gaps(array $skills, array $req): array {
    $g = [];
    foreach ($req as $skill => $need) {
        $cur = $skills[$skill] ?? 0; $gap = $need - $cur;
        $g[] = ['skill' => $skill, 'required' => $need, 'current' => $cur, 'gap' => max(0, $gap),
                'priority' => $gap >= 25 ? 'Priority' : ($gap >= 10 ? 'Moderate' : ($gap > 0 ? 'Minor' : 'Met'))];
    }
    usort($g, fn($a, $b) => $b['gap'] <=> $a['gap']);
    return $g;
}

function recommendations(array $s, array $c, array $r): array {
    $o = [];
    if ($r['academic'] !== 'LOW') $o[] = ['Academic mentoring', 'Pair with a faculty mentor' . ($s['backlogs'] ? "; clear {$s['backlogs']} backlog(s) with a revision plan" : '') . '.'];
    if ($r['attendance'] !== 'LOW') $o[] = ['Attendance improvement plan', 'Agree a weekly attendance target; alert guardian if below 65%.'];
    if ($s['coding'] < 60 || $r['placement'] !== 'LOW') $o[] = ['Coding & placement training', 'Enrol in weekly coding practice and mock interview drills.'];
    if ($r['lms'] !== 'LOW') $o[] = ['Weekly LMS targets', 'Set weekly login and assignment completion goals; review every Friday.'];
    if ($r['skills'] !== 'LOW') $o[] = ['Skill-building track', 'Close the top 2 priority skill gaps for target job role.'];
    if ($r['overall'] === 'HIGH') $o[] = ['Faculty counselling', 'One-to-one counselling session within 7 days.'];
    if (!$o) $o[] = ['Stretch opportunities', 'Encourage hackathons, industry certifications and peer mentoring.'];
    return $o;
}

function analyze(array $s, array $skills, array $req): array {
    $c = score_components($s, $skills);
    $score = total_score($c);
    $r = risk_flags($s, $c, $score);
    $seg = segment_of($c, $score, $s);
    return $s + ['c' => $c, 'score' => $score, 'rating' => rating($score), 'risk' => $r, 'explain' => explain($c),
                  'segment' => $seg[0], 'segment_color' => $seg[1], 'skillmap' => $skills, 'gaps' => skill_gaps($skills, $req)];
}

function load_students(?int $onlyId = null, ?int $onlyTeacherId = null, ?array $scopedUser = null): array {
    $pdo = db();
    $sql = 'SELECT s.id, s.user_id, s.teacher_id, s.student_code, s.name, s.email, s.department, s.class_name, s.semester, s.bio, s.interests, s.projects,
        tu.name AS teacher_name,
        a.cgpa, a.backlogs, a.internal_avg, t.overall_pct AS attendance, l.login_freq, l.assignment_completion,
        e.events, e.clubs, e.hackathons, e.certifications, p.aptitude, p.coding, p.mock_interview, p.resume, p.applications,
        p.role_id, jr.name AS role_name, f.satisfaction, f.faculty_rating, f.notes AS feedback_notes
        FROM students s
        LEFT JOIN users tu ON tu.id=s.teacher_id
        JOIN academic_records a ON a.student_id=s.id JOIN attendance t ON t.student_id=s.id
        JOIN lms_activity l ON l.student_id=s.id JOIN engagement e ON e.student_id=s.id
        JOIN placement p ON p.student_id=s.id JOIN feedback f ON f.student_id=s.id
        LEFT JOIN job_roles jr ON jr.id=p.role_id WHERE 1=1';
        
    if ($onlyId) $sql .= ' AND s.id=' . (int)$onlyId;
    if ($onlyTeacherId) $sql .= ' AND s.teacher_id=' . (int)$onlyTeacherId;

    if ($scopedUser) {
        if ($scopedUser['role'] === 'faculty') {
            $tid = (int)$scopedUser['id'];
            $deptEsc = $pdo->quote($scopedUser['department'] ?? '');
            $sql .= " AND (s.department = $deptEsc OR s.teacher_id = $tid OR EXISTS (SELECT 1 FROM subject_marks sm WHERE sm.student_id = s.id AND sm.staff_id = $tid))";
        } elseif ($scopedUser['role'] === 'hod') {
            $deptEsc = $pdo->quote($scopedUser['department'] ?? '');
            $sql .= " AND s.department = $deptEsc";
        }
    }

    $sql .= ' ORDER BY s.student_code';

    $rows = $pdo->query($sql)->fetchAll();
    
    // Load skills
    $sk = [];
    foreach ($pdo->query('SELECT student_id, skill, level FROM student_skills')->fetchAll() as $x) {
        $sk[$x['student_id']][$x['skill']] = (int)$x['level'];
    }
    
    $rq = [];
    foreach ($pdo->query('SELECT role_id, skill, required FROM role_skills')->fetchAll() as $x) {
        $rq[$x['role_id']][$x['skill']] = (int)$x['required'];
    }

    $out = [];
    foreach ($rows as $s) {
        foreach (['cgpa', 'internal_avg', 'attendance', 'assignment_completion', 'aptitude', 'coding', 'mock_interview', 'resume', 'satisfaction', 'faculty_rating'] as $k) {
            $s[$k] = (float)$s[$k];
        }
        foreach (['backlogs', 'login_freq', 'events', 'clubs', 'hackathons', 'certifications', 'applications'] as $k) {
            $s[$k] = (int)$s[$k];
        }
        $out[] = analyze($s, $sk[$s['id']] ?? [], $rq[$s['role_id']] ?? []);
    }
    return $out;
}

function can_teacher_access_student(array $user, int $studentId): bool {
    if (in_array($user['role'], ['admin', 'placement'], true)) return true;

    $pdo = db();
    $st = $pdo->prepare("SELECT s.department, s.teacher_id FROM students s WHERE s.id = ?");
    $st->execute([$studentId]);
    $s = $st->fetch();
    if (!$s) return false;

    if ($user['role'] === 'hod') {
        return !empty($user['department']) && $s['department'] === $user['department'];
    }

    if ($user['role'] === 'faculty') {
        // 1. Same department student
        if (!empty($user['department']) && $s['department'] === $user['department']) {
            return true;
        }
        // 2. Class Tutor / Mentor assigned
        if ((int)$s['teacher_id'] === (int)$user['id']) {
            return true;
        }
        // 3. Handles subject marks for student from other department
        $sm = $pdo->prepare("SELECT 1 FROM subject_marks WHERE student_id = ? AND staff_id = ?");
        $sm->execute([$studentId, (int)$user['id']]);
        if ($sm->fetch()) {
            return true;
        }
        return false;
    }

    return true;
}

function student_by_user(int $userId): ?array {
    $id = db()->prepare('SELECT id FROM students WHERE user_id=?'); 
    $id->execute([$userId]);
    $sid = $id->fetchColumn();
    if (!$sid) return null;
    $r = load_students((int)$sid);
    return $r[0] ?? null;
}

// Staff Hierarchy Loaders
function load_teachers(?string $dept = null): array {
    $sql = "SELECT u.id, u.name, u.email, u.department, u.added_by, h.name AS hod_name,
            (SELECT COUNT(*) FROM students s WHERE s.teacher_id=u.id) AS student_count
            FROM users u
            LEFT JOIN users h ON h.id=u.added_by
            WHERE u.role='faculty'";
    if ($dept) $sql .= " AND u.department=" . db()->quote($dept);
    $sql .= " ORDER BY u.department, u.name";
    return db()->query($sql)->fetchAll();
}

function load_hods(): array {
    $sql = "SELECT u.id, u.name, u.email, u.department,
            (SELECT COUNT(*) FROM users f WHERE f.added_by=u.id AND f.role='faculty') AS teacher_count,
            (SELECT COUNT(*) FROM students s WHERE s.department=u.department) AS student_count
            FROM users u
            WHERE u.role='hod'
            ORDER BY u.department";
    return db()->query($sql)->fetchAll();
}

function seed_default_job_roles(?PDO $pdo = null): void {
    if (!$pdo) $pdo = db();
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM job_roles")->fetchColumn();
        if ($count > 0) return;
    } catch (Exception $e) {
        return;
    }

    $jobRoles = [
      'Java Backend Developer' => ['Java' => 65, 'SQL' => 60, 'Spring Boot' => 65, 'REST API' => 60, 'Git' => 55, 'Problem Solving' => 60],
      'Data Analyst'           => ['SQL' => 65, 'Python' => 60, 'Excel' => 65, 'Statistics' => 60, 'Power BI' => 55, 'Communication' => 65],
      'Full Stack Developer'   => ['JavaScript' => 65, 'React' => 60, 'Node.js' => 60, 'SQL' => 55, 'Git' => 55, 'Teamwork' => 60],
      'Cloud & DevOps Engineer'=> ['Linux' => 65, 'Docker' => 60, 'AWS' => 60, 'Git' => 55, 'Python' => 50, 'Problem Solving' => 65],
    ];

    foreach ($jobRoles as $rn => $req) {
        $pdo->prepare('INSERT IGNORE INTO job_roles (name) VALUES (?)')->execute([$rn]);
        $ch = $pdo->prepare('SELECT id FROM job_roles WHERE name=?');
        $ch->execute([$rn]);
        $rid = (int)$ch->fetchColumn();
        if ($rid > 0) {
            foreach ($req as $sk => $lv) {
                $pdo->prepare('INSERT IGNORE INTO role_skills (role_id, skill, required) VALUES (?,?,?)')->execute([$rid, $sk, $lv]);
            }
        }
    }
}

// Subject Marks, Period Attendance & Class Tutor Helper Functions
function load_subject_marks(int $studentId): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT sm.*, u.name AS staff_name FROM subject_marks sm LEFT JOIN users u ON u.id=sm.staff_id WHERE sm.student_id=? ORDER BY sm.subject_code');
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function can_edit_subject_mark(array $user, int $studentId, ?int $markStaffId = null): bool {
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'student') return false;

    $pdo = db();
    $st = $pdo->prepare("SELECT department FROM students WHERE id=?");
    $st->execute([$studentId]);
    $studentDept = $st->fetchColumn();

    if ($user['role'] === 'hod') {
        return !empty($user['department']) && $user['department'] === $studentDept;
    }

    if ($user['role'] === 'faculty') {
        // Higher hierarchy check: Subject staff can edit their own subject mark, or add a new subject mark
        if ($markStaffId === null || $markStaffId === (int)$user['id'] || $markStaffId == 0) {
            return true;
        }
        // Class tutor cannot edit another subject staff's mark
        return false;
    }
    return false;
}

function can_update_class_attendance(array $user, int $studentId): bool {
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'student') return false;

    $pdo = db();
    $st = $pdo->prepare("SELECT department, teacher_id FROM students WHERE id=?");
    $st->execute([$studentId]);
    $s = $st->fetch();
    if (!$s) return false;

    if ($user['role'] === 'hod') {
        return !empty($user['department']) && $user['department'] === $s['department'];
    }

    if ($user['role'] === 'faculty') {
        // Class Tutor assigned to student or faculty in same department
        return (int)$s['teacher_id'] === (int)$user['id'] || (!empty($user['department']) && $s['department'] === $user['department']);
    }
    return false;
}

function recalculate_student_attendance(int $studentId): float {
    $pdo = db();
    
    $st = $pdo->prepare("SELECT 
        SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as cnt_present,
        SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as cnt_late,
        SUM(CASE WHEN status='OD' THEN 1 ELSE 0 END) as cnt_od,
        SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as cnt_absent,
        COUNT(*) as total_periods
        FROM period_attendance WHERE student_id = ?");
    $st->execute([$studentId]);
    $row = $st->fetch();

    $totalPeriods = (int)($row['total_periods'] ?? 0);
    $cntAbsent    = (int)($row['cnt_absent'] ?? 0);
    $cntPresent   = (int)($row['cnt_present'] ?? 0);
    $cntLate      = (int)($row['cnt_late'] ?? 0);
    $cntOd        = (int)($row['cnt_od'] ?? 0);

    if ($totalPeriods === 0) {
        $overallPct = 100.0;
    } else {
        $attended = $cntPresent + $cntLate + $cntOd; // OD (On Duty) does NOT reduce attendance!
        $overallPct = round(($attended / $totalPeriods) * 100.0, 1);
        $overallPct = max(0.0, min(100.0, $overallPct));
    }

    $pdo->prepare("INSERT INTO attendance (student_id, overall_pct) VALUES (?, ?) ON DUPLICATE KEY UPDATE overall_pct = VALUES(overall_pct)")
        ->execute([$studentId, $overallPct]);

    return $overallPct;
}

function log_period_attendance(int $studentId, string $subjectCode, int $staffId, string $date, int $periodNumber, string $status): void {
    $pdo = db();
    if (!in_array($status, ['Present', 'Absent', 'Late', 'OD'])) {
        $status = 'Present';
    }
    $st = $pdo->prepare('INSERT INTO period_attendance (student_id, subject_code, staff_id, date, period_number, status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE subject_code=VALUES(subject_code), staff_id=VALUES(staff_id), status=VALUES(status)');
    $st->execute([$studentId, $subjectCode ?: 'GEN', $staffId, $date, $periodNumber, $status]);
    recalculate_student_attendance($studentId);
}

function load_class_period_matrix(string $dept, string $className, string $date, ?int $year = 0, ?int $sem = 0): array {
    $pdo = db();
    
    $where = ["s.department = ?", "s.class_name = ?"];
    $params = [$dept, $className];

    if ($year > 0) {
        $semesters = match($year) {
            1 => [1, 2],
            2 => [3, 4],
            3 => [5, 6],
            4 => [7, 8],
            default => []
        };
        if (!empty($semesters)) {
            $where[] = "s.semester IN (" . implode(',', $semesters) . ")";
        }
    } elseif ($sem > 0) {
        $where[] = "s.semester = ?";
        $params[] = $sem;
    }

    $sql = "SELECT s.id, s.name, s.student_code, s.department, s.class_name, s.semester,
                   CEIL(s.semester / 2) AS year,
                   COALESCE(a.overall_pct, 100.0) AS overall_pct
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY s.semester, s.student_code, s.name";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $students = $st->fetchAll();

    if (empty($students)) return [];

    $studentIds = array_column($students, 'id');
    $inClause = implode(',', array_map('intval', $studentIds));

    $paSt = $pdo->query("SELECT pa.*, u.name AS staff_name FROM period_attendance pa LEFT JOIN users u ON u.id = pa.staff_id WHERE pa.date = " . $pdo->quote($date) . " AND pa.student_id IN ($inClause)");
    $paRows = $paSt->fetchAll();

    $periodMap = [];
    foreach ($paRows as $r) {
        $periodMap[$r['student_id']][$r['period_number']] = $r;
    }

    foreach ($students as &$s) {
        $sid = $s['id'];
        $s['periods'] = [];
        for ($p = 1; $p <= 7; $p++) {
            $s['periods'][$p] = $periodMap[$sid][$p] ?? [
                'status' => 'Present',
                'subject_code' => 'GEN',
                'staff_name' => null
            ];
        }
    }
    unset($s);

    return $students;
}

function create_assignment(string $title, string $desc, string $dept, string $className, int $year, int $sem, string $subCode, int $createdBy, string $dueDate): bool {
    $pdo = db();
    $st = $pdo->prepare('INSERT INTO assignments (title, description, department, class_name, year, semester, subject_code, created_by, due_date) VALUES (?,?,?,?,?,?,?,?,?)');
    return $st->execute([$title, $desc, $dept, $className, $year, $sem, $subCode, $createdBy, $dueDate]);
}

function load_assignments(?string $dept = null, ?int $year = 0, ?int $sem = 0): array {
    $pdo = db();
    $where = [];
    $params = [];
    if ($dept) { $where[] = "a.department = ?"; $params[] = $dept; }
    if ($year > 0) { $where[] = "a.year = ?"; $params[] = $year; }
    if ($sem > 0) { $where[] = "a.semester = ?"; $params[] = $sem; }

    $sql = "SELECT a.*, u.name AS creator_name FROM assignments a LEFT JOIN users u ON u.id = a.created_by";
    if ($where) { $sql .= " WHERE " . implode(" AND ", $where); }
    $sql .= " ORDER BY a.due_date ASC, a.created_at DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function save_class_period_matrix(array $matrix, string $date, int $staffId): int {
    $pdo = db();
    $st = $pdo->prepare('INSERT INTO period_attendance (student_id, subject_code, staff_id, date, period_number, status) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE subject_code=VALUES(subject_code), staff_id=VALUES(staff_id), status=VALUES(status)');
    
    $affectedStudents = [];
    $count = 0;

    foreach ($matrix as $studentId => $periods) {
        $studentId = (int)$studentId;
        if ($studentId <= 0) continue;

        foreach ($periods as $periodNum => $pData) {
            $periodNum = (int)$periodNum;
            if ($periodNum < 1 || $periodNum > 7) continue;

            $status = is_array($pData) ? ($pData['status'] ?? 'Present') : (string)$pData;
            $subCode = is_array($pData) ? ($pData['subject_code'] ?? 'GEN') : 'GEN';

            if (!in_array($status, ['Present', 'Absent', 'Late', 'OD'])) {
                $status = 'Present';
            }

            $st->execute([$studentId, $subCode ?: 'GEN', $staffId, $date, $periodNum, $status]);
            $count++;
            $affectedStudents[$studentId] = true;
        }
    }

    foreach (array_keys($affectedStudents) as $sid) {
        recalculate_student_attendance($sid);
    }

    return $count;
}

function load_period_attendance(int $studentId): array {
    $pdo = db();
    $st = $pdo->prepare('SELECT pa.*, u.name AS staff_name FROM period_attendance pa LEFT JOIN users u ON u.id=pa.staff_id WHERE pa.student_id=? ORDER BY pa.date DESC, pa.period_number DESC');
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function save_subject_mark(int $studentId, string $subjectCode, string $subjectName, float $internalMark, float $examMark, ?int $staffId = null, int $semester = 5): void {
    $pdo = db();
    // Calculate 40% internal + 60% exam total percentage
    $totalPct = min(100.0, max(0.0, ($internalMark * 0.4) + ($examMark * 0.6)));
    $grade = $totalPct >= 90 ? 'A+' : ($totalPct >= 80 ? 'A' : ($totalPct >= 70 ? 'B' : ($totalPct >= 60 ? 'C' : ($totalPct >= 50 ? 'D' : 'F'))));

    // Check if mark row exists
    $ch = $pdo->prepare('SELECT id FROM subject_marks WHERE student_id=? AND subject_code=?');
    $ch->execute([$studentId, $subjectCode]);
    $existingId = $ch->fetchColumn();

    if ($existingId) {
        $pdo->prepare('UPDATE subject_marks SET subject_name=?, staff_id=?, internal_mark=?, exam_mark=?, total_pct=?, grade=?, semester=? WHERE id=?')
            ->execute([$subjectName, $staffId, $internalMark, $examMark, $totalPct, $grade, $semester, $existingId]);
    } else {
        $pdo->prepare('INSERT INTO subject_marks (student_id, subject_code, subject_name, staff_id, internal_mark, exam_mark, total_pct, grade, semester) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$studentId, $subjectCode, $subjectName, $staffId, $internalMark, $examMark, $totalPct, $grade, $semester]);
    }

    // Recalculate student internal_avg and CGPA based on subject totals
    $avgPct = (float)$pdo->query("SELECT AVG(total_pct) FROM subject_marks WHERE student_id=" . (int)$studentId)->fetchColumn();
    $newCgpa = min(10.0, max(0.0, round($avgPct / 10, 2)));

    $pdo->prepare('UPDATE academic_records SET internal_avg=?, cgpa=? WHERE student_id=?')->execute([round($avgPct, 1), $newCgpa, $studentId]);
}

function assign_class_teacher(string $dept, string $className, int $teacherId): void {
    $pdo = db();
    $pdo->prepare('INSERT INTO class_assignments (department, class_name, teacher_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE teacher_id=?')
        ->execute([$dept, $className, $teacherId, $teacherId]);
    
    // Assign teacher_id to all students in that department & class_name
    $pdo->prepare('UPDATE students SET teacher_id=? WHERE department=? AND class_name=?')
        ->execute([$teacherId, $dept, $className]);
}

function load_class_assignments(?string $dept = null): array {
    $sql = "SELECT ca.*, u.name AS teacher_name, u.email AS teacher_email,
            (SELECT COUNT(*) FROM students s WHERE s.department=ca.department AND s.class_name=ca.class_name) AS student_count
            FROM class_assignments ca
            LEFT JOIN users u ON u.id=ca.teacher_id";
    if ($dept) $sql .= " WHERE ca.department=" . db()->quote($dept);
    $sql .= " ORDER BY ca.department, ca.class_name";
    return db()->query($sql)->fetchAll();
}

function load_departments(): array {
    $pdo = db();
    try {
        $defaults = [
            ['name' => 'Aeronautical Engineering', 'code' => 'AERO'],
            ['name' => 'Agricultural Engineering', 'code' => 'AGRI'],
            ['name' => 'Artificial Intelligence and Data Science', 'code' => 'AIDS'],
            ['name' => 'Biomedical Engineering', 'code' => 'BME'],
            ['name' => 'Biotechnology', 'code' => 'BT'],
            ['name' => 'Chemical Engineering', 'code' => 'CHEM'],
            ['name' => 'Civil Engineering', 'code' => 'CIVIL'],
            ['name' => 'Computer Science and Engineering', 'code' => 'CSE'],
            ['name' => 'CSE - Artificial Intelligence and Machine Learning', 'code' => 'AIML'],
            ['name' => 'CSE - Internet of Things', 'code' => 'IOT'],
            ['name' => 'Cyber Security', 'code' => 'CYBER'],
            ['name' => 'Electrical and Electronics Engineering', 'code' => 'EEE'],
            ['name' => 'Electronics and Communication Engineering', 'code' => 'ECE'],
            ['name' => 'Food Technology', 'code' => 'FT'],
            ['name' => 'Information Technology', 'code' => 'IT'],
            ['name' => 'Mechanical Engineering', 'code' => 'MECH'],
            ['name' => 'Mechatronics Engineering', 'code' => 'MCT'],
            ['name' => 'Pharmaceutical Technology', 'code' => 'PT'],
            ['name' => 'Robotics and Automation', 'code' => 'RA']
        ];
        foreach ($defaults as $d) {
            $pdo->prepare("INSERT IGNORE INTO departments (name, code) VALUES (?, ?)")->execute([$d['name'], $d['code']]);
        }
        $depts = $pdo->query("SELECT code FROM departments ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
        return !empty($depts) ? $depts : ['AERO', 'AGRI', 'AIDS', 'AIML', 'BME', 'BT', 'CHEM', 'CIVIL', 'CSE', 'CYBER', 'ECE', 'EEE', 'FT', 'IOT', 'IT', 'MCT', 'MECH', 'PT', 'RA'];
    } catch (Exception $ex) {
        return ['AERO', 'AGRI', 'AIDS', 'AIML', 'BME', 'BT', 'CHEM', 'CIVIL', 'CSE', 'CYBER', 'ECE', 'EEE', 'FT', 'IOT', 'IT', 'MCT', 'MECH', 'PT', 'RA'];
    }
}

function add_department(string $name, string $code = ''): bool {
    $pdo = db();
    $name = trim($name);
    $code = strtoupper(trim($code ?: preg_replace('/[^A-Za-z0-9]/', '', $name)));
    if (!$name) return false;
    $st = $pdo->prepare("INSERT INTO departments (name, code) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
    return $st->execute([$name, $code]);
}

function risk_pill(string $l): string {
    $cls = ['HIGH' => 'red', 'MEDIUM' => 'amber', 'LOW' => 'green'][$l] ?? 'slate';
    return '<span class="pill ' . $cls . '">' . e(ucfirst(strtolower($l))) . '</span>';
}

function seg_pill(array $s): string { return '<span class="pill ' . e($s['segment_color']) . '">' . e($s['segment']) . '</span>'; }
function score_color(float $sc): string { return $sc >= 65 ? 'var(--green)' : ($sc >= 45 ? 'var(--amber)' : 'var(--red)'); }
function avg(array $a): float { return $a ? array_sum($a) / count($a) : 0; }
