# GUINAYANG NATIONAL HIGH SCHOOL
## Guidance & Counseling Office
### User Acceptance Testing (UAT) Form — Guidance Counselor & Administrator Portal

---

| **System Name** | GNHS Guidance Case Management System | **Document Code** | UAT-FORM-ADM-03 |
| :--- | :--- | :--- | :--- |
| **Target User Role** | Guidance Counselor / Guidance Head / System Administrator | **System Version** | v1.0 |
| **Date of Testing** | `YYYY-MM-DD` | **Testing Environment** | Web Browser (Chrome / Edge / Firefox) |

---

### 1. Tester Information

| Field | Tester Information |
| :--- | :--- |
| **Administrator / Counselor Name** | __________________________________________________ |
| **Official Position / Title** | `[ ]` Registered Guidance Counselor (RGC) &nbsp; `[ ]` Guidance Designate &nbsp; `[ ]` System Admin |
| **Department / Unit** | Guidance & Counseling Services Unit |
| **Official DepEd Email** | __________________________________________________ |
| **Device & Browser Used** | Device: ___________________ Browser: ____________________ |

---

### 2. Testing Instructions
1. Execute each test case in sequence across the **Core Guidance Modules**.
2. Document the system's exact response in the **Actual Result** column.
3. Assign the appropriate **Status**:
   - **PASS** (`P`): Functions as expected according to guidance protocols and design specs.
   - **FAIL** (`F`): Error encountered, data mismatch, security defect, or malfunction.
   - **CONDITIONAL PASS** (`C`): Functional, but requires minor layout, workflow, or wording refinement.
   - **BLOCKED** (`B`): Test cannot be completed due to missing data or prerequisite blockers.
4. Complete the **Administrative Usability Evaluation** and **Defect Log** if any bugs are discovered.
5. Provide your overall appraisal and formal sign-off in the **Acceptance Sign-Off** section.

---

### 3. Functional Test Cases

#### Module 1: Guidance Admin Security & Account Management
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-01** | **Admin Secure Login** | Login with administrator credentials (Guidance Counselor) and verify TOTP 2FA code. | Login succeeds; session is established with role-appropriate permissions and navigation. | | `[ ] P`<br>`[ ] F` |
| **ADM-02** | **User Management (`admin-users.php`)** | In Guidance Admin portal, register walk-in students (validating 12-digit LRN), edit profile, or toggle role / status. | User record updates in database; student LRN enforces strictly 12 digits; role-based access is respected. | | `[ ] P`<br>`[ ] F` |
| **ADM-03** | **Password Reset Approvals** | In Guidance Admin portal, open *Password Reset Requests* (`admin-reset-requests.php`). Approve or reject pending student/teacher requests. | Reset password is processed by Guidance Admin; request status updates to handled. | | `[ ] P`<br>`[ ] F` |
| **ADM-04** | **2FA / TOTP Reset Requests** | In Guidance Admin portal, open `admin-2fa-requests.php`. Review and reset locked user's authenticator secret. | User's 2FA secret is reset safely by Guidance Admin, allowing user to re-bind authenticator on next login. | | `[ ] P`<br>`[ ] F` |

#### Module 2: Admin Executive Dashboard
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-05** | **Executive Statistics Counters** | Verify metric counters: *Total Cases*, *Active Sessions*, *Pending Referrals*, *Urgent Alerts*. | Metrics display real-time, accurate counts matching current active database records. | | `[ ] P`<br>`[ ] F` |
| **ADM-06** | **Today's Session Schedule** | View today's scheduled appointments list on the dashboard. | Shows student names, scheduled times, consultation modes, and case references. | | `[ ] P`<br>`[ ] F` |
| **ADM-07** | **Urgent Case Notification Alert**| Submit an urgent referral or bullying report from another user, then refresh admin dashboard. | High-priority banner/badge highlights the urgent case requiring immediate triage. | | `[ ] P`<br>`[ ] F` |

