/**
 * Header navigation: mobile toggle and sub-menu disclosures, no dependencies.
 *
 * Markup contract (partials/header.twig and partials/navigation.twig):
 *  - [data-navigation]      the header
 *  - [data-nav-toggle]      button with aria-controls -> navigation panel
 *  - [data-nav-icon]        "open" / "closed" icons inside that button
 *  - [data-submenu-toggle]  button with aria-controls -> sub-menu list
 *
 * Panels open by removing the "hidden" class. Escape and clicks outside the
 * header close everything; focus returns to the control that owns the panel.
 */
const TOGGLES = '[data-nav-toggle], [data-submenu-toggle]';

const isExpanded = (button) => button.getAttribute('aria-expanded') === 'true';

const panelOf = (button) => document.getElementById(button.getAttribute('aria-controls') ?? '');

function setExpanded(button, expanded) {
  const panel = panelOf(button);

  if (!panel) {
    return;
  }

  button.setAttribute('aria-expanded', String(expanded));
  panel.classList.toggle('hidden', !expanded);

  for (const icon of button.querySelectorAll('[data-nav-icon]')) {
    icon.classList.toggle('hidden', (icon.dataset.navIcon === 'open') !== expanded);
  }
}

export function init(root = document) {
  const header = root.querySelector('[data-navigation]');
  const toggles = header ? [...header.querySelectorAll(TOGGLES)] : [];

  if (toggles.length === 0) {
    return;
  }

  const closeAll = () => {
    for (const button of toggles) {
      if (isExpanded(button)) {
        setExpanded(button, false);
      }
    }
  };

  header.addEventListener('click', (event) => {
    const button = event.target.closest(TOGGLES);

    if (!button) {
      return;
    }

    const open = !isExpanded(button);

    if (open && button.hasAttribute('data-submenu-toggle')) {
      for (const other of toggles) {
        if (other !== button && other.hasAttribute('data-submenu-toggle')) {
          setExpanded(other, false);
        }
      }
    }

    setExpanded(button, open);
  });

  document.addEventListener('click', (event) => {
    if (!header.contains(event.target)) {
      closeAll();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !toggles.some(isExpanded)) {
      return;
    }

    // Innermost open panel that contains the focus: its toggle receives focus back.
    const owner = [...toggles]
      .reverse()
      .find((button) => isExpanded(button) && panelOf(button)?.contains(document.activeElement));

    closeAll();
    owner?.focus();
  });
}
