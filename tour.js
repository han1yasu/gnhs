/**
 * GNHS Guidance System — Interactive System Walkthrough Tour
 * Supports: Student, Teacher, and Guidance Admin roles.
 * Features:
 *  - Spotlight & glowing halo highlighting tabs and functions
 *  - Step-by-step informative walkthrough card
 *  - Press "Enter" to advance, "Esc" to skip
 *  - Smooth scrolling and smart screen-edge clamping
 *  - Full Dark Mode and Light Mode styling
 */

(function () {
  'use strict';

  const TOUR_STEPS = {
    student: [
      {
        target: '#topbarBrand',
        title: 'GNHS Guidance Portal',
        icon: 'fa-graduation-cap',
        desc: 'Welcome to your official school guidance portal! Here you can submit confidential guidance concerns, communicate with counselors, and track appointment schedules.'
      },
      {
        target: '#sb-link-dashboard',
        title: 'Student Dashboard',
        icon: 'fa-home',
        desc: 'Your central hub. Check quick status counters of your pending concerns, upcoming sessions, and recent counselor updates at a single glance.'
      },
      {
        target: '#sb-link-submit',
        title: 'Submit Guidance Concern',
        icon: 'fa-file-medical',
        desc: 'Report concerns confidentially (bullying, academic stress, emotional well-being, family, behavioral). You can report anonymously or under your name, attach video/photo evidence, and list witnesses.'
      },
      {
        target: '#sb-link-cases',
        title: 'My Cases & Appeals',
        icon: 'fa-folder-open',
        desc: 'Monitor real-time case progress from "Under Review" to "Resolved". Inspect counselor case notes, view uploaded evidence, and file follow-up appeals if an issue reoccurs.'
      },
      {
        target: '#sb-link-chats',
        title: 'Cases Chat Correspondence',
        icon: 'fa-comments',
        desc: 'Direct, private messaging channel with the guidance counselor assigned to your case for confidential discussions.'
      },
      {
        target: '#sb-link-archive',
        title: 'Case Archive',
        icon: 'fa-archive',
        desc: 'Browse and search through your past resolved guidance cases and official closing notes whenever you need reference.'
      },
      {
        target: '#sb-link-chat_archive',
        title: 'Chat Archive',
        icon: 'fa-history',
        desc: 'Access saved historical messaging records with guidance counselors from previously completed cases.'
      },
      {
        target: '#sb-link-sessions',
        title: 'My Counseling Sessions',
        icon: 'fa-calendar-alt',
        desc: 'View upcoming scheduled one-on-one appointments with date, time, designated counselor, and meeting place (Guidance Office or Virtual Meet).'
      },
      {
        target: '#sb-link-sessions_archive',
        title: 'Sessions Archive',
        icon: 'fa-calendar-check',
        desc: 'Review summaries and action plans from completed counseling sessions to continue your personal growth.'
      },
      {
        target: '#themeContainerBtn',
        title: 'Theme & Dark Mode Switcher',
        icon: 'fa-palette',
        desc: 'Toggle between comfortable Dark Mode and Light Mode, and personalize the system with your preferred school color theme (Maroon, Blue, Emerald, Violet).'
      },
      {
        target: '#topbarNotifBtn',
        title: 'Live Notifications Bell',
        icon: 'fa-bell',
        desc: 'Instant alerts notify you whenever a counselor responds to your concern, updates case status, or books a counseling appointment.'
      },
      {
        target: '#topbarUserBtn',
        title: 'Profile & Account Settings',
        icon: 'fa-user-circle',
        desc: 'View your student profile, upload your avatar photo, manage security credentials, or relaunch this walkthrough guide whenever you need a refresher!'
      }
    ],

    teacher: [
      {
        target: '#topbarBrand',
        title: 'Teacher Guidance Desk',
        icon: 'fa-chalkboard-teacher',
        desc: 'Welcome to the faculty portal. Collaborate with the Guidance Office to support student well-being, initiate early interventions, and monitor advisory classes.'
      },
      {
        target: '#sb-link-dashboard',
        title: 'Teacher Dashboard',
        icon: 'fa-home',
        desc: 'Overview of students you have referred, active counseling interventions, and urgent alerts from the guidance department.'
      },
      {
        target: '#sb-link-advisory',
        title: 'Advisory Class Wellness',
        icon: 'fa-users',
        desc: 'Class roster overview of your designated advisory section with student wellness status indicators and monitoring flags.'
      },
      {
        target: '#sb-link-referral',
        title: 'Submit Student Referral',
        icon: 'fa-user-plus',
        desc: 'Refer students needing guidance counseling by LRN or name. Specify urgency levels (Low to Urgent) and attach confidential teacher observations.'
      },
      {
        target: '#sb-link-referrals',
        title: 'My Referrals Tracker',
        icon: 'fa-list-alt',
        desc: 'Monitor the status of your referred students through the counseling pipeline and review counselor recommendations and classroom accommodations.'
      },
      {
        target: '#sb-link-referrals_chat',
        title: 'Faculty Referrals Chat',
        icon: 'fa-comments',
        desc: 'Direct communication line with guidance counselors to discuss referred student progress and case coordination.'
      },
      {
        target: '#sb-link-referrals_archive',
        title: 'Referrals Archive',
        icon: 'fa-archive',
        desc: 'Searchable historical archive of past referrals, intervention logs, and resolution outcomes across school terms.'
      },
      {
        target: '#themeContainerBtn',
        title: 'Theme & Dark Mode Switcher',
        icon: 'fa-palette',
        desc: 'Switch between Dark Mode and Light Mode, and customize your accent theme for optimal viewing comfort.'
      },
      {
        target: '#topbarNotifBtn',
        title: 'Notifications Center',
        icon: 'fa-bell',
        desc: 'Receive immediate notifications when the Guidance Office acknowledges your referral or schedules an intake session.'
      },
      {
        target: '#topbarUserBtn',
        title: 'Teacher Profile & Settings',
        icon: 'fa-user-circle',
        desc: 'Update your faculty profile, manage security settings, change password, or restart this walkthrough guide anytime!'
      }
    ],

    admin: [
      {
        target: '#topbarBrand',
        title: 'Guidance Administration Desk',
        icon: 'fa-user-tie',
        desc: 'The central administration hub for student cases, emergency intake, counseling appointments, faculty referrals, and school user security.'
      },
      {
        target: '#sb-link-dashboard',
        title: 'Executive Dashboard',
        icon: 'fa-home',
        desc: 'High-level real-time KPI counters: Total Cases, Active Sessions, Pending Teacher Referrals, and Emergency Urgent Alerts requiring triage.'
      },
      {
        target: '#sb-link-cases',
        title: 'All Cases Management',
        icon: 'fa-folder-open',
        desc: 'Comprehensive case review queue. Open full case drawers, view student video/photo evidence in high-resolution lightbox, and update case progress.'
      },
      {
        target: '#sb-link-chats',
        title: 'Cases Chat Correspondence',
        icon: 'fa-comments',
        desc: 'Direct two-way messaging with students regarding submitted concerns and counseling follow-up details.'
      },
      {
        target: '#sb-link-followups',
        title: 'Follow-up Appeals Hub',
        icon: 'fa-reply-all',
        desc: 'Dedicated review desk for student appeals on resolved cases. Re-open cases when new concerns arise or finalize closing notes.'
      },
      {
        target: '#sb-link-referrals',
        title: 'Faculty Referrals Queue',
        icon: 'fa-exchange-alt',
        desc: 'Review teacher-submitted referrals, convert them into official guidance cases, and coordinate classroom accommodations.'
      },
      {
        target: '#sb-link-referrals_chat',
        title: 'Referrals Chat Channel',
        icon: 'fa-comments',
        desc: 'Collaborate with referring faculty members in private conversation threads regarding student concerns.'
      },
      {
        target: '#sb-group-archive_group-wrap',
        title: 'Searchable System Archives',
        icon: 'fa-archive',
        desc: 'Comprehensive archives for closed cases, past student chats, teacher referrals history, and concluded counseling appointments.'
      },
      {
        target: '#sb-link-sessions',
        title: 'Counseling Sessions Scheduler',
        icon: 'fa-calendar-alt',
        desc: 'Schedule in-person or virtual counseling appointments with calendar integration, intake notes, behavior contracts, and outcomes.'
      },
      {
        target: '#sb-link-analytics',
        title: 'Analytics & DepEd Reports',
        icon: 'fa-chart-bar',
        desc: 'View analytical charts on concern distributions, priority breakdowns, monthly resolution rates, and export DepEd compliance summaries.'
      },
      {
        target: '#sb-link-users',
        title: 'User Accounts & Walk-in Registration',
        icon: 'fa-users-cog',
        desc: 'Manage student, teacher, and counselor accounts. Register walk-in students on the spot with strict 12-digit LRN validation and duplicate prevention.'
      },
      {
        target: '#sb-link-reset_requests',
        title: 'Password Reset Requests',
        icon: 'fa-key',
        desc: 'Review pending password reset requests from students who forgot their security questions, and approve resets with automatic question clearance.'
      },
      {
        target: '#sb-link-2fa_requests',
        title: '2FA Recovery Requests',
        icon: 'fa-shield-alt',
        desc: 'Safely unlink lost authenticator apps for locked-out accounts, allowing users to re-pair their devices on next login.'
      },
      {
        target: '#themeContainerBtn',
        title: 'Theme & Dark Mode Switcher',
        icon: 'fa-palette',
        desc: 'Toggle Dark Mode or Light Mode and customize accent colors across the administration interface.'
      },
      {
        target: '#topbarNotifBtn',
        title: 'Administrative Alerts',
        icon: 'fa-bell',
        desc: 'Real-time alert notifications for new emergency cases, urgent teacher referrals, and student follow-up appeals.'
      },
      {
        target: '#topbarUserBtn',
        title: 'Admin Profile & System Walkthrough',
        icon: 'fa-user-circle',
        desc: 'Access your administrator profile, change credentials, or relaunch this system walkthrough tour anytime!'
      }
    ]
  };

  let currentTourStep = 0;
  let isTourActive = false;
  let activeTourSteps = [];
  let sidebarWasOpenedByTour = false;

  // DOM elements cache
  let overlayEl = null;
  let cutoutRectEl = null;
  let haloEl = null;
  let cardEl = null;

  function ensureTourDOM() {
    if (overlayEl) return;

    // Build the SVG Overlay + Mask + Halo + Card
    const div = document.createElement('div');
    div.id = 'systemTourOverlay';
    div.className = 'system-tour-overlay';
    div.innerHTML = `
      <svg class="system-tour-svg" id="tourSvgRoot">
        <defs>
          <mask id="tourCutoutMask">
            <rect width="100%" height="100%" fill="white" />
            <rect id="tourCutoutRect" x="0" y="0" width="0" height="0" rx="10" ry="10" fill="black" />
          </mask>
        </defs>
        <rect width="100%" height="100%" fill="rgba(12, 8, 8, 0.72)" mask="url(#tourCutoutMask)" style="pointer-events:auto;" />
      </svg>
      <div id="tourHighlightHalo" class="tour-highlight-halo"></div>
      <div id="tourTooltipCard" class="tour-tooltip-card" role="dialog" aria-modal="true">
        <div class="tour-card-header">
          <div class="tour-badge-wrap">
            <span class="tour-role-badge" id="tourRoleBadge">Student Tour</span>
            <span class="tour-step-counter" id="tourStepCounter">Step 1 of 10</span>
          </div>
          <button type="button" class="tour-close-btn" onclick="endSystemTour(true)" title="Skip Tour (Esc)">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="tour-progress-bar">
          <div class="tour-progress-fill" id="tourProgressFill"></div>
        </div>
        <div class="tour-card-body">
          <div class="tour-item-header">
            <div class="tour-item-icon" id="tourItemIcon"><i class="fas fa-info-circle"></i></div>
            <h4 class="tour-item-title" id="tourItemTitle">Title</h4>
          </div>
          <p class="tour-item-desc" id="tourItemDesc">Description goes here...</p>
        </div>
        <div class="tour-card-footer">
          <button type="button" class="tour-btn tour-btn-skip" onclick="endSystemTour(true)" title="Skip Tour (Esc)">
            Skip Tour
          </button>
          <div class="tour-nav-btns">
            <button type="button" class="tour-btn tour-btn-prev" id="tourBtnPrev" onclick="prevTourStep()">
              <i class="fas fa-chevron-left"></i> Back
            </button>
            <button type="button" class="tour-btn tour-btn-next" id="tourBtnNext" onclick="nextTourStep()">
              <span>Next</span> <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </div>
        <div class="tour-kbd-footer">
          <span>Press <kbd>↵ Enter</kbd> for next &nbsp;•&nbsp; <kbd>Esc</kbd> to skip</span>
        </div>
      </div>
    `;

    document.body.appendChild(div);

    overlayEl = div;
    cutoutRectEl = document.getElementById('tourCutoutRect');
    haloEl = document.getElementById('tourHighlightHalo');
    cardEl = document.getElementById('tourTooltipCard');

    // Click on overlay background: advance or stay on card
    div.querySelector('#tourSvgRoot rect[mask]')?.addEventListener('click', function (e) {
      // Don't close abruptly if they just click the dark area; advance to next step
      nextTourStep();
    });
  }

  function getEffectiveRole() {
    if (window.currentUserRole && TOUR_STEPS[window.currentUserRole]) {
      return window.currentUserRole;
    }
    const path = window.location.pathname.toLowerCase();
    if (path.includes('admin-')) return 'admin';
    if (path.includes('teacher-')) return 'teacher';
    return 'student';
  }

  function getRoleLabel(role) {
    if (role === 'admin') return 'Guidance Admin Tour';
    if (role === 'teacher') return 'Teacher Tour';
    return 'Student Tour';
  }

  window.startSystemTour = function (stepIndex = 0) {
    ensureTourDOM();

    const role = getEffectiveRole();
    const rawSteps = TOUR_STEPS[role] || TOUR_STEPS.student;

    // Filter steps to those whose targets exist in DOM
    activeTourSteps = rawSteps.filter(s => {
      const el = document.querySelector(s.target);
      return el !== null;
    });

    if (activeTourSteps.length === 0) {
      if (typeof showToast === 'function') {
        showToast('Walkthrough items are not available on this page.', 'info');
      }
      return;
    }

    isTourActive = true;
    overlayEl.classList.add('active');
    haloEl.classList.add('active');

    // Bind keyboard navigation
    window.addEventListener('keydown', handleTourKeydown, true);
    window.addEventListener('resize', handleTourReposition, { passive: true });
    window.addEventListener('scroll', handleTourReposition, { passive: true });

    renderTourStep(Math.max(0, Math.min(stepIndex, activeTourSteps.length - 1)));
  };

  function renderTourStep(index) {
    if (!isTourActive || index < 0 || index >= activeTourSteps.length) return;

    currentTourStep = index;
    const step = activeTourSteps[index];
    const targetEl = document.querySelector(step.target);

    if (!targetEl) {
      // If target missing, advance
      if (index < activeTourSteps.length - 1) {
        renderTourStep(index + 1);
      } else {
        endSystemTour(false);
      }
      return;
    }

    // Handle mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const isInsideSidebar = targetEl.closest('#sidebar') !== null;
    if (window.innerWidth <= 768) {
      if (isInsideSidebar) {
        if (typeof toggleSidebar === 'function') {
          toggleSidebar(true);
        } else if (sidebar && !sidebar.classList.contains('open')) {
          sidebar.classList.add('open');
        }
        sidebarWasOpenedByTour = true;
      } else {
        // Target is outside sidebar: ALWAYS close the mobile drawer so it doesn't obstruct the screen
        if (typeof toggleSidebar === 'function') {
          toggleSidebar(false);
        } else if (sidebar) {
          sidebar.classList.remove('open');
          const bd = document.getElementById('sidebarBackdrop');
          if (bd) bd.classList.remove('active');
        }
      }
    }

    // Handle collapsed dropdown if target is inside one
    const parentSubmenu = targetEl.closest('.sb-submenu');
    if (parentSubmenu && !parentSubmenu.classList.contains('show')) {
      parentSubmenu.classList.add('show');
      const wrap = parentSubmenu.closest('.sb-dropdown-group');
      if (wrap) wrap.classList.add('open');
    }

    // Scroll target into view
    targetEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });

    // Allow scroll to settle then position overlay and card
    setTimeout(() => {
      repositionTourUI(targetEl, step, index);
    }, 120);
  }

  function repositionTourUI(targetEl, step, index) {
    if (!isTourActive) return;

    const rect = targetEl.getBoundingClientRect();
    const pad = 6;
    const rx = 10;

    // Update SVG Cutout Rect
    if (cutoutRectEl) {
      cutoutRectEl.setAttribute('x', Math.max(0, rect.left - pad));
      cutoutRectEl.setAttribute('y', Math.max(0, rect.top - pad));
      cutoutRectEl.setAttribute('width', rect.width + pad * 2);
      cutoutRectEl.setAttribute('height', rect.height + pad * 2);
      cutoutRectEl.setAttribute('rx', rx);
      cutoutRectEl.setAttribute('ry', rx);
    }

    // Update Halo
    if (haloEl) {
      haloEl.style.left = `${Math.max(0, rect.left - pad)}px`;
      haloEl.style.top = `${Math.max(0, rect.top - pad)}px`;
      haloEl.style.width = `${rect.width + pad * 2}px`;
      haloEl.style.height = `${rect.height + pad * 2}px`;
    }

    // Populate Card Content
    const role = getEffectiveRole();
    document.getElementById('tourRoleBadge').textContent = getRoleLabel(role);
    document.getElementById('tourStepCounter').textContent = `Step ${index + 1} of ${activeTourSteps.length}`;
    
    const pct = Math.round(((index + 1) / activeTourSteps.length) * 100);
    document.getElementById('tourProgressFill').style.width = `${pct}%`;

    document.getElementById('tourItemIcon').innerHTML = `<i class="fas ${step.icon || 'fa-info-circle'}"></i>`;
    document.getElementById('tourItemTitle').textContent = step.title;
    document.getElementById('tourItemDesc').textContent = step.desc;

    // Prev button state
    const prevBtn = document.getElementById('tourBtnPrev');
    if (prevBtn) {
      prevBtn.disabled = (index === 0);
    }

    // Next button state
    const nextBtn = document.getElementById('tourBtnNext');
    if (nextBtn) {
      if (index === activeTourSteps.length - 1) {
        nextBtn.className = 'tour-btn tour-btn-finish';
        nextBtn.innerHTML = `<span>Finish Tour</span> <i class="fas fa-check"></i>`;
      } else {
        nextBtn.className = 'tour-btn tour-btn-next';
        nextBtn.innerHTML = `<span>Next</span> <i class="fas fa-arrow-right"></i>`;
      }
    }

    // Position Tooltip Card Smartly
    positionTooltipCard(rect);
  }

  function positionTooltipCard(targetRect) {
    if (!cardEl) return;

    const viewW = window.innerWidth;
    const viewH = window.innerHeight;
    const margin = 14;
    const cardWidth = Math.min(380, viewW - (margin * 2));
    const cardHeight = cardEl.offsetHeight || 260;

    let left = 0;
    let top = 0;

    // Check if on mobile / narrow screen
    if (viewW <= 640) {
      // Center horizontally on mobile
      left = Math.max(margin, (viewW - cardWidth) / 2);
      if (targetRect.top > viewH / 2) {
        // Target is in lower half: put card above target
        top = Math.max(margin, targetRect.top - cardHeight - margin);
      } else {
        // Target is in upper half: put card below target
        top = Math.min(viewH - cardHeight - margin, targetRect.bottom + margin);
      }
    } else {
      // Desktop positioning:
      // If target is in the sidebar (left side of screen)
      if (targetRect.left < 300) {
        left = targetRect.right + 20;
        top = targetRect.top - 10;
      }
      // If target is in topbar (near the top)
      else if (targetRect.top < 120) {
        top = targetRect.bottom + 16;
        left = targetRect.left - (cardWidth / 2) + (targetRect.width / 2);
      }
      // Fallback
      else {
        left = targetRect.right + 20;
        top = targetRect.top;
      }

      // Clamp horizontally
      if (left + cardWidth > viewW - margin) {
        // Try left side of target
        if (targetRect.left - cardWidth - 20 > margin) {
          left = targetRect.left - cardWidth - 20;
        } else {
          left = viewW - cardWidth - margin;
        }
      }
      if (left < margin) left = margin;

      // Clamp vertically
      if (top + cardHeight > viewH - margin) {
        top = viewH - cardHeight - margin;
      }
      if (top < margin) top = margin;
    }

    cardEl.style.left = `${Math.round(left)}px`;
    cardEl.style.top = `${Math.round(top)}px`;
  }

  window.nextTourStep = function () {
    if (!isTourActive) return;
    if (currentTourStep < activeTourSteps.length - 1) {
      renderTourStep(currentTourStep + 1);
    } else {
      endSystemTour(false);
    }
  };

  window.prevTourStep = function () {
    if (!isTourActive) return;
    if (currentTourStep > 0) {
      renderTourStep(currentTourStep - 1);
    }
  };

  window.endSystemTour = function (wasSkipped = false) {
    if (!isTourActive) return;

    isTourActive = false;
    window.removeEventListener('keydown', handleTourKeydown, true);
    window.removeEventListener('resize', handleTourReposition);
    window.removeEventListener('scroll', handleTourReposition);

    if (overlayEl) overlayEl.classList.remove('active');
    if (haloEl) haloEl.classList.remove('active');

    // Close mobile sidebar if opened by tour
    if (sidebarWasOpenedByTour) {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && window.innerWidth <= 768) {
        sidebar.classList.remove('open');
      }
      sidebarWasOpenedByTour = false;
    }

    const role = getEffectiveRole();
    if (!wasSkipped) {
      try {
        localStorage.setItem(`gnhs_tour_completed_${role}`, 'true');
      } catch (e) {}
      if (typeof showToast === 'function') {
        showToast('Walkthrough complete! You can restart it anytime from your profile.', 'success');
      }
    } else {
      if (typeof showToast === 'function') {
        showToast('Tour dismissed. Access it anytime under your profile menu.', 'info');
      }
    }
  };

  function handleTourKeydown(e) {
    if (!isTourActive) return;

    if (e.key === 'Enter') {
      e.preventDefault();
      e.stopPropagation();
      nextTourStep();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      endSystemTour(true);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      e.stopPropagation();
      nextTourStep();
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      e.stopPropagation();
      prevTourStep();
    }
  }

  let repositionTimer = null;
  function handleTourReposition() {
    if (!isTourActive) return;
    if (repositionTimer) clearTimeout(repositionTimer);
    repositionTimer = setTimeout(() => {
      if (isTourActive && activeTourSteps[currentTourStep]) {
        const step = activeTourSteps[currentTourStep];
        const el = document.querySelector(step.target);
        if (el) repositionTourUI(el, step, currentTourStep);
      }
    }, 60);
  }

})();

