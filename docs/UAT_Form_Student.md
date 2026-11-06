+# GUINAYANG NATIONAL HIGH SCHOOL
## Guidance & Counseling Office
### User Acceptance Testing (UAT) Form — Student Portal

---

| **System Name** | GNHS Guidance Case Management System | **Document Code** | UAT-FORM-STU-01 |
| :--- | :--- | :--- | :--- |
| **Target User Role** | Student / Peer Reporter | **System Version** | v1.0 |
| **Date of Testing** | `YYYY-MM-DD` | **Testing Environment** | Web Browser (Chrome / Edge / Mobile) |

---

### 1. Tester Information

| Field | Tester Information |
| :--- | :--- |
| **Student Full Name** | __________________________________________________ |
| **Student LRN / ID** | __________________________________________________ |
| **Grade Level & Section** | Grade: ________ Section: ___________________________ |
| **School Email / Phone** | __________________________________________________ |
| **Device Used** | `[ ]` Desktop / PC &nbsp;&nbsp; `[ ]` Laptop &nbsp;&nbsp; `[ ]` Smartphone / Tablet |
| **Operating System & Browser** | OS: ________________ Browser: _______________________ |

---

### 2. Testing Instructions
1. Follow each test case sequentially under the **Test Scenarios** section.
2. In the **Actual Result** column, describe what actually happened on your screen.
3. Mark the **Status** column as:
   - **PASS** (`P`): Feature worked smoothly as described.
   - **FAIL** (`F`): Feature generated an error, didn't save, or did not behave as expected.
   - **CONDITIONAL PASS** (`C`): Feature worked, but with minor cosmetic or text issues.
   - **BLOCKED** (`B`): Cannot test because another step prevented it.
4. Complete the **Usability Evaluation** and **Defect Log** if any issues were found.
5. Provide your overall recommendation and sign the **Acceptance Sign-Off** at the end.

---

### 3. Functional Test Cases

#### Module 1: Account Access & Security Setup
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **STU-00** | **Student Registration & Strict 12-Digit LRN** | On login modal, click *Create Account*. Enter student details and test LRN input with letters, <12 digits, and exactly 12 digits. | Input field strictly restricts input to numbers only with 12 max length. Submitting <12 digits displays validation error. Exactly 12 digits succeeds. | | `[ ] P`<br>`[ ] F` |
| **STU-01** | **Student Login** | Enter registered student email/LRN and password. Click *Sign In*. | Successfully logs in and redirects to TOTP verification or Student Dashboard. | | `[ ] P`<br>`[ ] F` |
| **STU-02** | **Security Questions Setup** | Navigate to security settings. Select 2 security questions and provide answers. Save. | Security questions are saved successfully; confirmation alert appears. | | `[ ] P`<br>`[ ] F` |
| **STU-03** | **Two-Factor Auth (2FA / TOTP)** | Scan QR code using Authenticator app (e.g., Google Authenticator), enter 6-digit TOTP code. | TOTP is verified; 2FA is successfully enabled for next logins. | | `[ ] P`<br>`[ ] F` |
| **STU-04** | **Password Reset Request (Routed to Guidance Admin)** | From login page, click *Forgot Password*. Submit request with security question verification or route to Guidance Admin. | Request is queued for Guidance Administrator review; student can check request update. | | `[ ] P`<br>`[ ] F` |

#### Module 2: Student Dashboard Overview
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **STU-05** | **Dashboard Metrics** | View the student dashboard cards (Pending Concerns, Upcoming Sessions, Active Cases). | Accurate count of submitted concerns and active sessions is displayed. | | `[ ] P`<br>`[ ] F` |
| **STU-06** | **Theme Switcher** | Toggle Dark Mode / Light Mode and Maroon color theme. | Colors update immediately across navbar, cards, and text without reload errors. | | `[ ] P`<br>`[ ] F` |
| **STU-07** | **Guidance Announcements** | Check the announcements/bulletin card on the dashboard. | Latest guidance notices and consultation schedules are visible and readable. | | `[ ] P`<br>`[ ] F` |

