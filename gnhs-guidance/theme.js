// ── THEME ENGINE (Dark & Light Mode Only) ──
function initTheme() {
  const savedTheme = localStorage.getItem('theme') || 'light';
  const mode = (savedTheme === 'dark') ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', mode);
  // Lock color theme permanently to GNHS Maroon
  document.documentElement.setAttribute('data-color', 'maroon');
  localStorage.setItem('colorTheme', 'maroon');
}

function setTheme(mode) {
  const targetMode = (mode === 'dark') ? 'dark' : 'light';
  document.documentElement.classList.add('theme-transitioning');
  document.documentElement.setAttribute('data-theme', targetMode);
  localStorage.setItem('theme', targetMode);
  updateThemeUI();
  setTimeout(() => {
    document.documentElement.classList.remove('theme-transitioning');
  }, 750);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const next = (current === 'dark') ? 'light' : 'dark';
  setTheme(next);
}

// Backward-compatibility aliases
function setColorMode(color) {
  document.documentElement.setAttribute('data-color', 'maroon');
  localStorage.setItem('colorTheme', 'maroon');
}

function toggleThemeMenu() {
  toggleTheme();
}

function updateThemeUI() {
  const currentTheme = document.documentElement.getAttribute('data-theme');
  const isDark = (currentTheme === 'dark');

  // Update all theme toggle icons (sun when dark mode, moon when light mode)
  document.querySelectorAll('#themeToggleIcon, .theme-toggle-icon').forEach(icon => {
    if (isDark) {
      icon.className = 'fas fa-sun';
      if (icon.parentElement) {
        icon.parentElement.setAttribute('title', 'Switch to Light Mode');
        icon.parentElement.setAttribute('aria-label', 'Switch to Light Mode');
      }
    } else {
      icon.className = 'fas fa-moon';
      if (icon.parentElement) {
        icon.parentElement.setAttribute('title', 'Switch to Dark Mode');
        icon.parentElement.setAttribute('aria-label', 'Switch to Dark Mode');
      }
    }
  });

  const modeBtn = document.getElementById('tpModeToggle');
  if (modeBtn) {
    if (isDark) {
      modeBtn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
    } else {
      modeBtn.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
    }
  }
}

// Ensure theme is set immediately on script run
initTheme();

document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  updateThemeUI();
});
