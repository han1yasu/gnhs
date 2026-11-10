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
    "• Pass: Feature functions accurately with no defects according to technical requirements.\n" +
    "• Minor Polish: Functional logic works, but minor cosmetic, UI alignment, or wording polish is noted.\n" +
    "• Bug Found: Functional defect, logic error, broken state, or unhandled exception encountered.\n" +
    "• Not Tested: Test case was not covered during this evaluation session."
  );

  form.setConfirmationMessage(
    "Thank you for completing the technical evaluation of the GNHS Guidance System. Your formal QA & UAT assessment has been recorded."
  );
  form.setAllowResponseEdits(true);

  // Concise column labels so Google Forms displays them in full without "..." truncation
  const testColumns = [
    "Pass",
    "Minor Polish",
    "Bug Found",
    "Not Tested"
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
  // MODULE 1: AUTHENTICATION, IDENTITY & ACCESS CONTROL
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 1: Authentication, Identity & Access Control")
    .setHelpText("Evaluate role segregation, credential constraints, keyboard traversal, Google flow, email codes, 2FA/TOTP vector QR, and brute-force lockouts.");

  form.addGridItem()
    .setTitle("Module 1 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Role-Based Access Control (Student, Teacher, Counselor Isolation)",
      "12-digit LRN & Employee ID Credential Validation",
      "Enter Key Progression (Email/LRN -> Password -> Login Submit)",
      "Google Sign-In & Shopee-Style Account Chooser",
      "Passwordless 6-Digit Email Code Verification",
      "Instant Vector QR & Manual Key 2FA Setup",
      "Time-Based 6-Digit Authenticator App Verification (TOTP)",
      "Brute-Force Lockout Defense & Unlock Countdown",
      "First-Time Onboarding & Profile Setup Wizard"
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
    .setHelpText("Evaluate incident taxonomy, 30-400 word quality limits, profanity filters, anonymous tokens, peer reporting, teacher shields, witness limits, and evidence upload.");

  form.addGridItem()
    .setTitle("Module 2 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Concern Categorization (7 Predefined Types & Custom Other)",
      "Anti-Spam Word Limits (Minimum 30, Maximum 400 Words)",
      "Automated Profanity & Inappropriate Word Filter",
      "Anonymous Reporting & Identity Masking Token",
      "Peer Proxy Reporting (Report for a Friend / Classmate)",
      "Adviser / Teacher Concern Protection Shield",
      "Witness Field Limits (Max 100 Characters / Max 15 Words)",
      "Multi-Media Evidence Upload (Images & Video up to 100MB)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 2: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 3: STUDENT DASHBOARD & CASE STATE MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 3: Student Dashboard & Case State Management")
    .setHelpText("Evaluate KPI metric counters, responsive table wrappers, upcoming session alerts, case detail modals, and appeals state transitions.");

  form.addGridItem()
    .setTitle("Module 3 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Real-Time KPI Metric Cards (Cases, Review, Resolved, Notifs)",
      "Mobile-Responsive Table & Empty State Scaling",
      "Upcoming Counseling Session Reminder Alert Card",
      "Detailed Case Information Modal & Triage View",
      "Case Appeal Submission for Closed Concerns",
      "Counseling Session History & Attendance Records"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 3: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 4: GUIDANCE MESSAGING & ASYNCHRONOUS REAL-TIME CHAT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 4: Guidance Messaging & Real-Time Chat Engine")
    .setHelpText("Evaluate 1-to-1 case message isolation, anonymous masking, optimistic UI, background polling, and Messenger-style 'Seen' read receipts.");

  form.addGridItem()
    .setTitle("Module 4 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "1-to-1 Private Chat Thread per Case ID",
      "Anonymous Student Identity Masking in Chat View",
      "Optimistic Message Dispatch & 3.5s Polling Sync",
      "Database Read Receipt Tracking (is_read & read_at)",
      "Messenger-Style Delivery & Seen Status Badges",
      "Concluded Case Chat Transcript Archival"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 4: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 5: FACULTY REFERRAL & ADVISORY OVERSIGHT (TEACHER PORTAL)
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 5: Faculty Referral & Advisory Oversight (Teacher Portal)")
    .setHelpText("Evaluate referral intake, urgency priority indexing, teacher-counselor direct collaboration chat, and student privacy compliance in advisory rosters.");

  form.addGridItem()
    .setTitle("Module 5 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Teacher Referral Dashboard & KPI Metrics Feed",
      "Structured Student Referral Submission Form",
      "Referral Triage & Priority Urgency Tagging",
      "Teacher-Counselor Direct Collaboration Chat with Seen Receipts",
      "Advisory Section Roster with Confidential Guidance Flags",
      "Teacher Shield Query Exclusion (Hides Teacher Concerns)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 5: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 6: GUIDANCE ADMINISTRATION & MASTER CASE MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 6: Guidance Administration & Master Case Management (Counselor Portal)")
    .setHelpText("Evaluate master triage dashboard, multi-parameter search/filters, counselor assignment, state machine lifecycle, and appeals adjudication.");

  form.addGridItem()
    .setTitle("Module 6 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Counselor Master Dashboard & Intake Alert Feed",
      "Multi-Filter Case Search (Status, Priority, Category, Date)",
      "Counselor Workload & Case Ownership Assignment",
      "Linear Case Lifecycle State Machine Progression",
      "Student Case Appeal Review & Reopening Workflow",
      "Master Archival Vault for Resolved Incident Records",
      "Post-Counseling Follow-Up & Behavioral Monitoring"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 6: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 7: COUNSELING SESSIONS & IN-SESSION MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 7: Counseling Sessions & Clinical In-Session Management")
    .setHelpText("Evaluate session booking, countdown timers, real-time in-session stopwatch, clinical notes, and AI guidance recommendations.");

  form.addGridItem()
    .setTitle("Module 7 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Session Booking & Venue/Time Allocation",
      "Upcoming Sessions Calendar Feed with Live Countdowns",
      "Live In-Session Controller with Real-Time Stopwatch",
      "Confidential Counselor Clinical Notes & Action Plans",
      "AI Guidance Resolution & DepEd Intervention Assistant",
      "Concluded Session Archive with Attendance Logs"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 7: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 8: EVIDENCE VAULT & MULTI-MEDIA LIGHTBOX ARCHITECTURE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 8: Evidence Vault & Multi-Media Lightbox Architecture")
    .setHelpText("Evaluate evidence storage security, file integrity, in-browser media stream playback, and role-based data isolation.");

  form.addGridItem()
    .setTitle("Module 8 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Secure Evidence File Storage & Binary Sanitization",
      "In-Browser Responsive Media Lightbox (Zoom & Video Player)",
      "Large File Upload Processing (Up to 100MB per file)",
      "Role-Based Evidence Access Control (Counselor Only)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 8: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 9: ANALYTICS, TREND MODELING & VISUAL REPORTING
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 9: Analytics, Trend Modeling & Visual Reporting")
    .setHelpText("Evaluate statistical aggregation, incident trend visualization, resolution efficiency metrics, and demographic heatmaps.");

  form.addGridItem()
    .setTitle("Module 9 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Concern Category Distribution Visualization",
      "Longitudinal Incident Trend Trajectory Tracking",
      "Resolution Rate & Turnaround Time Metrics",
      "Incident Priority & Urgency Risk Matrix",
      "Grade Level & Advisory Section Incident Heatmap",
      "Academic Term, Semester & Date Range Filters"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 9: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 10: USER ADMINISTRATION & DIRECTORY SERVICES
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 10: User Administration & Directory Services")
    .setHelpText("Evaluate user accounts master table, indexed queries, advisory class binding, and administrative password resets.");

  form.addGridItem()
    .setTitle("Module 10 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Master User Accounts Directory (Student, Teacher, Counselor)",
      "Indexed Search (Name, Email, LRN, Employee ID)",
      "User Profile Attribute Editing & Status Toggles",
      "Teacher Advisory Class Binding & Reassignment",
      "Administrator Password Reset & Temporary Credential Issue"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 10: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // MODULE 11: SECURITY, RECOVERY, SYSTEM TIME & UX INFRASTRUCTURE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 11: Security, Recovery, System Time & UX Infrastructure")
    .setHelpText("Evaluate password resets, 2FA emergency resets, Philippine Standard Time (UTC+8) synchronization, notifications, and mobile adaptation.");

  form.addGridItem()
    .setTitle("Module 11 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Self-Service Password Reset via 10-Minute Token Link",
      "Manual Reset Request Ticket for Non-Email Users",
      "Real-Time Ticket Status Inquiry Tracker",
      "2FA Emergency Secret Reset via Security Questions",
      "Philippine Standard Time Sync (UTC+8 Across MySQL & PHP)",
      "Cross-Browser Datetime Serialization (Safari, Chrome, Firefox, Edge)",
      "In-App Real-Time Notification Bell & Counter Badges",
      "High-Contrast Light & Dark Mode Theme Persistence",
      "Mobile Viewport Optimization & Collapsible Navigation",
      "Interactive First-Time Guided User Tour"
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
