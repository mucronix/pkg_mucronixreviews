/**
 * Mucronix Reviews
 * (C) 2026 Mucronix
 * License GNU General Public License version 2 or later; see LICENSE.txt
 */
(() => {
  'use strict';

  const asJson = (address) => {
    const url = new URL(address, window.location.href);

    url.searchParams.set('format', 'json');

    return url;
  };

  // A button that does nothing without this script is hidden until here
  const reveal = (root) => {
    root.querySelectorAll('.mxr-more[hidden]').forEach((button) => {
      button.hidden = false;
    });
  };

  const tell = (block, message, isError) => {
    const status = block.querySelector('.mxr-status');

    // The classes of the template for a message, those of UIkit for example
    const success = (block.dataset.mxrSuccess || '').split(' ').filter(Boolean);
    const failure = (block.dataset.mxrFailure || '').split(' ').filter(Boolean);

    status.textContent = message;
    status.classList.remove(...success, ...failure);
    status.classList.add(...(isError ? failure : success));
    status.classList.toggle('mxr-status--error', isError);
    status.classList.toggle('mxr-status--success', !isError);
    status.scrollIntoView({ block: 'nearest' });
  };

  const read = async (response) => {
    try {
      return await response.json();
    } catch (e) {
      return null;
    }
  };

  const send = async (block, form) => {
    const button = form.querySelector('[type="submit"]');

    button.disabled = true;

    try {
      const response = await fetch(asJson(form.action), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
      });
      const answer = await read(response);

      if (answer && answer.success) {
        const body = block.querySelector('.mxr-body');

        body.innerHTML = answer.html;
        reveal(body);
        tell(block, answer.message, false);

        // What had the focus is gone with the old form
        block.querySelector('.mxr-status').focus({ preventScroll: true });

        return;
      }

      tell(block, (answer && answer.message) || block.dataset.mxrError, true);
    } catch (e) {
      tell(block, block.dataset.mxrError, true);
    }

    button.disabled = false;
  };

  const more = async (block, button) => {
    button.disabled = true;

    try {
      const url = asJson(button.dataset.mxrUrl);

      url.searchParams.set('offset', button.dataset.mxrOffset);

      const response = await fetch(url, { credentials: 'same-origin' });
      const answer = await read(response);

      if (answer && answer.success) {
        const list = block.querySelector('.mxr-list');
        const known = list.children.length;

        list.insertAdjacentHTML('beforeend', answer.html);

        // The keyboard goes on with the first of the new reviews
        if (list.children[known]) {
          list.children[known].tabIndex = -1;
          list.children[known].focus({ preventScroll: true });
        }

        if (answer.next === null) {
          button.remove();
        } else {
          button.dataset.mxrOffset = answer.next;
          button.disabled = false;
        }

        return;
      }

      tell(block, (answer && answer.message) || block.dataset.mxrError, true);
    } catch (e) {
      tell(block, block.dataset.mxrError, true);
    }

    button.disabled = false;
  };

  document.addEventListener('submit', (event) => {
    const block = event.target.closest('.mxr-block');

    if (block && event.target.matches('[data-mxr-form]')) {
      event.preventDefault();
      send(block, event.target);
    }
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.mxr-more');
    const block = button && button.closest('.mxr-block');

    if (block) {
      more(block, button);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => reveal(document));
  } else {
    reveal(document);
  }
})();
