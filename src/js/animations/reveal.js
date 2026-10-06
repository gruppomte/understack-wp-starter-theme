import { gsap } from 'gsap';

const variants = {
  'fade-up': { y: 24 },
  'fade-down': { y: -24 },
  'fade-left': { x: -24 },
  'fade-right': { x: 24 },
  fade: {},
};

const belowFold = (element) => element.getBoundingClientRect().top > window.innerHeight;

/**
 * Scroll reveals for elements that are below the fold when the page loads:
 *  - data-animate="fade-up|fade-down|fade-left|fade-right|fade" on single elements
 *  - data-animate-stagger on a container staggers its direct children
 *
 * Elements already in view are never touched, so the first paint (and the LCP
 * element) is complete. Opacity is animated instead of visibility so content
 * stays in the accessibility tree; receiving focus completes the reveal at once.
 */
function entrance(targets, host, from, extra = {}) {
  const tween = gsap.from(targets, {
    opacity: 0,
    ...from,
    duration: 0.7,
    clearProps: 'transform',
    scrollTrigger: { trigger: host, start: 'top 88%', once: true },
    ...extra,
  });

  host.addEventListener('focusin', () => tween.progress(1), { once: true });
}

export function reveal(root = document) {
  for (const element of root.querySelectorAll('[data-animate]')) {
    if (belowFold(element)) {
      entrance(element, element, variants[element.dataset.animate] ?? variants['fade-up']);
    }
  }

  for (const group of root.querySelectorAll('[data-animate-stagger]')) {
    if (group.children.length && belowFold(group)) {
      entrance([...group.children], group, { y: 24 }, { stagger: 0.08 });
    }
  }
}
