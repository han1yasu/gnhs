# GUINAYANG NATIONAL HIGH SCHOOL
## Guidance & Counseling Office
### User Acceptance Testing (UAT) Form — Teacher / Class Adviser Portal

---

| **System Name** | GNHS Guidance Case Management System | **Document Code** | UAT-FORM-TCH-02 |
| :--- | :--- | :--- | :--- |
| **Target User Role** | Teacher / Subject Teacher / Class Adviser | **System Version** | v1.0 |
| **Date of Testing** | `YYYY-MM-DD` | **Testing Environment** | Web Browser (Chrome / Edge / Firefox) |

---

### 1. Tester Information

| Field | Tester Information |
| :--- | :--- |
| **Teacher Full Name** | __________________________________________________ |
| **Employee ID / DepEd ID** | __________________________________________________ |
| **Department / Grade Level** | `[ ]` Junior HS (Grades 7–10) &nbsp;&nbsp; Department: _________________ |
| **Assigned Advisory Section** | Grade: ________ Section: ___________________________ (or N/A) |
| **Official Email Address** | __________________________________________________ |
| **Device & Browser Used** | Device: ___________________ Browser: ____________________ |

---

### 2. Testing Instructions
1. Follow each test case sequentially under the **Test Scenarios** section.
2. In the **Actual Result** column, document the system's behavior and performance.
3. Mark the **Status** column as:
   - **PASS** (`P`): Feature operated exactly according to specifications.
   - **FAIL** (`F`): Error occurred, data did not save, or functionality deviated.
   - **CONDITIONAL PASS** (`C`): Feature works; minor visual, text, or labeling update needed.
   - **BLOCKED** (`B`): Could not proceed due to an upstream blocking defect.
4. Complete the **Usability Evaluation** and **Defect Log** if any bugs are discovered.
5. Provide your overall recommendation and sign the **Acceptance Sign-Off** at the end.

---

### 3. Functional Test Cases

#### Module 1: Teacher Authentication & Profile Setup
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TCH-01** | **Teacher Login** | Enter registered faculty credentials. Click *Sign In*. | Successfully logs in and directs to the Teacher Dashboard. | | `[ ] P`<br>`[ ] F` |
| **TCH-02** | **Advisory Profile Verification** | Check assigned grade level and advisory section in profile (`teacher-setup.php`). | Accurate advisory assignment is displayed; options to update security settings work. | | `[ ] P`<br>`[ ] F` |
| **TCH-03** | **Security & 2FA Setup** | Configure security questions and verify 2FA TOTP authentication. | Security configuration saves securely with confirmation message. | | `[ ] P`<br>`[ ] F` |

#### Module 2: Teacher Dashboard Overview
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TCH-04** | **Summary KPI Cards** | View summary cards: *Total Referrals*, *Pending Counsel*, *Ongoing Cases*, *Advisory Count*. | Metric counts accurately match submitted database records. | | `[ ] P`<br>`[ ] F` |
| **TCH-05** | **Recent Updates Feed** | Check recent notifications and referral status updates on the dashboard. | Counselor updates and scheduled interventions are summarized chronologically. | | `[ ] P`<br>`[ ] F` |
| **TCH-06** | **Quick Actions** | Click *Submit New Referral* and *View Advisory* buttons from dashboard. | Navigation links instantly redirect to respective pages without error. | | `[ ] P`<br>`[ ] F` |

#### Module 3: Advisory Class Management (`teacher-advisory.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TCH-07** | **Advisory Roster Display** | Navigate to *My Advisory Class*. View student roster table. | Full list of enrolled students in the adviser's section displays with LRN, names, and status. | | `[ ] P`<br>`[ ] F` |
| **TCH-08** | **Student Search & Filter** | Search for a specific student by first name, last name, or LRN. | Table filters dynamically in real-time, showing the matching student record. | | `[ ] P`<br>`[ ] F` |
| **TCH-09** | **Quick Refer Action** | Click the *Refer to Guidance* action button beside a specific student in the roster. | Opens referral form with the student's name, grade, and section pre-filled. | | `[ ] P`<br>`[ ] F` |
| **TCH-10** | **Confidentiality Check** | Check student records where student reported an adviser-sensitive concern. | Sensitive reports marked with adviser protection are strictly hidden from the adviser view. | | `[ ] P`<br>`[ ] F` |

