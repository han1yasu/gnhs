/**
 * ====================================================================================================
 * GNHS GUIDANCE SYSTEM — STREAMLINED IT PROFESSIONAL EVALUATION FORM (ISO/IEC 25010)
 * ====================================================================================================
 * 
 * STREAMLINED & OPTIMIZED FOR QUICK 5-10 MINUTE EVALUATION:
 * • Reduced from 12 long pages / 72 rows down to 6 Core Functional Pillars (4 items each = 24 items total).
 * • Covers 100% of the system's capabilities without overwhelming the IT Professional / Panelist.
 * • Includes the standardized ISO/IEC 25010 Product Quality Scale & Production Sign-Off.
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
    "ESTIMATED TIME TO COMPLETE: 5 to 8 Minutes\n\n" +
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
    .setHelpText("Please supply your professional background and the test environment used.");

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
  // PILLAR 1: ACCESS CONTROL, AUTHENTICATION & USER ADMINISTRATION
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 1: Authentication, Access Control & User Administration")
    .setHelpText("Evaluates role-based permissions, multi-method logins, multi-factor security, and user directory management.");

  form.addGridItem()
    .setTitle("Pillar 1 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Role-Based Access Control (Student, Teacher, Counselor Isolation)",
      "Multi-Method Login (12-digit LRN/ID, Google Sign-In & Email OTP)",
      "Cybersecurity & 2FA (Vector QR TOTP & Brute-Force Lockout Defense)",
      "Master User Directory & Teacher Advisory Class Binding"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 1: Technical Observations, Anomalies & Defect Logs (Optional)")
    .setHelpText("Specify any observed authentication vulnerabilities, console errors, or logic deviations.");

  // ====================================================================================================
  // PILLAR 2: INCIDENT REPORTING, PRIVACY SHIELDS & EVIDENCE VAULT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 2: Incident Reporting, Privacy Shields & Evidence Vault")
    .setHelpText("Evaluates incident taxonomy, anti-spam word limits, profanity filtering, student privacy, and evidence handling.");

  form.addGridItem()
    .setTitle("Pillar 2 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Structured Concern Taxonomy (7 Predefined Types & Custom 'Other')",
      "Input Validation (30-400 Word Limits & Automated Profanity Filter)",
      "Privacy Protections (Anonymous Masking, Peer Reporting & Adviser Shield)",
      "Evidence Locker (100MB Upload & In-Browser Media Lightbox Viewer)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 2: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // PILLAR 3: GUIDANCE CASE ADMINISTRATION, TRIAGE & ARCHIVAL
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 3: Guidance Case Administration, Triage & Archival")
    .setHelpText("Evaluates counselor triage, multi-filter querying, finite state machine progression, and appeals adjudication.");

  form.addGridItem()
    .setTitle("Pillar 3 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Counselor Dashboard & Real-Time KPI Metric Cards",
      "Multi-Filter Case Search (Status, Priority, Category, Grade & Section)",
      "Finite State Machine Lifecycle (Pending ➔ Under Review ➔ Ongoing ➔ Resolved)",
      "Student Appeal & Reopening Pipeline with Master Case Archival Vault"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 3: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // PILLAR 4: COUNSELING SESSIONS & CLINICAL INTERVENTIONS
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 4: Counseling Sessions & Clinical Interventions")
    .setHelpText("Evaluates session booking, calendar countdowns, active in-session stopwatch, and clinical notes.");

  form.addGridItem()
    .setTitle("Pillar 4 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Session Appointment Booking with Venue, Mode & Calendar Feed",
      "Live In-Session Controller with Real-Time Clinical Stopwatch",
      "Confidential Counselor Clinical Notes & Action Commitments",
      "AI Guidance Resolution & DepEd Intervention Recommendation Assistant"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 4: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // PILLAR 5: REAL-TIME MESSAGING & FACULTY OVERSIGHT
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 5: Real-Time Messaging & Faculty Advisory Oversight")
    .setHelpText("Evaluates private case messaging, seen receipts, teacher referral intake, and advisory section rosters.");

  form.addGridItem()
    .setTitle("Pillar 5 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "1-to-1 Private Asynchronous Case Messaging (Student-Counselor)",
      "Delivery & Read Receipts (Messenger-Style 'Seen' Timestamps)",
      "Faculty Student Referral Submission with Urgency Priority Tagging",
      "Teacher Advisory Section Roster with Confidential Guidance Status Flags"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 5: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // PILLAR 6: ANALYTICS, SECURITY RECOVERY & SYSTEM INFRASTRUCTURE
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Pillar 6: Analytics, Security Recovery & System Infrastructure")
    .setHelpText("Evaluates trend modeling charts, password recovery, Philippine Standard Time sync, and mobile ergonomics.");

  form.addGridItem()
    .setTitle("Pillar 6 Functional Verification Checklist")
    .setColumns(testColumns)
    .setRows([
      "Interactive Analytics Charts (Incident Trends & Grade Heatmaps)",
      "Disaster Recovery (10-Min Token Resets, 2FA Questions & Offline Tickets)",
      "Philippine Standard Time Sync (UTC+8 across PHP & MariaDB)",
      "Responsive Viewport Ergonomics & High-Contrast Theme Persistence"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("Pillar 6: Technical Observations, Anomalies & Defect Logs (Optional)");

  // ====================================================================================================
  // SECTION 7: ISO/IEC 25010 SOFTWARE QUALITY EVALUATION & PRODUCTION SIGN-OFF
  // ====================================================================================================
  form.addPageBreakItem()
    .setTitle("Section 7: ISO/IEC 25010 Software Quality Evaluation & Production Readiness Sign-Off")
    .setHelpText("Standardized Software Product Quality evaluation based on ISO/IEC 25010 international benchmarks.");

  form.addScaleItem()
    .setTitle("7.1 Functional Suitability (Completeness, Correctness & Task Appropriateness)")
    .setBounds(1, 5)
    .setLabels("1 = Non-Compliant / Flawed", "5 = Fully Compliant & Robust")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("7.2 Security & Data Privacy (Confidentiality, Integrity, Non-Repudiation, RBAC)")
    .setBounds(1, 5)
    .setLabels("1 = High Security Risk", "5 = High Security & DepEd Compliant")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("7.3 Performance Efficiency (Turnaround Time, Asynchronous Polling, Resource Efficiency)")
    .setBounds(1, 5)
    .setLabels("1 = Latency / High Overhead", "5 = Fast & Highly Responsive")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("7.4 Usability & User Experience (Learnability, Operability, Accessibility & Error Protection)")
    .setBounds(1, 5)
    .setLabels("1 = Poor Usability", "5 = Highly Intuitive & Ergonomic")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("7.5 Reliability & Fault Tolerance (Input Sanitization, Error Handling & Recovery)")
    .setBounds(1, 5)
    .setLabels("1 = Fragile / Unhandled Exceptions", "5 = Highly Resilient & Fault-Tolerant")
    .setRequired(true);

  form.addScaleItem()
    .setTitle("7.6 Compatibility & Portability (Cross-Browser Engine Parity & Mobile Viewport Adaptation)")
    .setBounds(1, 5)
    .setLabels("1 = Engine Incompatibilities", "5 = Flawless Cross-Platform Adaptation")
    .setRequired(true);

  form.addMultipleChoiceItem()
    .setTitle("7.7 Overall Production Deployment Recommendation")
    .setChoiceValues([
      "Approved for Immediate Production Deployment (No Blocking Defects)",
      "Approved with Minor Recommended Polish (Cosmetic / Non-Functional Enhancements)",
      "Conditional Approval (Requires Retesting after Specific Defect Fixes)",
      "Rejected (Critical Architectural, Security, or Functional Defects Encountered)"
    ])
    .setRequired(true);

  form.addParagraphTextItem()
    .setTitle("7.8 Defect & Anomaly Logging (Technical Bugs / Edge Cases Identified)")
    .setHelpText("Provide steps to reproduce, HTTP status codes, device/browser details, and expected vs. actual outcomes for any discovered bugs.");

  form.addParagraphTextItem()
    .setTitle("7.9 Architectural Observations, Technical Commendations & Panel Remarks")
    .setHelpText("Provide technical assessment feedback regarding the system's software architecture, security mechanisms, or capstone defense sign-off.");

  // Output URLs in execution log
  Logger.log("=================================================================================");
  Logger.log("STREAMLINED IT PROFESSIONAL EVALUATION FORM (ISO/IEC 25010) GENERATED SUCCESSFULLY!");
  Logger.log("Edit URL (Form Builder): " + form.getEditUrl());
  Logger.log("Shareable Fill-in URL (for IT Evaluators): " + form.getPublishedUrl());
  Logger.log("=================================================================================");
}
