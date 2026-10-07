# SURYAMITHRA: Student Success Score & Decision Intelligence Framework

## 1. Overview
The **SURYAMITHRA Student Success Score** is a composite metric (0–100 scale) that unifies multi-source data across academic performance, attendance, LMS engagement, placement readiness, skill assessments (technical and soft skills), and feedback ratings.

---

## 2. Indicator Breakdown & Formula Weights

| Indicator | Weight | Calculation Logic (0–100 Scale) |
|---|---|---|
| **Academic Performance** | **30%** | `70% × (CGPA × 10) + 30% × Internal Marks - 5 points per active Backlog` |
| **Placement Readiness** | **20%** | `25% Aptitude + 35% Coding + 20% Mock Interview + 10% Resume + 10% Applications (5 apps = 100)` |
| **Attendance** | **15%** | `Overall class attendance percentage` |
| **LMS Activity** | **10%** | `50% Login Frequency (20 logins/mo = 100) + 50% Assignment Completion %` |
| **Skills Assessment** | **10%** | `Average of student's Technical & Soft-skill proficiency levels (0–100)` |
| **Engagement** | **10%** | `Events × 8 + Clubs × 10 + Hackathons × 15 + Certifications × 12 (capped at 100)` |
| **Feedback** | **5%** | `Average of Student Satisfaction (1–5) and Faculty Rating (1–5) scaled to 100` |

---

## 3. Risk Flag Logic & Rating Thresholds

- **Score Ratings:**
  - **80 – 100:** Excellent
  - **65 – 79:** Good
  - **45 – 64:** Medium Risk
  - **< 45:** High Risk / Critical Intervention

- **Risk Level Criteria (Per Category):**
  - **Academic Risk:** High if CGPA < 5.0 or Backlogs ≥ 2; Medium if CGPA < 6.5.
  - **Attendance Risk:** High if < 65%; Medium if < 75%.
  - **Placement Risk:** High if Placement Readiness < 50%; Medium if < 65%.
  - **LMS Risk:** High if LMS score < 40; Medium if < 55.
  - **Skill Risk:** High if Skill score < 45; Medium if < 60.

---

## 4. Explainable Score Breakdown (Driver Analysis)

SURYAMITHRA avoids black-box scoring by calculating each indicator's **Risk Share**:
$$\text{Risk Share}_i = \frac{W_i \times (100 - V_i)}{\sum_{k} W_k \times (100 - V_k)} \times 100\%$$
The indicator with the largest percentage risk share is highlighted as the **Primary Risk Driver** for targeted intervention.

---

## 5. Student Segmentation Model

SURYAMITHRA automatically groups students into 7 actionable segments:
1. **High Performer:** Score ≥ 80 and Placement Readiness ≥ 65.
2. **Placement Gap:** High academic performance (Academic ≥ 70) but Placement Readiness < 60 (Low Aptitude/Coding/Mock scores).
3. **Critical Intervention:** Overall Score < 45.
4. **Attendance Risk:** Attendance < 70%.
5. **Skill Gap:** Average skill assessment < 55.
6. **Potential Improver:** Mid score (< 65) with high LMS activity or engagement.
7. **Balanced:** Steady performer with no major risk triggers.

---

## 6. Staff Hierarchy & Operational Flow

- **HOD (Head of Department):** Manages departmental staff, monitors department success averages, and adds Teachers/Faculty.
- **Teacher / Faculty:** Added by HOD; manages assigned students, adds new students, updates academic CGPA/GPA, attendance, placement scores, and faculty feedback ratings.
- **Student:** Logs in to view personal Success Score, Explainable breakdown, and updates profile details (skills known, soft skills, projects, bio, and satisfaction feedback).
