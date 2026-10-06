import Alpine from 'alpinejs';

/**
 * Strict Content Security Policies without 'unsafe-eval' need the CSP build:
 * replace the import with '@alpinejs/csp' (methods instead of inline expressions).
 */
export function init() {
  window.Alpine = Alpine;
  Alpine.start();
}
