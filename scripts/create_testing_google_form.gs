/**
 * =========================================================================================
 * GNHS GUIDANCE SYSTEM — COMPREHENSIVE FUNCTIONALITY & ACCEPTANCE TESTING FORM GENERATOR
 * =========================================================================================
 * 
 * INSTRUCTIONS:
 * 1. Open Google Apps Script: https://script.google.com/
 * 2. Click "New project".
 * 3. Delete any default code in Code.gs, paste this entire script, and click "Save" (Ctrl+S).
 * 4. Select the function "createGNHSFunctionalityTestForm" and click "Run".
 * 5. Review and grant permissions if prompted by Google.
 * 6. Check the Execution Log (Ctrl+Enter) — it will output the live Edit URL and Shareable URL of your new Form!
 * =========================================================================================
 */

function createGNHSFunctionalityTestForm() {
  const formTitle = "GNHS Guidance System — Comprehensive Functionality & Acceptance Testing";
  const form = FormApp.create(formTitle);

  form.setDescription(
    "Guinayang National High School (GNHS) Guidance System\n" +
    "Official Functionality, Quality Assurance & User Acceptance Testing (UAT) Form\n\n" +
    "Live Deployment URL: https://gnhsguidance.com/\n\n" +
    "PURPOSE:\n" +
    "This evaluation form systematically verifies all functional modules of the GNHS Guidance System across " +
    "Student, Faculty/Adviser, and Guidance Counselor roles. Please evaluate each test case based on your actual " +
    "testing on desktop, laptop, or mobile devices.\n\n" +
    "EVALUATION CRITERIA:\n" +
    "• Passed (Working as Expected): Feature functions accurately with no defects.\n" +
    "• Needs Improvement: Feature works but requires UI, wording, or minor behavioral polish.\n" +
    "• Failed (Bug Found): Feature failed to execute, displayed error, or produced incorrect output.\n" +
    "• Not Tested / N/A: Test case was not covered during this testing session."
  );

  form.setConfirmationMessage(
    "Thank you! Your functionality testing evaluation for the GNHS Guidance System has been recorded successfully."
  );
  form.setAllowResponseEdits(true);

  const testColumns = [
    "Passed (Working as Expected)",
    "Needs Improvement",
    "Failed (Bug Found)",
    "Not Tested / N/A"
  ];

  // =========================================================================================
  // SECTION 1: TESTER IDENTIFICATION & ENVIRONMENT
  // =========================================================================================
  form.addSectionHeaderItem()
    .setTitle("Tester Identification & Testing Environment")
    .setHelpText("Please provide your details and the device/browser used during testing.");

  form.addTextItem()
    .setTitle("1. Tester Full Name")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("2. Evaluator Role")
    .setChoiceValues([
      "Student Evaluator",
      "Advisory Teacher / Faculty Member",
      "Guidance Counselor / Guidance Admin",
      "IT Specialist / Technical Evaluator / Panel Member",
      "School Administrator"
    ])
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("3. Testing Device(s) Used (Select all that apply)")
    .setChoiceValues([
      "Desktop Computer (PC)",
      "Laptop (Windows / macOS)",
      "Mobile Smartphone (Android)",
      "Mobile Smartphone (iOS / iPhone)",
      "Tablet / iPad"
    ])
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("4. Web Browser(s) Tested")
    .setChoiceValues([
      "Google Chrome",
      "Safari (iOS / macOS)",
      "Mozilla Firefox",
      "Microsoft Edge",
      "Android System Webview / Mobile Chrome"
    ])
    .setRequired(true);

  form.addDateItem()
    .setTitle("5. Date of Testing")
    .setRequired(true);

  // =========================================================================================
  // MODULE 1: AUTHENTICATION, IDENTITY & ACCESS CONTROL
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 1: Authentication, Identity & Access Control")
    .setHelpText("Evaluation of multi-role login, 2FA/TOTP authenticator, Google flow, passwordless email verification, and keyboard progression.");

  form.addGridItem()
    .setTitle("Module 1 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "1.1 Student Login using 12-digit LRN & Password",
      "1.2 Teacher & Counselor Login using Employee ID or Email",
      "1.3 Keyboard Progression (Enter on Email/LRN moves to Password; Enter on Password submits)",
      "1.4 Google Sign-In with Official Google Button & Shopee-Style Account Chooser",
      "1.5 Passwordless Email Verification (Delivers 6-digit code to specific Gmail/DepEd email before registration)",
      "1.6 2FA / TOTP Setup with 0ms Instant Vector QR Code & Formatted Manual Setup Key (1-click copy)",
      "1.7 2FA 6-digit Code Verification during login with Authenticator app (Google Auth, Microsoft Auth, Authy)",
      "1.8 Brute-Force Defense (Failed attempt counter, temporary lockout, and live countdown timer)",
      "1.9 First-Time Onboarding Wizard (Student LRN/Grade/Section, Emergency Contact, Security Questions, Avatar)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 1: Comments, Observations & Issues Found (Optional)")
    .setHelpText("Note down any specific bugs, usability issues, or remarks regarding Authentication & 2FA.");

  // =========================================================================================
  // MODULE 2: STUDENT CONCERN & INCIDENT REPORTING
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 2: Student Concern & Incident Reporting Module")
    .setHelpText("Evaluation of concern submission, anti-spam validation, profanity filtering, anonymous token, peer report, teacher shield, witness limits, and evidence upload.");

  form.addGridItem()
    .setTitle("Module 2 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "2.1 Concern Intake Form with 7 Categories (Bullying, Academic, Emotional, Family, Peer, Behavioral, Other)",
      "2.2 Anti-Spam & Content Quality Validation (Enforces min 30 words, max 400 words, rejects random spam/gibberish)",
      "2.3 Automated Profanity & Inappropriate Language Screening across Subject, Description, and Witnesses",
      "2.4 Anonymous Reporting Option (Hides student name, creates tracking token, masks identity from counselor)",
      "2.5 Peer Reporting ('Report for a Friend' with target classmate name and grade details)",
      "2.6 Adviser / Teacher Protection Shield (Hides incidents involving teachers from advisory teacher portals)",
      "2.7 Witness Field Limit (Enforces max 100 characters and max 15 words with live dual counters)",
      "2.8 Multi-Media Evidence Uploader (Drag & drop, file picker, Ctrl+V paste for JPG, PNG, WEBP, GIF, MP4, MOV up to 100MB)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 2: Comments, Observations & Issues Found (Optional)")
    .setHelpText("Note down any remarks or issues regarding incident submission, evidence upload, or validation filters.");

  // =========================================================================================
  // MODULE 3: STUDENT DASHBOARD & CASE TRACKING
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 3: Student Dashboard & Case Tracking Module")
    .setHelpText("Evaluation of the student portal dashboard, active cases tracking, mobile empty states, session reminders, and case appeals.");

  form.addGridItem()
    .setTitle("Module 3 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "3.1 Real-Time KPI Metric Cards (Total Cases, Pending Review, Resolved Cases, Unread Notifications)",
      "3.2 Responsive Active Cases Table with mobile-scaled empty state typography (.empty-table-cell)",
      "3.3 Upcoming Counseling Session Alert Card (Displays counselor name, date, time, and office location)",
      "3.4 Detailed Case Modal (Full concern details, assigned counselor, status badge, triage priority)",
      "3.5 Student Case Archive & Appeals Workflow (Submitting formal appeal statement for closed cases)",
      "3.6 Student Sessions History & Concluded Session Attendance Overview"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 3: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 4: GUIDANCE MESSAGING & REAL-TIME CHAT
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 4: Guidance Messaging & Real-Time Chat Module")
    .setHelpText("Evaluation of two-way private guidance chat, anonymous masking, real-time polling, and Messenger-style 'Seen' read receipts.");

  form.addGridItem()
    .setTitle("Module 4 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "4.1 Two-Way Private Chat linked to specific Case IDs (#C-0001, etc.)",
      "4.2 Anonymous Chat Masking (Student identity masked as 'Anonymous Student' when enabled)",
      "4.3 Optimistic Message Rendering (Sent bubble appears immediately with delivery indicator)",
      "4.4 Auto-Sync Polling (Receives new incoming messages within 3.5 seconds without page reload)",
      "4.5 Messenger-Style 'Sent' Status (Unread sent message displays ✓ Sent status)",
      "4.6 Messenger-Style 'Seen' Status (Displays ✓✓ Seen [timestamp] on the last message opened by recipient)",
      "4.7 Chat Archive Transcript Viewer for concluded and resolved cases"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 4: Comments, Observations & Issues Found (Optional)")
    .setHelpText("Note down remarks regarding chat delivery, read receipts, and real-time syncing.");

  // =========================================================================================
  // MODULE 5: FACULTY REFERRAL & ADVISORY OVERSIGHT (TEACHER PORTAL)
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 5: Faculty Referral & Advisory Oversight Module (Teacher Portal)")
    .setHelpText("Evaluation of faculty referral submission, teacher dashboard, referral collaboration chat, and advisory class oversight.");

  form.addGridItem()
    .setTitle("Module 5 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "5.1 Teacher Dashboard Referral Metrics & Active Referrals Feed",
      "5.2 Submit Student Referral (Student name, LRN, behavioral observations, prior interventions)",
      "5.3 Referral Urgency Prioritization (Low, Medium, High)",
      "5.4 Teacher-Counselor Collaboration Chat with Messenger-style 'Seen' & 'Sent' receipts",
      "5.5 Advisory Section Roster & High-Level Guidance Concern Flags (Confidentiality-compliant)",
      "5.6 Teacher Protection Shield Enforcement (Teacher cannot view cases filed against themselves)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 5: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 6: GUIDANCE ADMINISTRATION & CASE MANAGEMENT (COUNSELOR PORTAL)
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 6: Guidance Administration & Case Management Module")
    .setHelpText("Evaluation of counselor dashboard, master case management, triage, lifecycle progression, appeals review, and follow-ups.");

  form.addGridItem()
    .setTitle("Module 6 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "6.1 Counselor Central Dashboard (Master KPI counters, incident intake feed, urgent referral alerts)",
      "6.2 Master Case Management (Search, filters by status, priority, category, date range)",
      "6.3 Counselor Case Assignment & Priority Triage (Low, Medium, High, Urgent)",
      "6.4 Case Lifecycle Progression (Pending -> Under Review -> Ongoing -> Concluded -> Resolved -> Reopened)",
      "6.5 Student Appeal Review Desk (Counselor reviews appeal justification and reopens case if warranted)",
      "6.6 Master Case Archive with historical resolution timeline, evidence attachments, and counselor notes",
      "6.7 Follow-up Care Tracking (Post-counseling check-ins, monitoring intervals, behavioral progress)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 6: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 7: COUNSELING SESSIONS & IN-SESSION MANAGEMENT
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 7: Counseling Sessions & In-Session Management Module")
    .setHelpText("Evaluation of session scheduling, live in-session stopwatch timer, counselor clinical notes, AI resolution assistant, and session archive.");

  form.addGridItem()
    .setTitle("Module 7 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "7.1 Counseling Session Scheduler (Links session to Case/Referral with date, time, venue, participants)",
      "7.2 Upcoming Sessions Calendar Feed with Live Countdowns and Status Badges",
      "7.3 Live In-Session Controller with Real-Time Stopwatch/Timer (startSessionTimer)",
      "7.4 Counselor Clinical Session Notes & Student Agreement Action Plan Documentation",
      "7.5 AI Resolution Suggestion Assistant (Contextual AI suggestions for DepEd-aligned interventions)",
      "7.6 Concluded Session Archive with Attendance Logs, Duration, and Resolution Outcomes"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 7: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 8: EVIDENCE VAULT & SECURE MEDIA LIGHTBOX
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 8: Evidence Vault & Secure Media Lightbox Module")
    .setHelpText("Evaluation of evidence vault storage, in-browser media preview, video playback, and access isolation.");

  form.addGridItem()
    .setTitle("Module 8 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "8.1 Secure Evidence Vault File Storage & MIME-type Sanitization",
      "8.2 In-Browser Responsive Lightbox (Zoom, pan, and responsive video player)",
      "8.3 Large File Support (Verified handling of photo evidence and video clips up to 100MB)",
      "8.4 Strict Role-Based Evidence Isolation (Restricted exclusively to counselors; hidden from teachers & peers)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 8: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 9: ANALYTICS, INSIGHTS & VISUAL REPORTING
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 9: Analytics, Insights & Visual Reporting Module")
    .setHelpText("Evaluation of statistical charts, trend graphs, resolution rate tracking, and academic term filtering.");

  form.addGridItem()
    .setTitle("Module 9 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "9.1 Concern Category Distribution Chart (Bullying, Academic Stress, Emotional, Family, etc.)",
      "9.2 Longitudinal Incident Trend Analysis (Monthly and quarterly case volume tracking)",
      "9.3 Resolution Metrics (Average resolution turnaround time and resolution success rates)",
      "9.4 Priority & Urgency Distribution Breakdown",
      "9.5 Grade Level & Advisory Section Incident Heatmap",
      "9.6 Academic Year, Semester, and Custom Date Range Filters"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 9: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 10: USER ADMINISTRATION & DIRECTORY MANAGEMENT
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 10: User Administration & Directory Management Module")
    .setHelpText("Evaluation of master accounts table, search/filter, profile modification, advisory assignments, and password reset.");

  form.addGridItem()
    .setTitle("Module 10 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "10.1 Master User Accounts Table (Students, Teachers, Guidance Counselors, Staff)",
      "10.2 Search & Filtering (Search by Name, Email, LRN, Employee ID; filter by Role & Section)",
      "10.3 User Profile Editing (Update Grade, Section, Role, Contact Information, Account Status)",
      "10.4 Teacher Advisory Class Assignment & Section Reassignment",
      "10.5 Administrator Password Reset with Temporary Password Generation"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 10: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // MODULE 11: SECURITY, RECOVERY, SYSTEM TIME & UX INFRASTRUCTURE
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Module 11: Security, Recovery, System Time & UX Infrastructure Module")
    .setHelpText("Evaluation of self-service resets, manual reset tickets, 2FA emergency resets, Philippine Standard Time sync, notifications, theme toggles, and mobile responsiveness.");

  form.addGridItem()
    .setTitle("Module 11 Functionality Checklist")
    .setColumns(testColumns)
    .setRows([
      "11.1 Self-Service Email Password Reset with 10-Minute Expiring Tokens",
      "11.2 Manual Password Reset Request System (ID verification for students without email access)",
      "11.3 Student Ticket Status Checker (Real-time tracking of reset request progress)",
      "11.4 2FA Emergency Reset Ticket System (Identity verification via security questions + Admin TOTP reset)",
      "11.5 System Time Accuracy (Philippine Standard Time UTC+8 synchronized across PHP, MySQL, and chats)",
      "11.6 Cross-Browser Datetime Parsing (Safari, iOS WebKit, Chrome, Firefox, Edge)",
      "11.7 Real-Time In-App Notifications (Notification bell with live unread counter badges)",
      "11.8 Theme Engine (Seamless Light Mode and Dark Mode toggling with persistent user preference)",
      "11.9 Mobile Responsive Navigation (Collapsible drawer menu, responsive headers, touch-friendly UI)",
      "11.10 Interactive Guided Tour (Interactive walkthrough of system features for first-time users)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 11: Comments, Observations & Issues Found (Optional)");

  // =========================================================================================
  // SECTION 12: OVERALL SYSTEM EVALUATION & DEPLOYMENT SIGN-OFF
  // =========================================================================================
  form.addPageBreakItem()
    .setTitle("Overall System Evaluation & Deployment Sign-Off")
    .setHelpText("Final rating and deployment readiness recommendation for the GNHS Guidance System.");

  form.addScaleItem()
    .setTitle("12.1 Overall System Usability & User Experience (UX)")
    .setBounds(1, 5)
    .setLabels("1 = Difficult to Navigate", "5 = Highly Intuitive & User-Friendly")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.2 Overall System Speed & Performance")
    .setBounds(1, 5)
    .setLabels("1 = Slow / Laggy", "5 = Fast & Instantaneous")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.3 Mobile Responsiveness & Phone Experience")
    .setBounds(1, 5)
    .setLabels("1 = Poor Mobile Layout", "5 = Excellent Mobile Adaptation")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("12.4 Security & Data Privacy Implementation")
    .setBounds(1, 5)
    .setLabels("1 = Concerns Observed", "5 = Highly Secure & DepEd Compliant")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("12.5 Production Deployment Recommendation")
    .setChoiceValues([
      "Ready for Full Production Deployment (No critical issues found)",
      "Ready for Deployment with Minor Recommended Polish",
      "Requires Bug Fixes & Follow-up Retesting",
      "Not Ready for Production Deployment"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("12.6 Critical Bugs, Edge Cases, or Defect Reports")
    .setHelpText("Describe any critical errors, steps to reproduce, or blocking issues identified during testing.");

  form.addParagraphTextItem()
    .setTitle("12.7 General Recommendations, Panel Feedback & Commendations")
    .setHelpText("Provide any general comments, suggestions for future enhancements, or overall panel remarks.");

  // Output URLs in the execution log
  Logger.log("=================================================================================");
  Logger.log("GOOGLE FORM SUCCESSFULLY CREATED!");
  Logger.log("Edit URL (Form Builder): " + form.getEditUrl());
  Logger.log("Shareable Fill-in URL (for Testers): " + form.getPublishedUrl());
  Logger.log("=================================================================================");
}
