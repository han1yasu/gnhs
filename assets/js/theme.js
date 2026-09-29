// ── THEME ENGINE ──
function initTheme() {
  const savedTheme = localStorage.getItem('theme') || 'light';
  const savedColor = localStorage.getItem('colorTheme') || 'maroon';
  document.documentElement.setAttribute('data-theme', savedTheme);
  document.documentElement.setAttribute('data-color', savedColor);
}

function setTheme(mode) {
  document.documentElement.classList.add('theme-transitioning');
  document.documentElement.setAttribute('data-theme', mode);
  localStorage.setItem('theme', mode);
  updateThemeUI();
  setTimeout(() => {
    document.documentElement.classList.remove('theme-transitioning');
  }, 750);
}

function setColorMode(color) {
  document.documentElement.setAttribute('data-color', color);
  localStorage.setItem('colorTheme', color);
  updateThemeUI();
}

function toggleThemeMenu() {
  const panel = document.getElementById('themePanel');
  if (panel) panel.classList.toggle('open');
}

function updateThemeUI() {
  const currentTheme = document.documentElement.getAttribute('data-theme');
  const currentColor = document.documentElement.getAttribute('data-color');
  
  const modeBtn = document.getElementById('tpModeToggle');
  if (modeBtn) {
    if (currentTheme === 'dark') {
      modeBtn.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
    } else {
      modeBtn.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
    }
  }
  
  document.querySelectorAll('.tp-color').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.color === currentColor);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  updateThemeUI();
  
  document.addEventListener('click', e => {
    const panel = document.getElementById('themePanel');
    if (panel && !panel.classList.contains('hidden') && !e.target.closest('.theme-container')) {
      panel.classList.remove('open');
    }
  });
});

