/**
 * ====================================================================================================
 * GNHS GUIDANCE SYSTEM — UPDATED STREAMLINED FUNCTIONALITY & ACCEPTANCE TESTING FORM (2026)
 * ====================================================================================================
 * 
 * DESIGN HIGHLIGHTS:
 * • STREAMLINED FOR FAST COMPLETION: Takes only 3 to 5 minutes to complete.
 * • COMPREHENSIVE COVERAGE: Covers all 8 core modules in 5 clean, compact test grids (20 items total).
 * • UPDATED WITH LATEST CAPABILITIES:
 *   - Direct Google Sign-In with 6-digit email code (bypasses 2FA for verified Google users).
 *   - Dedicated responsive "Add Student" page for teachers & counselors + advisory popup shortcut.
 *   - 2FA / TOTP authentication with centered confirmation modal for lost authenticator requests.
 *   - Strict single-recipient email dispatch for password recovery with live requirements checklist.
 *   - Live clinical session stopwatch and DepEd-aligned intervention recommendations.
 *   - Asynchronous case & referral chats with seen indicators.
 * • NO TRUNCATION: Short row and column labels designed specifically to prevent "..." ellipsis in Google Forms.
 * 
 * TARGET ROLES:
 * • Students, Advisory Teachers, Guidance Counselors, and IT Evaluators / Defense Panelists.
 * • Tested on Live Deployment: https://gnhsguidance.com/
 * 
 * INSTRUCTIONS TO GENERATE FORM:
 * 1. Open Google Apps Script: https://script.google.com/
 * 2. Click "+ New project".
 * 3. Delete any default code in Code.gs and paste this entire script.
 * 4. Click "Save" (Ctrl+S).
 * 5. Make sure "createUpdatedGNHSFunctionalityTestForm" is selected in the function dropdown, then click "Run".
 * 6. Grant permissions if prompted by Google (Allow access).
 * 7. Open the Execution Log (Ctrl+Enter) — your live Form Edit URL and Shareable Fill-in URL will appear!
 * ====================================================================================================
 */

