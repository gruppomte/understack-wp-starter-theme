import Lenis from 'lenis';

/**
 * Smooth scrolling with Lenis. app.js loads this module only on devices with a
 * fine pointer that allow motion: touch devices keep the native scrolling (Lenis
 * does not smooth touch by default, and native already feels right there), and
 * so do visitors with prefers-reduced-motion. Opt out per project with the body
 * class "no-smooth-scroll".
 *
 * Lenis runs on the native scroll, so position: sticky, keyboard scrolling,
 * focus management and the browser's own anchor handling keep working:
 *  - in-page links (including the skip link) are animated by Lenis while the
 *    browser still updates the hash, :target and the focus; the offset follows
 *    the scroll-padding-top declared in base.css
 *  - nested scrollers (code blocks, wide tables, menus) scroll natively
 *  - the page stops while a modal dialog is open (base.css also locks the body)
 *  - a click on a link to another page stops the inertia
 *
 * The instance is exposed as window.lenis and announced with a "lenis:ready"
 * event so the animations module can keep ScrollTrigger in sync.
 */
const MODAL = 'dialog[data-modal][open]';

const scrollPadding = () =>
  Number.parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;

/**
 * Stops the page while a modal is open and resumes it when every modal is closed.
 */
function followModals(lenis) {
  let stopped = false;

  const update = () => {
    const open = document.querySelector(MODAL) !== null;

    if (open !== stopped) {
      stopped = open;
      open ? lenis.stop() : lenis.start();
    }
  };

  new MutationObserver(update).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['open'],
    subtree: true,
  });
  update();
}

export function init() {
  if (window.lenis) {
    return window.lenis;
  }

  const lenis = new Lenis({
    autoRaf: true,
    allowNestedScroll: true,
    stopInertiaOnNavigate: true,
    anchors: { offset: -scrollPadding() },
    // Wheel events over an open dialog never move the page behind it.
    prevent: (node) => node.closest(MODAL) !== null,
  });

  window.lenis = lenis;
  followModals(lenis);
  document.dispatchEvent(new CustomEvent('lenis:ready', { detail: lenis }));

  return lenis;
}
