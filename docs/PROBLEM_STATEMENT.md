# Problem Statement & Solution

**KPMG in India · Smart Campus Analytics: Predict, Optimize & Improve Student Success**

Colleges generate large amounts of data through attendance, internal assessments, examinations, LMS activity, placement participation, extracurricular activities and student feedback. This data sits in disconnected systems and is rarely used before a student fails or misses a placement.

**Challenge:** Build an analytics and decision intelligence platform that identifies students who need intervention and helps HODs, faculty, and administrators take data-driven action. It must go beyond a basic dashboard and turn student data into explainable decision intelligence.

---

## Solution: SURYAMITHRA

**SURYAMITHRA** is an end-to-end Student Success & Placement Decision Intelligence Platform designed with zero setup friction (auto database creation & initial seeding out-of-the-box, no `install.php` wizard needed).

| Challenge Requirement | Implementation in SURYAMITHRA |
|---|---|
| **Data Integration** | Integrates 7 data streams: Academic, Attendance, LMS, Engagement, Placement (Aptitude, Coding, Mock Interviews), Skills (Technical & Soft), and Feedback. |
| **Staff Hierarchy** | HOD adds Teachers/Faculty; Teachers add Students; Teachers update GPA, CGPA, Attendance, Aptitude, Coding, Mock scores; Students update personal profile & skills known. |
| **Student Success Score** | Transparent 0–100 composite score (see `SCORE_NOTE.md`). |
| **At-Risk Identification** | Automated Risk Center flagging academic, attendance, placement, LMS, skills, and overall risk levels. |
| **Interactive Dashboard** | Real-time score distributions, risk split, department comparison bar charts, indicator radar, segments, and early-warning lists. |
| **Bonus: Segmentation** | 7 meaningful student segments, including "Placement Gap" (High academic performance but low placement readiness). |
| **Bonus: Explainable Score** | Dynamic waterfall / percentage driver analysis showing exact indicators contributing to score and risk. |
| **Interactive Features** | Staff Hierarchy page, What-If simulator, Skill Gap heatmap, Job matching, Notifications, and AI Copilot. |

---

## Role-Based Access

- **HOD (Head of Department):** Manages faculty/teachers, views department hierarchy, department averages, and monitors student success.
- **Teacher / Faculty:** Added by HOD; adds students, updates GPA/CGPA/placement marks, monitors student risk, and manages interventions.
- **Student:** Accesses personal success profile, updates profile bio/interests/projects/skills known, views explainable score breakdown, skill gaps, and what-if simulator.
- **Placement Officer:** Manages job roles, placement readiness, skill gap alerts, and campus placement drives.
- **Admin:** System-wide management, data import, user activation, and audit logs.
