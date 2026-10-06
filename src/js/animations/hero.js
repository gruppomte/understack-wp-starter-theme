import { gsap } from 'gsap';

/**
 * Opt-in entrance for secondary hero elements: data-hero-item inside a data-hero
 * container. Keep the LCP element (the main heading or image) out of it: an
 * entrance starts from opacity 0 and would delay the first meaningful paint.
 */
export function hero(root = document) {
  for (const container of root.querySelectorAll('[data-hero]')) {
    const items = container.querySelectorAll('[data-hero-item]');

    if (!items.length) {
      continue;
    }

    gsap.from(items, { opacity: 0, y: 16, duration: 0.5, stagger: 0.08, clearProps: 'transform' });
  }
}
