/**
 * ====================================================================================================
 * GNHS GUIDANCE SYSTEM — IT PROFESSIONAL FUNCTIONALITY & SOFTWARE QUALITY EVALUATION FORM (ISO/IEC 25010)
 * ====================================================================================================
 * 
 * TARGET AUDIENCE:
 * Exclusively for IT Professionals, Software Engineers, QA / Test Engineers, Capstone Defense Panelists,
 * and Technical Evaluators evaluating the live system at: https://gnhsguidance.com/
 * 
 * INSTRUCTIONS:
 * 1. Open Google Apps Script: https://script.google.com/
 * 2. Click "+ New project".
 * 3. Replace all default code with this script and save (Ctrl+S).
 * 4. Ensure "createGNHSITProfessionalTestForm" is selected, then click "Run".
 * 5. Review permissions if prompted (Allow access).
 * 6. View the Execution Log (Ctrl+Enter) — it prints your live Form Edit URL and Shareable Fill-in URL!
 * ====================================================================================================
 */

function createGNHSITProfessionalTestForm() {
  const formTitle = "GNHS Guidance System — IT Professional Functionality & Software Quality Evaluation";
  const form = FormApp.create(formTitle);

  form.setDescription(
    "Guinayang National High School (GNHS) Automated Guidance Management System\n" +
    "Official Software Quality Assurance (QA) & User Acceptance Testing (UAT) Instrument\n\n" +
    "SYSTEM UNDER TEST (SUT):\n" +
    "• Production URL: https://gnhsguidance.com/\n" +
    "• Architecture: Client-Server / MVC, PHP 8.2 backend, MariaDB/MySQL relational store, Vanilla ES6+ SPA components\n" +
    "• Evaluation Framework: ISO/IEC 25010 Software Product Quality Standards\n\n" +
    "TARGET EVALUATORS:\n" +
    "This technical evaluation instrument is designed exclusively for IT Professionals, Software Engineers, " +
    "Quality Assurance Testers, Capstone Defense Panelists, and Systems Evaluators.\n\n" +
    "EVALUATION VERDICT CRITERIA:\n" +
    "• Pass (Meets Technical Specification): Functionality operates strictly according to design and technical requirements with no errors.\n" +
    "• Pass with Minor Observation: Functional logic operates correctly, but minor cosmetic, UI alignment, or wording polish is noted.\n" +
    "• Fail (Functional Defect / Logic Error): Logic error, security flaw, unhandled exception, or broken state encountered.\n" +
    "• Not Evaluated / N/A: Test case was not covered during this evaluation session."
  );

  form.setConfirmationMessage(
    "Thank you for completing the technical evaluation of the GNHS Guidance System. Your formal QA & UAT assessment has been recorded."
  );
  form.setAllowResponseEdits(true);

  const testColumns = [
    "Pass (Meets Specification)",
    "Pass with Minor Observation",
    "Fail (Defect / Bug Found)",
    "Not Evaluated / N/A"
  ];

  // ====================================================================================================
  // SECTION 1: EVALUATOR PROFILE (IT PROFESSIONAL)
  // ====================================================================================================
  form.addSectionHeaderItem()
    .setTitle("Section 1: Evaluator Profile & Technical Test Environment")
    .setHelpText("Please supply your technical profile and the client environment used during test execution.");

  form.addTextItem()
    .setTitle("1.1 Evaluator Full Name")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("1.2 Professional Designation / Primary Technical Specialization")
    .setChoiceValues([
      "Software Engineer / Full-Stack Developer",
      "Quality Assurance (QA) / Software Test Engineer",
      "Systems Analyst / Software Architect",
      "IT Faculty Member / Capstone Defense Panelist",
      "Information Security / Cybersecurity Specialist",
      "Database Administrator / Cloud Infrastructure Engineer"
    ])
    .setRequired(true);

  form.addTextItem()
    .setTitle("1.3 Organization / Institutional Affiliation")
    .setHelpText("e.g. SDO, University, Tech Company, or DepEd Office")
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("1.4 Testing Device(s) & Hardware Profile (Select all applied)")
    .setChoiceValues([
      "Desktop Workstation (Windows / macOS / Linux)",
      "Laptop (Windows / macOS)",
      "Mobile Smartphone (Android / Chromium Engine)",
      "Mobile Smartphone (iOS / WebKit Safari Engine)",
      "Tablet / iPad"
    ])
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("1.5 Primary Web Browser(s) Exercised")
    .setChoiceValues([
      "Google Chrome (Chromium V8 Engine)",
      "Apple Safari (iOS / macOS WebKit Engine)",
      "Mozilla Firefox (Gecko Engine)",
      "Microsoft Edge (Chromium Engine)",
      "Mobile WebView / Embedded Mobile Browser"
    ])
    .setRequired(true);

  form.addDateItem()
    .setTitle("1.6 Date of Technical Evaluation")
    .setRequired(true);

  // ====================================================================================================
  // MODULE 1: AUTHENTICATION, IDENTITY & ACCESS CONTROL (RBAC & 2FA)
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 1: Authentication, Identity & Access Control (RBAC, 2FA & Google Auth)")
    .setHelpText("Verification of Role-Based Access Control (Student, Teacher, Counselor), RFC 6238 TOTP, Google flow, passwordless email verification, keyboard navigation, and brute-force defenses.");

  form.addGridItem()
    .setTitle("Module 1 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-AUTH-01: RBAC Partitioning — Role segregation and URI guard authorization for Student, Teacher, and Counselor",
      "TC-AUTH-02: Identifier Validation — Numeric 12-digit LRN constraint for students vs. Employee ID/Email credentials for staff",
      "TC-AUTH-03: Sequential Keyboard Navigation — Enter key shifts focus from identifier to password; Enter on password executes login without reload",
      "TC-AUTH-04: Google OAuth / GIS Integration — One-click Google Identity Services credential exchange, JWT parsing, and session mapping",
      "TC-AUTH-05: Passwordless Email Verification — Single-envelope 6-digit cryptographic code dispatch to user's specific email address before signup",
      "TC-AUTH-06: RFC 6238 TOTP Vector QR & Secret — 0ms client-side QR rendering (qrcode.min.js), session secret persistence, and Base32 manual key copy",
      "TC-AUTH-07: Time-Based 2FA Verification — 6-digit TOTP verification against authenticator apps (Google Auth, Microsoft Auth, Authy) with clock-skew tolerance",
      "TC-AUTH-08: Brute-Force & Lockout Defense — Failed attempt rate limiting, progressive lockout throttling, and timestamp-based automated unlock release",
      "TC-AUTH-09: First-Time Profile Wizard — Mandatory attribute completion (LRN, grade/section, security questions, avatar upload & compression)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 1: Technical Observations, Anomalies & Defect Logs (Optional)")
    .setHelpText("Specify any observed authentication vulnerabilities, console errors, or logic deviations.");

  // ====================================================================================================
  // MODULE 2: STUDENT CONCERN & INCIDENT REPORTING ENGINE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 2: Student Concern & Incident Reporting Engine")
    .setHelpText("Verification of incident intake, content quality validation, lexical profanity screening, anonymous tracking tokens, peer reporting, teacher shield, witness boundaries, and multi-media evidence ingestion.");

  form.addGridItem()
    .setTitle("Module 2 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-REP-01: Taxonomy Classification — Accurate classification across 7 predefined guidance concern types with custom specification handler",
      "TC-REP-02: Dual-Layer Anti-Spam Engine — Client and server validation enforcing 30-word minimum, 400-word ceiling, and pattern spam rejection",
      "TC-REP-03: Automated Lexical Screening — Real-time and server-side filtering of inappropriate profanity across subject, description, and witness fields",
      "TC-REP-04: Anonymous Token Cryptography — Identity shrouding, private tracking token generation, and counselor-facing student identity masking",
      "TC-REP-05: Peer Proxy Reporting Model — Third-party reporting capturing victim data while maintaining reporter safety and confidentiality",
      "TC-REP-06: Adviser Concern Shield — Flag restricting visibility of concerns involving advisory teachers from advisory faculty queries",
      "TC-REP-07: Witness Boundary Constraints — Dual-layer enforcement (maxlength='100' characters, max 15 words) with dynamic live double counter UI",
      "TC-REP-08: Multi-Media Evidence Ingestion — Drag-and-drop, file picker, and clipboard paste pipeline supporting JPG, PNG, WEBP, GIF, MP4, MOV up to 100MB"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 2: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 3: STUDENT DASHBOARD & CASE STATE MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 3: Student Dashboard & Case State Management")
    .setHelpText("Verification of real-time KPI metrics, responsive table wrappers, upcoming session notifications, case detail modals, and appeals state transitions.");

  form.addGridItem()
    .setTitle("Module 3 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-STAT-01: Real-Time KPI Aggregation — Asynchronous summation and rendering of Total Cases, Pending, Resolved, and Unread counts",
      "TC-STAT-02: Responsive Viewport Adaptation — Mobile viewport scaling with -webkit-text-size-adjust: 100% preventing font inflation on empty states",
      "TC-STAT-03: Chronological Session Alerts — Accurate retrieval and display of next scheduled consultation (Counselor, timestamp, venue)",
      "TC-STAT-04: Relational Detail Modal — Seamless relational case data loading (category, triage priority, AI overview, counselor assignments)",
      "TC-STAT-05: Case Appeal Lifecycle — Formal appeal justification submission on resolved cases successfully transitioning records to review queues",
      "TC-STAT-06: Session History & Attendance Logs — Historical session records tracking past consultation feedback and completion status"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 3: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 4: GUIDANCE MESSAGING & ASYNCHRONOUS REAL-TIME CHAT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 4: Guidance Messaging & Real-Time Chat Engine")
    .setHelpText("Verification of 1-to-1 case message isolation, anonymous masking, optimistic UI, background polling, and Messenger-style 'Seen' read receipts.");

  form.addGridItem()
    .setTitle("Module 4 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-CHAT-01: Relational Thread Binding — 1-to-1 conversation isolation strictly bounded to distinct Case IDs (case_id foreign key)",
      "TC-CHAT-02: Anonymous Identity Masking — Student name and avatar masked to 'Anonymous Student' in counselor chat thread when enabled",
      "TC-CHAT-03: Optimistic UI & Polling Sync — Instant client-side message bubble dispatch with non-blocking 3.5-second polling loop",
      "TC-CHAT-04: Read Receipt Transaction — Database atomic update marking is_read=1 and read_at=NOW() when recipient accesses conversation",
      "TC-CHAT-05: Messenger-Style Seen Receipts — Visual rendering of '✓ Sent' (unread) vs. '✓✓ Seen [timestamp]' on the latest message opened by recipient",
      "TC-CHAT-06: Permanent Chat Archival — Complete, immutable chat transcript preservation for concluded and resolved guidance cases"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 4: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 5: FACULTY REFERRAL & ADVISORY OVERSIGHT (TEACHER PORTAL)
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 5: Faculty Referral & Advisory Oversight (Teacher Portal)")
    .setHelpText("Verification of structured referral intake, urgency triage, teacher-counselor direct collaboration chat, and student privacy compliance in advisory rosters.");

  form.addGridItem()
    .setTitle("Module 5 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-TEACH-01: Teacher Referral Metrics — Tracking faculty-submitted referrals across status queues (Submitted, Under Review, Scheduled, Resolved)",
      "TC-TEACH-02: Structured Referral Ingestion — Capture of student identity, behavioral indicators, academic distress signs, and prior interventions",
      "TC-TEACH-03: Referral Priority Indexing — Priority triage tagging (Low, Medium, High) for counselor queue ranking",
      "TC-TEACH-04: Referral Collaboration Chat — Dedicated consultation channel between referring faculty and counselor with bidirectional Seen receipts",
      "TC-TEACH-05: Advisory Class Privacy Enforcement — Section roster displays aggregated guidance flags while strictly redacting private incident narratives, evidence, and witness logs",
      "TC-TEACH-06: Teacher Shield Query Exclusion — Robust query filtering preventing teachers from accessing incidents filed against themselves"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 5: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 6: GUIDANCE ADMINISTRATION & MASTER CASE MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 6: Guidance Administration & Master Case Management (Counselor Portal)")
    .setHelpText("Verification of master triage dashboard, multi-parameter search/filters, counselor assignment, state machine lifecycle, and appeals adjudication.");

  form.addGridItem()
    .setTitle("Module 6 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-ADMIN-01: Master Counselor Dashboard — Centralized intake feed, urgent referral alerts, and real-time caseload metrics",
      "TC-ADMIN-02: Multi-Parameter Query Engine — Dynamic SQL filtering across status, priority, category, date range, and student identifiers",
      "TC-ADMIN-03: Workload & Case Assignment — Case allocation and ownership attribution to specific guidance counselors",
      "TC-ADMIN-04: State Machine Lifecycle — Sequential progression (Pending -> Under Review -> Ongoing -> Concluded -> Resolved -> Reopened)",
      "TC-ADMIN-05: Student Appeal Adjudication — Counselor review of student appeal justifications and execution of case reopening workflows",
      "TC-ADMIN-06: Master Archival Vault — Permanent archival storage of closed records, counselor resolution logs, and historical evidence",
      "TC-ADMIN-07: Follow-up Care Tracking — Longitudinal observation logs, check-in schedules, and post-resolution monitoring intervals"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 6: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 7: COUNSELING SESSIONS & IN-SESSION MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 7: Counseling Sessions & Clinical In-Session Management")
    .setHelpText("Verification of session booking, countdown timers, real-time in-session stopwatch, clinical notes, and AI guidance recommendations.");

  form.addGridItem()
    .setTitle("Module 7 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-SESS-01: Session Booking & Venue Allocation — Booking calendar appointments linked to cases/referrals with venue, time, and attendee specifications",
      "TC-SESS-02: Chronological Feed & Countdowns — Real-time countdown calculation to scheduled sessions with status tagging",
      "TC-SESS-03: Active Session Controller — In-session state tracking with client/server synchronized stopwatch timer (startSessionTimer)",
      "TC-SESS-04: Confidential Clinical Notes — Counselor progress notes, behavioral observations, and student agreement commitments",
      "TC-SESS-05: AI Guidance Resolution Assistant — Algorithmic summarization and contextual DepEd guidance strategy suggestions (summarizer.php)",
      "TC-SESS-06: Concluded Session Archive — Audited records of session durations, timestamps, and resolution outcomes"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 7: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 8: EVIDENCE VAULT & MULTI-MEDIA LIGHTBOX ARCHITECTURE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 8: Evidence Vault & Multi-Media Lightbox Architecture")
    .setHelpText("Verification of evidence storage security, file integrity, in-browser media stream playback, and role-based data isolation.");

  form.addGridItem()
    .setTitle("Module 8 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-EVID-01: Secure Media Ingestion — Upload directory isolation, filename hashing, and binary sanitization",
      "TC-EVID-02: In-Browser Media Lightbox — Responsive modal with pan, zoom, and cross-browser video stream playback (MP4, MOV, WEBM)",
      "TC-EVID-03: Large File Handling — Stable handling and buffering of large media assets up to 100MB per file",
      "TC-EVID-04: Granular Evidence Access Control — Absolute access control restricting evidence visibility strictly to Counselors, denying teacher and student access"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 8: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 9: ANALYTICS, TREND MODELING & VISUAL REPORTING
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 9: Analytics, Trend Modeling & Visual Reporting")
    .setHelpText("Verification of statistical aggregation, incident trend visualization, resolution efficiency metrics, and demographic heatmaps.");

  form.addGridItem()
    .setTitle("Module 9 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-DATA-01: Concern Taxonomy Distribution — Statistical aggregation and chart rendering of incident taxonomy",
      "TC-DATA-02: Longitudinal Temporal Trends — Month-over-month and quarterly incident trajectory tracking",
      "TC-DATA-03: Resolution Efficiency Metrics — Statistical computation of average time-to-resolution and resolution success rates",
      "TC-DATA-04: Priority Risk Matrix — Visual risk categorization and incident urgency distribution (Low, Medium, High, Urgent)",
      "TC-DATA-05: Demographic & Section Heatmap — Incident frequency mapping across grade levels and advisory sections",
      "TC-DATA-06: Dynamic Temporal Query Filtering — Dynamic SQL filtering by Academic Year, Semester, and custom date brackets"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 9: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 10: USER ADMINISTRATION & DIRECTORY SERVICES
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 10: User Administration & Directory Services")
    .setHelpText("Verification of user accounts master table, indexed queries, advisory class binding, and administrative password resets.");

  form.addGridItem()
    .setTitle("Module 10 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-USER-01: Master Accounts Directory — Unified administrative table covering all user entities across Student, Teacher, and Counselor roles",
      "TC-USER-02: Indexed Query & Role Filtering — Fast search indexing by Name, Email, LRN, Employee ID, and Advisory Section",
      "TC-USER-03: Profile & Attribute Management — Real-time modification of user profiles, sections, roles, and account statuses (Active, Inactive, Suspended)",
      "TC-USER-04: Teacher Advisory Class Association — Relational binding and re-binding of teachers to designated advisory classes (assign_advisory.php)",
      "TC-USER-05: Administrative Password Reset Protocol — Generation and secure issuance of temporary credentials by authorized counselors"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 10: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 11: SECURITY, RECOVERY, SYSTEM TIME & UX INFRASTRUCTURE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 11: Security, Recovery, System Time & UX Infrastructure")
    .setHelpText("Verification of password resets, 2FA emergency resets, Philippine Standard Time (UTC+8) synchronization, notifications, and mobile adaptation.");

  form.addGridItem()
    .setTitle("Module 11 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "TC-SEC-01: Self-Service Tokenized Reset — Cryptographic 10-minute expiring tokens sent via secure email link",
      "TC-SEC-02: Manual Reset Ticket Workflow — Identity-verification reset ticket for students lacking active email access",
      "TC-SEC-03: Real-Time Ticket Status Query — Asynchronous inquiry endpoint tracking reset ticket verification progress",
      "TC-SEC-04: 2FA Emergency Recovery Protocol — Identity verification via hashed security questions and administrative TOTP secret reset",
      "TC-SEC-05: Database Timezone Synchronization — Strict MySQL session timezone enforcement (+08:00 / PHT) harmonized with PHP's Asia/Manila",
      "TC-SEC-06: Cross-Browser Datetime Serialization — Robust ISO-8601 parsing (parseChatDate()) across Safari (WebKit), Chrome (V8), Firefox, and Edge",
      "TC-SEC-07: In-App Event Notification Engine — Topbar notification bell with dynamic badge counters triggered on chat messages, sessions, and status updates",
      "TC-SEC-08: Theme Engine & Preference Persistence — High-contrast Maroon & White Light/Dark mode toggling with localStorage state retention",
      "TC-SEC-09: Viewport Adaptation & Mobile Navigation — Responsive sidebar-to-drawer transitions, touch targets, and horizontal overflow clamping",
      "TC-SEC-10: Interactive Guided Tour Architecture — Interactive multi-step onboarding guide (tour.js) walking users through key workflows"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 11: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // SECTION 12: ISO/IEC 25010 SOFTWARE QUALITY EVALUATION & PRODUCTION SIGN-OFF
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Section 12: ISO/IEC 25010 Software Quality Evaluation & Production Readiness Sign-Off")
    .setHelpText("Standardized Software Product Quality evaluation based on ISO/IEC 25010 benchmarks.");

  form.addScaleItem()
    .setTitle("12.1 Functional Suitability (Completeness, Correctness & Task Appropriateness)")
    .setBounds(1, 5)
    .setLabels("1 = Non-Compliant / Flawed", "5 = Fully Compliant & Robust")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.2 Security & Data Privacy (Confidentiality, Integrity, Non-Repudiation, RBAC)")
    .setBounds(1, 5)
    .setLabels("1 = High Security Risk", "5 = High Security & DepEd Compliant")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.3 Performance Efficiency (Turnaround Time, Asynchronous Polling, Resource Efficiency)")
    .setBounds(1, 5)
    .setLabels("1 = Latency / High Overhead", "5 = Fast & Highly Responsive")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.4 Usability & User Experience (Learnability, Operability, Accessibility & Error Protection)")
    .setBounds(1, 5)
    .setLabels("1 = Poor Usability", "5 = Highly Intuitive & Ergonomic")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.5 Reliability & Fault Tolerance (Input Sanitization, Error Handling & Recovery)")
    .setBounds(1, 5)
    .setLabels("1 = Fragile / Unhandled Exceptions", "5 = Highly Resilient & Fault-Tolerant")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.6 Compatibility & Portability (Cross-Browser Engine Parity & Mobile Viewport Adaptation)")
    .setBounds(1, 5)
    .setLabels("1 = Engine Incompatibilities", "5 = Flawless Cross-Platform Adaptation")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("12.7 Overall Production Deployment Recommendation")
    .setChoiceValues([
      "Approved for Immediate Production Deployment (No Blocking Defects)",
      "Approved with Minor Recommended Polish (Cosmetic / Non-Functional Enhancements)",
      "Conditional Approval (Requires Retesting after Specific Defect Fixes)",
      "Rejected (Critical Architectural, Security, or Functional Defects Encountered)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("12.8 Defect & Anomaly Logging (Technical Bugs / Edge Cases Identified)")
    .setHelpText("Provide steps to reproduce, HTTP status codes, device/browser details, and expected vs. actual outcomes for any discovered bugs.");

  form.addParagraphTextItem()
    .setTitle("12.9 Architectural Observations, Technical Commendations & Panel Remarks")
    .setHelpText("Provide technical assessment feedback regarding the system's software architecture, security mechanisms, or capstone defense sign-off.");

  // Output URLs in execution log
  Logger.log("=================================================================================");
  Logger.log("IT PROFESSIONAL FUNCTIONALITY TEST FORM (ISO/IEC 25010) GENERATED SUCCESSFULLY!");
  Logger.log("Edit URL (Form Builder): " + form.getEditUrl());
  Logger.log("Shareable Fill-in URL (for IT Evaluators): " + form.getPublishedUrl());
  Logger.log("=================================================================================");
}