#### Module 4: Student Referral Submission (`teacher-referral.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TCH-11** | **Grade & Dynamic Section** | Select Grade (e.g. *Grade 9*). Observe Section dropdown options. | Section dropdown automatically updates with corresponding sections for that grade. | | `[ ] P`<br>`[ ] F` |
| **TCH-12** | **Concern Categorization** | Select concern type (*Bullying*, *Academic*, *Behavioral*, *Emotional*, *Family*, *Attendance*, *Other*). | Concern is categorized properly; selecting *Other* reveals text input for specification. | | `[ ] P`<br>`[ ] F` |
| **TCH-13** | **Urgency Level Selection** | Choose urgency: *Low*, *Moderate*, or *Urgent*. | Urgency badge indicator updates; urgent selection displays warning alert for immediate triage. | | `[ ] P`<br>`[ ] F` |
| **TCH-14** | **Observations Validation** | Enter detailed behavioral observations. Check character and word counters. | Enforces minimum 30 characters and max 400 words; submit button validates properly. | | `[ ] P`<br>`[ ] F` |
| **TCH-15** | **Evidence Attachment (Optional)**| Attach incident documentation, attendance logs, or behavioral slips (PDF/Images). | Attachment uploads and previews with valid file type check. | | `[ ] P`<br>`[ ] F` |
| **TCH-16** | **Submit Referral** | Click *Submit Referral*. | Success notification displays; unique Referral Number (e.g. `REF-2026-XXXX`) is issued. | | `[ ] P`<br>`[ ] F` |

#### Module 5: Referral Tracking & Counselor Collaboration
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TCH-17** | **Track Referrals Table** | Navigate to *My Referrals* (`teacher-referrals.php`). | Submitted referrals appear with ref number, date, urgency tag, and current status. | | `[ ] P`<br>`[ ] F` |
| **TCH-18** | **Status Lifecycle Tracking** | Observe status progression (*Pending* → *Under Review* → *Ongoing* → *Resolved*). | Badges change color and status label updates when counselor acts on the referral. | | `[ ] P`<br>`[ ] F` |
| **TCH-19** | **Referral Search & Filtering** | Filter by urgency (*Urgent*), concern type (*Attendance*), or status. | Table correctly filters results without page breakdown. | | `[ ] P`<br>`[ ] F` |
| **TCH-20** | **Counselor Collaboration Chat** | Open *Referral Chat* (`teacher-referrals-chat.php`) linked to a referral. | Dedicated coordination thread opens with the guidance counselor; messages send instantly. | | `[ ] P`<br>`[ ] F` |
| **TCH-21** | **Referral Archive** | Navigate to *Referrals Archive* (`teacher-referrals-archive.php`). | Resolved and closed referrals are archived properly for historical faculty record-keeping. | | `[ ] P`<br>`[ ] F` |

---

### 4. Usability & Workflow Evaluation (Teacher Perspective)
*Please rate each statement on a scale of 1 to 5:*  
**(1 = Strongly Disagree | 2 = Disagree | 3 = Neutral | 4 = Agree | 5 = Strongly Agree)**

| # | Evaluation Statement | Rating (1 - 5) | Comments / Feedback |
| :-: | :--- | :-: | :--- |
| **1** | The referral submission process is faster and more convenient than manual paper forms. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **2** | Dynamic section loading and student selection reduce data entry mistakes. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **3** | The ability to track whether a student has been called for counseling is transparent and helpful. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **4** | The dedicated referral chat provides clear and confidential communication with counselors. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **5** | The advisory roster management simplifies monitoring students needing behavioral interventions. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **6** | The system user interface is intuitive and requires minimal technical training. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **7** | Page loading speed and reliability during submission met expectations. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |

---

### 5. Defect / Issue Log (To be filled if bugs are encountered)

| # | Module / Page | Description of Problem | Steps to Reproduce | Severity (Low/Med/High) |
| :-: | :--- | :--- | :--- | :---: |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

---

### 6. Teacher General Feedback & Suggestions
*What features are most helpful for classroom advisers, and what tools should be added?*  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  

---

### 7. Acceptance Sign-Off

**Overall Evaluation Result:**  
- `[ ]` **ACCEPTED**: System meets faculty requirements and is endorsed for operational deployment.  
- `[ ]` **CONDITIONALLY ACCEPTED**: System is accepted subject to addressing minor issues noted.  
- `[ ]` **REJECTED**: Significant defects hinder teacher workflows; requires remediation before rollout.  

<br>

| **Teacher / Adviser Signature** | **Guidance Department Head** |
| :--- | :--- |
| Signature: ________________________________ | Signature: ________________________________ |
| Printed Name: _____________________________ | Printed Name: _____________________________ |
| Designation: ______________________________ | Designation: ______________________________ |
| Date: _____________________________________ | Date: _____________________________________ |
