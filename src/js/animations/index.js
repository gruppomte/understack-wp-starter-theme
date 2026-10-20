import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { hero } from './hero.js';
import { reveal } from './reveal.js';
import { parallax } from './parallax.js';

gsap.registerPlugin(ScrollTrigger);
gsap.defaults({ ease: 'power2.out', duration: 0.6 });

/**
 * When Lenis drives the page (modules/scroll.js), ScrollTrigger follows its frames
 * instead of the native scroll event, so scrubbed tweens never lag behind. Both
 * modules load on demand, in either order: the instance is taken when present or
 * when it announces itself.
 */
function syncWithLenis(lenis) {
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.lagSmoothing(0);
}

/**
 * Animations run only when the visitor allows motion; with reduced motion the
 * content is simply left as rendered. Everything created here is reverted
 * automatically if the preference changes while the page is open.
 *
 * Nothing is hidden by CSS before this module runs: the page paints complete,
 * and only elements below the fold are prepared for their entrance.
 */
export function init() {
  if (window.lenis) {
    syncWithLenis(window.lenis);
  } else {
    document.addEventListener('lenis:ready', (event) => syncWithLenis(event.detail), {
      once: true,
    });
  }

  const media = gsap.matchMedia();

  media.add('(prefers-reduced-motion: no-preference)', () => {
    hero();
    reveal();
    parallax();
  });

  return media;
}