function createUpdatedGNHSFunctionalityTestForm() {
  const formTitle = "GNHS Guidance System — Updated Functionality & Acceptance Test";
  const form = FormApp.create(formTitle);

  form.setDescription(
    "Guinayang National High School (GNHS) Guidance Case Management System\n" +
    "Official System Functionality, Usability & User Acceptance Testing (UAT) Instrument\n\n" +
    "SYSTEM UNDER TEST:\n" +
    "• Production URL: https://gnhsguidance.com/\n" +
    "• Evaluated Roles: Student, Advisory Teacher, Guidance Counselor / Admin\n" +
    "• Supported Devices: Desktop, Laptop, Tablet / iPad, Mobile Smartphone (Android & iOS)\n\n" +
    "TEST INSTRUCTIONS:\n" +
    "Please test the corresponding features on the live system and mark your verdict for each test item below.\n" +
    "Estimated Completion Time: 3 to 5 Minutes\n\n" +
    "VERDICT OPTIONS:\n" +
    "• Passed: Feature works accurately with expected results.\n" +
    "• Minor Polish: Feature functions, but minor UI layout or wording polish is noted.\n" +
    "• Failed (Bug): Feature encountered an error, unexpected behavior, or broken state.\n" +
    "• Not Tested: Feature was not tested during this evaluation."
  );

  form.setConfirmationMessage(
    "Thank you! Your functionality test responses for the GNHS Guidance System have been successfully recorded."
  );
  form.setAllowResponseEdits(true);

  // Short column labels to avoid truncation on desktop and mobile screens
  const testColumns = [
    "Passed",
    "Minor Polish",
    "Failed (Bug)",
    "Not Tested"
  ];

  // ====================================================================================================
  // SECTION 1: EVALUATOR PROFILE & TEST ENVIRONMENT
  // ====================================================================================================
  form.addSectionHeaderItem()
    .setTitle("Section 1: Tester Profile & Environment")
    .setHelpText("Please enter your information and the device/browser used during testing.");

  form.addTextItem()
    .setTitle("1.1 Tester Full Name")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("1.2 User Role Tested")
    .setChoiceValues([
      "Student Evaluator",
      "Advisory Teacher / Faculty Member",
      "Guidance Counselor / Admin",
      "IT Specialist / Capstone Defense Panelist"
    ])
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("1.3 Testing Device(s) Used (Select all applied)")
    .setChoiceValues([
      "Desktop Workstation (Windows / macOS)",
      "Laptop (Windows / macOS)",
      "Mobile Smartphone (Android)",
      "Mobile Smartphone (iPhone / iOS)",
      "Tablet / iPad"
    ])
    .setRequired(true);

  form.addCheckboxItem()
    .setTitle("1.4 Web Browser(s) Used")
    .setChoiceValues([
      "Google Chrome",
      "Safari (iOS / macOS)",
      "Mozilla Firefox",
      "Microsoft Edge",
      "Mobile In-App Browser / WebView"
    ])
    .setRequired(true);

  form.addDateItem()
    .setTitle("1.5 Date of Testing")
    .setRequired(true);

  // ====================================================================================================
  // MODULE 1: AUTHENTICATION, MULTI-FACTOR SECURITY & RECOVERY
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 1: Authentication, Multi-Factor Security & Recovery")
    .setHelpText("Verifies multi-role login, Google sign-in, 2FA/TOTP authenticator, and password recovery.");

  form.addGridItem()
    .setTitle("Module 1 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Standard Login via LRN / Employee ID / Email + Password",
      "Google Sign-In with 6-digit code (direct dashboard entry without 2FA)",
      "2FA / TOTP Authenticator & Centered Lost-2FA Confirmation Modal",
      "Password Reset with Strict Email Delivery & Live Strength Checklist"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 1: Observations or Anomalies (Optional)")
    .setHelpText("Note any issues observed with authentication, verification codes, or layout.");

  // ====================================================================================================
  // MODULE 2: STUDENT INCIDENT REPORTING & CASE TRACKING
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 2: Student Incident Reporting & Case Tracking")
    .setHelpText("Verifies student concern filing, input validation, privacy protections, and case progression.");

  form.addGridItem()
    .setTitle("Module 2 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Concern Submission across 7 Defined Types + Custom 'Other'",
      "Input Safeguards (30-400 Word Count Limit & Profanity Detection)",
      "Privacy Shields (Anonymous Peer Reporting & Adviser Access Toggle)",
      "Case Lifecycle Timeline Tracking (Pending ➔ Under Review ➔ Ongoing ➔ Resolved)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 2: Observations or Anomalies (Optional)")
    .setHelpText("Note any issues observed during case filing, validation, or timeline updates.");

  // ====================================================================================================
  // MODULE 3: FACULTY ADVISORY & STUDENT REFERRAL MANAGEMENT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 3: Faculty Advisory & Student Referral Management")
    .setHelpText("Verifies teacher advisory class management, responsive student enrollment, and referrals.");

  form.addGridItem()
    .setTitle("Module 3 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Advisory Class Roster with Confidential Guidance Status Badges",
      "Dedicated Full-Page Add Student Form (Responsive on Phone/Tablet/PC)",
      "Advisory Quick Add Student Shortcut Popup Modal",
      "Student Referral Submission with Priority Levels (Low, Med, High, Urgent)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 3: Observations or Anomalies (Optional)")
    .setHelpText("Note any issues observed with advisory lists, student creation, or referrals.");

  // ====================================================================================================
  // MODULE 4: COUNSELING SESSIONS, INTERVENTIONS & CASE ADMINISTRATION
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 4: Counseling Sessions, Interventions & Case Administration")
    .setHelpText("Verifies session scheduling, active session stopwatch, clinical notes, and case administration.");

  form.addGridItem()
    .setTitle("Module 4 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Counselor Case Triage with Multi-Filter Querying (Status, Category, Grade)",
      "Session Appointment Booking (In-Person Office / Virtual Google Meet)",
      "Live Active In-Session Mode with Real-Time Clinical Stopwatch Timer",
      "Confidential Clinical Notes, DepEd Recommendations & Case Archiving"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 4: Observations or Anomalies (Optional)")
    .setHelpText("Note any issues observed with session booking, stopwatch, or case management.");

  // ====================================================================================================
  // MODULE 5: REAL-TIME MESSAGING, ANALYTICS & SYSTEM ERGONOMICS
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Module 5: Real-Time Messaging, Analytics & System Ergonomics")
    .setHelpText("Verifies 1-on-1 messaging, read receipts, visual analytics, and cross-device responsiveness.");

  form.addGridItem()
    .setTitle("Module 5 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Student-Counselor & Teacher-Counselor Messaging with 'Seen' Timestamps",
      "Visual Analytics Dashboard (Incident Trend Charts & Grade Level Heatmaps)",
      "Multi-Device Responsive Ergonomics (Mobile Drawer, Tablet & Desktop)",
      "Theme Persistence (High-Contrast Maroon Light Mode & Dark Mode)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Module 5: Observations or Anomalies (Optional)")
    .setHelpText("Note any issues observed with chat delivery, analytics charts, or mobile display.");

  // ====================================================================================================
  // SECTION 6: OVERALL EVALUATION & PRODUCTION ACCEPTANCE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Section 6: Overall System Evaluation & Acceptance Sign-Off")
    .setHelpText("Overall assessment of software quality, usability, and readiness for production deployment.");

  form.addScaleItem()
    .setTitle("6.1 Overall System Functionality & Reliability")
    .setBounds(1, 5)
    .setLabels("1 = Needs Significant Work", "5 = Fully Functional & Reliable")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("6.2 User Experience & Ease of Use (Mobile & Desktop)")
    .setBounds(1, 5)
    .setLabels("1 = Difficult to Navigate", "5 = Highly Intuitive & Ergonomic")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("6.3 System Deployment & Acceptance Verdict")
    .setChoiceValues([
      "Accepted for Production Deployment (Ready for school use)",
      "Accepted with Minor Cosmetic Polish (Non-blocking adjustments)",
      "Conditional Acceptance (Minor fixes needed before deployment)",
      "Requires Major Revision (Critical defects encountered)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("6.4 General Feedback, Commendations, or Suggestions for Improvement")
    .setHelpText("Share your overall feedback or commendations regarding the GNHS Guidance System.");

  // Output generated URLs in the Google Apps Script execution log
  Logger.log("=================================================================================");
  Logger.log("GNHS UPDATED FUNCTIONALITY TEST FORM GENERATED SUCCESSFULLY!");
  Logger.log("Edit Form URL (for form owner): " + form.getEditUrl());
  Logger.log("Shareable Fill-in Form URL (for testers): " + form.getPublishedUrl());
  Logger.log("=================================================================================");
}
