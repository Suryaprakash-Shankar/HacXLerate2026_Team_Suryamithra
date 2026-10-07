# HacXLerate2026_Team_Suryamithra

# SURYAMITHRA: Student Success & Placement Decision Intelligence Platform (PHP + MySQL)

Built for the KPMG in India Smart Campus Analytics challenge. See `docs/PROBLEM_STATEMENT.md` and `docs/SCORE_NOTE.md`.

## Quick Start (Zero Config Auto-Setup)
1. Ensure XAMPP (PHP 7.4+ & MySQL) is running.
2. Place this folder in `c:\xampp\htdocs\campusiq` (or any subfolder in your web root).
3. Open **http://localhost/campusiq/** in your browser.
   - Database tables and initial seed data are **automatically created on first launch** — no `install.php` wizard is needed!

---

## Demo Logins (Password: `password`)

| Role | Email | Features |
|---|---|---|
| **HOD (Head of Department)** | `hod@suryamithra.local` | Department analytics, add teachers, view hierarchy |
| **Teacher / Faculty** | `faculty@suryamithra.local` | Add students, update CGPA/GPA, attendance, placement & feedback |
| **Student** | `student@suryamithra.local` | View success score, update profile (skills, projects), what-if simulator |
| **Placement Officer** | `placement@suryamithra.local` | Placement readiness, job postings, skill gap notifications |
| **Administrator** | `admin@suryamithra.local` | System configuration, user control, audit logs |

---

## Key Highlights & Features

1. **Staff Hierarchy (`hierarchy.php`):** HOD adds Teachers → Teachers add & manage Students → Teachers update GPA, CGPA, Aptitude, Coding, Mock interview scores.
2. **Student Profile Update (`student.php`):** Students can update their profile ("what they know"), bio, projects, technical skills, and soft skills.
3. **Explainable Success Score:** 0–100 composite score with dynamic risk driver breakdown (Academic 30%, Placement 20%, Attendance 15%, LMS 10%, Skills 10%, Engagement 10%, Feedback 5%).
4. **Student Segmentation:** 7 meaningful groups, including *Placement Gap* (High Academic but Low Placement readiness).
5. **Interactive Tools:** What-If simulator, Skill Gap heatmap, Job matching, Notifications, and AI Copilot.
