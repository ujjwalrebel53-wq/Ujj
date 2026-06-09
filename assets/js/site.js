(function () {
  const openBtn = document.getElementById('menu-open');
  const closeBtn = document.getElementById('menu-close');
  const drawer = document.getElementById('mobile-drawer');
  const overlay = document.getElementById('mobile-overlay');

  function setMenu(on) {
    drawer?.classList.toggle('open', on);
    overlay?.classList.toggle('open', on);
    document.body.style.overflow = on ? 'hidden' : '';
  }
  openBtn?.addEventListener('click', () => setMenu(true));
  closeBtn?.addEventListener('click', () => setMenu(false));
  overlay?.addEventListener('click', () => setMenu(false));

  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const text = btn.getAttribute('data-copy') || '';
      navigator.clipboard.writeText(text).then(() => {
        btn.classList.add('text-[#10b981]');
        setTimeout(() => btn.classList.remove('text-[#10b981]'), 1500);
      });
    });
  });

  let lastY = window.scrollY;
  const header = document.getElementById('site-header');
  window.addEventListener('scroll', () => {
    if (!header) return;
    const y = window.scrollY;
    if (y > lastY && y > 120) header.style.transform = 'translate(-50%, -150%)';
    else header.style.transform = 'translate(-50%, 0)';
    lastY = y;
  });
})();
