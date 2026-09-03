import { gsap } from 'gsap';

/**
 * Subtle scroll-linked parallax: data-parallax="0.5" (relative speed, default 1)
 * moves the element up to 20% of its height while its parent crosses the viewport.
 * Give the parent overflow-hidden and the element some extra height.
 */
export function parallax(root = document) {
  for (const element of root.querySelectorAll('[data-parallax]')) {
    const speed = Number.parseFloat(element.dataset.parallax) || 1;

    gsap.to(element, {
      yPercent: -20 * speed,
      ease: 'none',
      scrollTrigger: {
        trigger: element.parentElement ?? element,
        start: 'top bottom',
        end: 'bottom top',
        scrub: true,
      },
    });
  }
}