#### Module 3: Submit a Concern / Counseling Request (`student-submit.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **STU-08** | **Personal Concern Selection** | Click *Submit a Concern*. Select *"Personal Concern"*. | Form displays personal fields; personal identity is retained for the counselor. | | `[ ] P`<br>`[ ] F` |
| **STU-09** | **Peer / Other Student Report** | Select *"Another Student"*. Check fields for Affected Student's Name and Grade/Section. | Fields for affected student appear dynamically; clear instructions are shown. | | `[ ] P`<br>`[ ] F` |
| **STU-10** | **Concern Type Selection** | Open the dropdown and select (e.g., *Bullying*, *Academic Stress*, *Emotional/Mental Health*, *Family*, *Peer Conflict*). | Selected type updates; selecting *Other* displays a custom text input field. | | `[ ] P`<br>`[ ] F` |
| **STU-11** | **Adviser Protection Mode** | Check *"This concern involves my Class Adviser or Subject Teacher"*. | Confidentiality banner appears informing that the adviser is restricted from seeing this record. | | `[ ] P`<br>`[ ] F` |
| **STU-12** | **Bullying Tip Banner** | Select *Bullying* as concern type. | Guidance tip banner appears advising that evidence/witnesses are optional but helpful. | | `[ ] P`<br>`[ ] F` |
| **STU-13** | **Date Validation** | Select today's date or past date. Try selecting a future date. | Past/today dates are accepted; future dates are blocked by calendar validation. | | `[ ] P`<br>`[ ] F` |
| **STU-14** | **Description Word/Char Counter** | Type description. Observe character counter (min 30 chars) and word counter (max 400 words). | Live counter increments; form disallows submission if under 30 characters. | | `[ ] P`<br>`[ ] F` |
| **STU-15** | **Optional Witnesses & Evidence** | Add witness names (optional) and attach an image or screenshot (JPEG/PNG). | File name previews correctly; upload file size/format restrictions are enforced. | | `[ ] P`<br>`[ ] F` |
| **STU-16** | **Submission Confirmation** | Click *Submit Concern*. | Success message appears; user receives reference case tracking number; redirected to Cases list. | | `[ ] P`<br>`[ ] F` |

#### Module 4: Counseling Sessions & Case Tracking
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **STU-17** | **My Cases Tracking** | Navigate to *My Cases*. Check status of submitted concerns. | Case number, date, concern type, and current status (*Pending*, *Under Review*, *Resolved*) display. | | `[ ] P`<br>`[ ] F` |
| **STU-18** | **Scheduled Sessions** | Open *Counseling Sessions*. View upcoming appointment schedule. | Date, time, counselor name, and meeting location/room or mode display clearly. | | `[ ] P`<br>`[ ] F` |
| **STU-19** | **Past Sessions History** | Click *Sessions Archive* or filter by completed sessions. | Past completed counseling appointments are listed with date and completion status. | | `[ ] P`<br>`[ ] F` |

#### Module 5: Guidance Consultation Chat
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **STU-20** | **Send Message to Counselor** | Open *Guidance Chat*. Type a message to counselor and press Send. | Message appears immediately with timestamp; counselor receives notification. | | `[ ] P`<br>`[ ] F` |
| **STU-21** | **Receive Counselor Reply** | Wait for or refresh message from Guidance Counselor. | Counselor response displays in chat thread; message read/sent status updates. | | `[ ] P`<br>`[ ] F` |
| **STU-22** | **Chat Archive** | View *Chat Archive*. | Completed/closed conversations are archived safely and accessible for reference. | | `[ ] P`<br>`[ ] F` |

---

### 4. Usability & User Experience Evaluation (Student Perspective)
*Please rate each statement on a scale of 1 to 5:*  
**(1 = Strongly Disagree | 2 = Disagree | 3 = Neutral | 4 = Agree | 5 = Strongly Agree)**

| # | Evaluation Statement | Rating (1 - 5) | Comments / Feedback |
| :-: | :--- | :-: | :--- |
| **1** | The portal is easy to navigate, and buttons/menus are clear. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **2** | I felt safe and confident that my concerns are kept private and confidential. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **3** | The "Submit a Concern" form is easy to understand and fill out. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **4** | The character and word counters helped me compose my description properly. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **5** | The ability to protect my report from my adviser gave me peace of mind. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **6** | The colors, text font, and dark mode option look modern and comfortable to read. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **7** | The system responded quickly without lag or confusing errors. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |

---

### 5. Defect / Issue Log (To be filled if bugs are encountered)

| # | Module / Page | Description of Problem | Steps to Reproduce | Severity (Low/Med/High) |
| :-: | :--- | :--- | :--- | :---: |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

---

### 6. Student General Feedback & Suggestions
*What features did you like best, and what would you like improved in the GNHS Guidance Portal?*  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  

---

### 7. Acceptance Sign-Off

**Overall Evaluation Result:**  
- `[ ]` **ACCEPTED**: System meets student needs and is ready for school-wide deployment.  
- `[ ]` **CONDITIONALLY ACCEPTED**: System is acceptable; minor improvements noted above should be addressed.  
- `[ ]` **REJECTED**: System has critical issues and requires fixes before student rollout.  

<br>

| **Student Tester Signature** | **Guidance Counselor Witness** |
| :--- | :--- |
| Signature: ________________________________ | Signature: ________________________________ |
| Printed Name: _____________________________ | Printed Name: _____________________________ |
| Date: _____________________________________ | Date: _____________________________________ |
