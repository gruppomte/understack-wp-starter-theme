/**
 * Native dialog based modals.
 *
 * Open:  any element with data-modal-open="{dialog id}"
 * Close: any element inside the dialog with data-modal-close, Esc, or a click
 *        on the backdrop (closedby="any", emulated where unsupported).
 * Focus trapping, inert background and focus return are handled by the browser.
 */
const supportsClosedBy = 'closedBy' in HTMLDialogElement.prototype;

function clickedBackdrop(dialog, event) {
  const rect = dialog.getBoundingClientRect();

  return (
    event.clientY < rect.top ||
    event.clientY > rect.top + rect.height ||
    event.clientX < rect.left ||
    event.clientX > rect.left + rect.width
  );
}

export function init(root = document) {
  root.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');

    if (opener) {
      const dialog = document.getElementById(opener.dataset.modalOpen);

      if (dialog instanceof HTMLDialogElement && !dialog.open) {
        dialog.showModal();
      }

      return;
    }

    if (event.target.closest('[data-modal-close]')) {
      event.target.closest('dialog')?.close();
    }
  });

  if (supportsClosedBy) {
    return;
  }

  for (const dialog of root.querySelectorAll('dialog[data-modal][closedby="any"]')) {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog && clickedBackdrop(dialog, event)) {
        dialog.close();
      }
    });
  }
}
