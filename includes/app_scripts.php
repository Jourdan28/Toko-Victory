<?php
/* === includes/app_scripts.php === */
?>
<script>
(function () {
  const root = document.documentElement;
  const themeBtn = document.getElementById('theme-toggle');
  if (themeBtn) {
    const syncIcon = () => {
      const icon = themeBtn.querySelector('i');
      if (icon) {
        icon.className = root.classList.contains('light') ? 'ti ti-moon' : 'ti ti-sun';
      }
    };
    syncIcon();
    themeBtn.addEventListener('click', () => {
      root.classList.toggle('light');
      localStorage.setItem('theme', root.classList.contains('light') ? 'light' : 'dark');
      syncIcon();
    });
  }

  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('mini'));
  }

  document.getElementById('masterToggle')?.addEventListener('click', () => {
    document.getElementById('masterSub')?.classList.toggle('open');
  });

  const btnMenu = document.getElementById('btnMenu');
  const overlay = document.getElementById('sidebarOverlay');
  if (btnMenu && sidebar) {
    btnMenu.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay?.classList.toggle('open');
    });
  }
  overlay?.addEventListener('click', () => {
    sidebar?.classList.remove('open');
    overlay.classList.remove('open');
  });

  const flash = document.getElementById('flashMsg');
  if (flash) {
    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateX(12px)';
      setTimeout(() => flash.remove(), 300);
    }, 4000);
  }
})();

function staggerFadeUp(selector) {
  document.querySelectorAll(selector).forEach((el, i) => {
    el.style.animationDelay = i * 60 + 'ms';
    el.classList.add('animate-fade-up');
  });
}
document.addEventListener('DOMContentLoaded', () => {
  staggerFadeUp('.stat-card, .summary-card, .panel');
});
</script>
