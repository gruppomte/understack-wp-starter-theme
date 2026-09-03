import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { hero } from './hero.js';
import { reveal } from './reveal.js';
import { parallax } from './parallax.js';

gsap.registerPlugin(ScrollTrigger);
gsap.defaults({ ease: 'power2.out', duration: 0.6 });

/**
 * Animations run only when the visitor allows motion. With reduced motion the
 * content is simply shown, and everything created here is reverted automatically
 * if the preference changes while the page is open.
 */
export function init() {
  const media = gsap.matchMedia();

  media.add(
    {
      motion: '(prefers-reduced-motion: no-preference)',
      reduce: '(prefers-reduced-motion: reduce)',
    },
    (context) => {
      if (context.conditions.reduce) {
        document.documentElement.classList.remove('motion');
        return;
      }

      hero();
      reveal();
      parallax();
    },
  );

  return media;
}
