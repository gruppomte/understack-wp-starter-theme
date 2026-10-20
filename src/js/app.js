import { init as initNavigation } from './modules/navigation.js';

initNavigation();

/**
 * Feature modules are loaded only when the page contains markup that needs them
 * (selector) and, when given, the environment fits (when). Each module exports an
 * init() function.
 */
const features = [
  {
    // Smooth scrolling (Lenis) for fine-pointer devices that allow motion; touch devices
    // keep native scrolling. Opt out per project with the body class "no-smooth-scroll".
    selector: 'body:not(.no-smooth-scroll)',
    when: () =>
      matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)')
        .matches,
    load: () => import('./modules/scroll.js'),
  },
  {
    // Alpine.js for small declarative UI state: loaded when a component is present.
    selector: '[x-data]',
    load: () => import('./modules/alpine.js'),
  },
  {
    selector: 'dialog[data-modal]',
    load: () => import('./modules/modal.js'),
  },
  {
    selector: '[data-animate], [data-animate-stagger], [data-hero], [data-parallax]',
    load: () => import('./animations/index.js'),
  },
];

for (const feature of features) {
  if (!document.querySelector(feature.selector) || (feature.when && !feature.when())) {
    continue;
  }

  feature
    .load()
    .then((module) => module.init())
    .catch((error) => console.error(error));
}
