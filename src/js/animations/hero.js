import { gsap } from 'gsap';

/**
 * Page hero: elements marked with data-hero-item inside a data-hero container
 * enter with a short stagger on load. Kept brief so the main heading paints early.
 */
export function hero(root = document) {
  for (const container of root.querySelectorAll('[data-hero]')) {
    const items = container.querySelectorAll('[data-hero-item]');

    if (!items.length) {
      continue;
    }

    gsap.fromTo(
      items,
      { autoAlpha: 0, y: 16 },
      { autoAlpha: 1, y: 0, duration: 0.5, stagger: 0.08, clearProps: 'transform' },
    );
  }
}
