(() => {
  const root = document.documentElement;
  const saved = localStorage.getItem('tsm-theme');
  const initial = saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  root.dataset.theme = initial;

  const departmentsButton = () => document.querySelector('.tsm-departments-toggle');
  const departmentsPanel = () => document.getElementById('tsm-departments-panel');

  const closeDepartments = () => {
    const button = departmentsButton();
    const panel = departmentsPanel();
    if (!button || !panel) return;
    button.setAttribute('aria-expanded', 'false');
    panel.classList.remove('is-open');
    panel.hidden = true;
  };

  const openDepartments = () => {
    const button = departmentsButton();
    const panel = departmentsPanel();
    if (!button || !panel) return;
    button.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
    requestAnimationFrame(() => panel.classList.add('is-open'));
  };

  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('#tsm-theme-toggle');
    if (toggle) {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next;
      localStorage.setItem('tsm-theme', next);
      return;
    }

    const menu = e.target.closest('.tsm-departments-toggle');
    if (menu) {
      const open = menu.getAttribute('aria-expanded') === 'true';
      open ? closeDepartments() : openDepartments();
      return;
    }

    const searchTrigger = e.target.closest('[data-tsm-search-focus]');
    if (searchTrigger) {
      e.preventDefault();
      const search = document.getElementById('tsm-product-search');
      const input = search?.querySelector('input[type="search"]');
      search?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.setTimeout(() => input?.focus(), 220);
      return;
    }

    if (!e.target.closest('.tsm-departments-panel') && !e.target.closest('.tsm-departments-toggle')) {
      closeDepartments();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDepartments();
      departmentsButton()?.focus();
    }
  });
})();