#### Module 3: Referral Management & Intake Triage (`admin-referrals.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-08** | **Referral Queue & Filters** | View incoming referrals list. Filter by urgency (*Urgent*, *Moderate*, *Low*) and status. | Referrals sort correctly; urgent cases appear prioritized at the top. | | `[ ] P`<br>`[ ] F` |
| **ADM-09** | **Review Referral Details** | Click on a referral card/row to open full details modal. | Displays student info, referring teacher, detailed observations, timestamp, and attachments. | | `[ ] P`<br>`[ ] F` |
| **ADM-10** | **Update Referral Status** | Change status from *Pending* to *Under Review* or *Ongoing*. | Status updates immediately; action is recorded with timestamp in the audit log. | | `[ ] P`<br>`[ ] F` |
| **ADM-11** | **Faculty Coordination Chat** | Open referral chat thread (`admin-referrals-chat.php`) and reply to the referring teacher. | Chat connects counselor and teacher without revealing confidential counseling notes. | | `[ ] P`<br>`[ ] F` |
| **ADM-12** | **Convert Referral to Case** | Click *Create Counseling Case* or *Schedule Session* directly from the referral modal. | Referral details populate the case/session creation form smoothly. | | `[ ] P`<br>`[ ] F` |

#### Module 4: Counseling Session Management (`admin-sessions.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-13** | **Schedule Counseling Session** | Click *Schedule Session*. Select student, case, date, time, and counseling room/mode. | Session is saved; displays in active schedule; student is notified. | | `[ ] P`<br>`[ ] F` |
| **ADM-14** | **Start Live Session (`in_progress`)** | When consultation begins, update session status to *In Progress*. | Session indicates active status; timer/intake recording interface activates. | | `[ ] P`<br>`[ ] F` |
| **ADM-15** | **Log Counselor Clinical Notes**| Enter confidential counseling notes, behavioral observations, and student agreements. | Notes save securely with strict access control; confidential text is protected. | | `[ ] P`<br>`[ ] F` |
| **ADM-16** | **Complete Counseling Session**| Set status to *Completed*. Enter outcome/recommendation. | Session moves to Completed; option to schedule follow-up session appears. | | `[ ] P`<br>`[ ] F` |
| **ADM-17** | **Schedule Follow-Up (`admin-followups.php`)** | Set a follow-up appointment date for monitoring student progress. | Follow-up is recorded in calendar and alerts counselor on the scheduled date. | | `[ ] P`<br>`[ ] F` |
| **ADM-18** | **Sessions Archive (`admin-sessions-archive.php`)** | Search and inspect past completed sessions by student name, date range, or case ID. | Historical counseling logs retrieve accurately with full privacy protections intact. | | `[ ] P`<br>`[ ] F` |

#### Module 5: Comprehensive Case Management (`admin-cases.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-19** | **Case File Creation** | Create a formal guidance case file. Assign case category, priority, and summary. | Unique Case Number (e.g. `CASE-2026-XXXX`) is generated and linked to student record. | | `[ ] P`<br>`[ ] F` |
| **ADM-20** | **Adviser-Shielded Cases** | Open a case reported by a student with the *Adviser Protection* flag enabled. | Case is clearly flagged as confidential from the class adviser; only guidance staff have access. | | `[ ] P`<br>`[ ] F` |
| **ADM-21** | **Case Status Progression** | Transition case status through lifecycle (*Active* → *Monitoring* → *Resolved* → *Closed*). | Status transitions save correctly; resolution summary and date closed are recorded. | | `[ ] P`<br>`[ ] F` |
| **ADM-22** | **Case Search & Audit Trail**| Search cases by student name, keyword, or incident type. | Instant search results with comprehensive timeline of sessions and actions taken. | | `[ ] P`<br>`[ ] F` |

#### Module 6: Live Consultation Chat (`admin-chats.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-23** | **Student Chat Inbox** | Open *Student Chats*. Check active student conversation threads. | Unread message badges indicate incoming inquiries; threads sort by recent activity. | | `[ ] P`<br>`[ ] F` |
| **ADM-24** | **Counseling Message Response** | Send real-time guidance responses and crisis intervention advice to student. | Messages transmit instantly; student reads message without page reload. | | `[ ] P`<br>`[ ] F` |
| **ADM-25** | **Chat Archiving (`admin-chat-archive.php`)** | Close/archive an ongoing chat thread once resolved. | Chat thread moves to archive; conversation transcript is preserved for legal/guidance records. | | `[ ] P`<br>`[ ] F` |

