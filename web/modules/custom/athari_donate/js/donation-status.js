/**
 * @file
 * Polls the donation status endpoint until the payment is no longer pending.
 *
 * The server also asks Daraja (STK Push Query) on these polls, so the page
 * resolves even if the Safaricom callback never reaches the site.
 */
(function (Drupal, once) {
  'use strict';

  const POLL_MS = 3000;
  const GIVE_UP_MS = 5 * 60 * 1000;

  Drupal.behaviors.athariDonationStatus = {
    attach(context) {
      once('athari-donation-status', '.athari-donation-status', context).forEach((box) => {
        if (box.dataset.status !== 'pending') {
          return;
        }
        const url = box.dataset.statusUrl;
        const message = box.querySelector('.athari-donation-status__message');
        const receipt = box.querySelector('.athari-donation-status__receipt');
        const started = Date.now();

        const render = (data) => {
          box.dataset.status = data.status;
          box.className = box.className.replace(/athari-donation-status--\S+/, 'athari-donation-status--' + data.status);
          message.textContent = data.message;
          if (receipt && data.receipt) {
            receipt.textContent = Drupal.t('M-Pesa receipt: @receipt', { '@receipt': data.receipt });
          }
        };

        const poll = () => {
          fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status))))
            .then((data) => {
              render(data);
              if (data.status === 'pending') {
                schedule();
              }
            })
            .catch(() => schedule());
        };

        const schedule = () => {
          if (Date.now() - started > GIVE_UP_MS) {
            message.textContent = Drupal.t('We are still waiting for M-Pesa. If you approved the payment, it will be recorded shortly. You can close this page.');
            return;
          }
          window.setTimeout(poll, POLL_MS);
        };

        schedule();
      });
    },
  };
}(Drupal, once));
