import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { hero } from './hero.js';
import { reveal } from './reveal.js';
import { parallax } from './parallax.js';

gsap.registerPlugin(ScrollTrigger);
gsap.defaults({ ease: 'power2.out', duration: 0.6 });

/**
 * Animations run only when the visitor allows motion; with reduced motion the
 * content is simply left as rendered. Everything created here is reverted
 * automatically if the preference changes while the page is open.
 *
 * Nothing is hidden by CSS before this module runs: the page paints complete,
 * and only elements below the fold are prepared for their entrance.
 */
export function init() {
  const media = gsap.matchMedia();

  media.add('(prefers-reduced-motion: no-preference)', () => {
    hero();
    reveal();
    parallax();
  });

  return media;
}
