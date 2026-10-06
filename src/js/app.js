import { init as initNavigation } from './modules/navigation.js';

initNavigation();

/**
 * Feature modules are loaded only when the page contains markup that needs them.
 * Each module exports an init() function.
 */
const features = [
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
  if (!document.querySelector(feature.selector)) {
    continue;
  }

  feature
    .load()
    .then((module) => module.init())
    .catch((error) => console.error(error));
}