#### Module 7: Analytics & DepEd Compliance Reports (`admin-analytics.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-26** | **Graphical Visualizations** | Inspect demographic charts, concern type breakdown (Bullying, Stress, etc.), monthly trends. | Visual graphs render accurately with correct proportional data and legends. | | `[ ] P`<br>`[ ] F` |
| **ADM-27** | **Date & Demographic Filters** | Filter analytics by school quarter, month, grade level (Grades 7–10), or concern type. | Charts and numerical summaries recalculate accurately based on selected parameters. | | `[ ] P`<br>`[ ] F` |
| **ADM-28** | **Printable Report Generation** | Click *Print / Export Report* for official guidance accomplishment reports. | Formats clean, printer-friendly summary with school header, tables, and signature blocks. | | `[ ] P`<br>`[ ] F` |

#### Module 8: System Archiving & Data Governance (`admin-archive.php`)
| Test ID | Feature / Action | Test Procedure | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **ADM-29** | **Records Archiving** | Archive closed cases and inactive student records. | Archived records are removed from active views but remain searchable in the archive. | | `[ ] P`<br>`[ ] F` |
| **ADM-30** | **Record Restoration / View** | Open an archived record and verify historical notes. Test restore feature if applicable. | Data integrity is maintained; all original notes, dates, and evidence remain intact. | | `[ ] P`<br>`[ ] F` |

---

### 4. Usability & Compliance Evaluation (Counselor & Admin Perspective)
*Please rate each statement on a scale of 1 to 5:*  
**(1 = Strongly Disagree | 2 = Disagree | 3 = Neutral | 4 = Agree | 5 = Strongly Agree)**

| # | Evaluation Statement | Rating (1 - 5) | Comments / Feedback |
| :-: | :--- | :-: | :--- |
| **1** | The system complies with DepEd Child Protection Policy & Data Privacy Act confidentiality standards. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **2** | The intake triage workflow from referral to case creation is streamlined and efficient. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **3** | Scheduling, conducting, and logging counseling sessions is organized and intuitive. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **4** | The Adviser Protection feature securely isolates sensitive student concerns from faculty. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **5** | Analytics and reporting tools significantly reduce the time needed to compile quarterly reports. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **6** | Account security, 2FA management, and password reset handling are robust and administrative-friendly. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |
| **7** | Overall system reliability, speed, and database responsiveness meet school operational standards. | `[ ]` 1 &nbsp; `[ ]` 2 &nbsp; `[ ]` 3 &nbsp; `[ ]` 4 &nbsp; `[ ]` 5 | |

---

### 5. Defect / Issue Log (To be filled if bugs are encountered)

| # | Module / Page | Description of Problem | Steps to Reproduce | Severity (Low/Med/High) |
| :-: | :--- | :--- | :--- | :---: |
| 1 | | | | |
| 2 | | | | |
| 3 | | | | |

---

### 6. Guidance Counselor Feedback & Policy Recommendations
*Administrative recommendations, feature additions, or workflow adjustments for future system iterations:*  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  
____________________________________________________________________________________________________  

---

### 7. Acceptance Sign-Off & Official Endorsement

**Overall Evaluation Result:**  
- `[ ]` **ACCEPTED**: System meets all operational, clinical, and administrative requirements for official production release.  
- `[ ]` **CONDITIONALLY ACCEPTED**: System is accepted pending resolution of minor items listed in Defect Log.  
- `[ ]` **REJECTED**: Critical issues prevent system deployment; requires revision and re-testing.  

<br>

| **Evaluated & Tested By:** | **Approved & Endorsed By:** |
| :--- | :--- |
| Signature: ________________________________ | Signature: ________________________________ |
| Printed Name: _____________________________ | Printed Name: _____________________________ |
| Designation: Guidance Counselor / Designate | Designation: School Principal / Head Teacher |
| Date: _____________________________________ | Date: _____________________________________ |
