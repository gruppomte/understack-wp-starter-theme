import { gsap } from 'gsap';

const variants = {
  'fade-up': { y: 24 },
  'fade-down': { y: -24 },
  'fade-left': { x: -24 },
  'fade-right': { x: 24 },
  fade: {},
};

const trigger = (element) => ({ trigger: element, start: 'top 88%', once: true });

/**
 * Scroll reveals.
 *  - data-animate="fade-up|fade-down|fade-left|fade-right|fade" on single elements
 *  - data-animate-stagger on a container staggers its direct children
 */
export function reveal(root = document) {
  for (const element of root.querySelectorAll('[data-animate]')) {
    const from = variants[element.dataset.animate] ?? variants['fade-up'];

    gsap.fromTo(
      element,
      { autoAlpha: 0, ...from },
      {
        autoAlpha: 1,
        x: 0,
        y: 0,
        duration: 0.7,
        clearProps: 'transform',
        scrollTrigger: trigger(element),
      },
    );
  }

  for (const group of root.querySelectorAll('[data-animate-stagger]')) {
    const children = [...group.children];

    if (!children.length) {
      continue;
    }

    gsap.fromTo(
      children,
      { autoAlpha: 0, y: 24 },
      {
        autoAlpha: 1,
        y: 0,
        duration: 0.7,
        stagger: 0.08,
        clearProps: 'transform',
        scrollTrigger: trigger(group),
      },
    );
  }
}
