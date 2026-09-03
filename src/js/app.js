import './modules/alpine.js';
import { init as initNavigation } from './modules/navigation.js';

initNavigation();

/**
 * Feature modules are loaded only when the page contains markup that needs them.
 * Each module exports an init() function.
 */
const features = [
  {
    selector: 'dialog[data-modal]',
    load: () => import('./modules/modal.js'),
  },
  {
    selector: '[data-animate], [data-animate-stagger], [data-hero], [data-parallax]',
    load: () => import('./animations/index.js'),
    // Never leave content hidden if the animation module fails to load.
    onError: () => document.documentElement.classList.remove('motion'),
  },
];

for (const feature of features) {
  if (!document.querySelector(feature.selector)) {
    continue;
  }

  feature
    .load()
    .then((module) => module.init())
    .catch((error) => {
      feature.onError?.();
      console.error(error);
    });
}
