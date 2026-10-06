/**
 * Collects console errors and uncaught exceptions for the lifetime of a page.
 * Returns a function that yields the messages gathered so far.
 */
export function watchErrors(page) {
  const errors = [];

  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
  });
  page.on('pageerror', (error) => errors.push(error.message));

  return () => errors;
}
