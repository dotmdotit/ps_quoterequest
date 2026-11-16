/**
 * dotmquoterequest — carrello preventivi (front office).
 * @author dotm.it
 * @license MIT
 */
(function () {
    'use strict';

    var cfg = typeof dqrFront !== 'undefined' ? dqrFront : { afterAdd: 'redirect', networkError: 'Network error', viewQuote: '' };

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* ---------------------------------------------------------------
     * "Aggiungi al preventivo" nella pagina prodotto
     * ------------------------------------------------------------- */

    // Il tema tiene i dati della combinazione scelta nell'attributo data-product di #product-details,
    // aggiornato a ogni cambio di variante: leggerlo al clic è più affidabile che seguire gli eventi.
    function currentAttributeId(button) {
        var details = document.getElementById('product-details');
        if (details) {
            var data = null;
            if (window.jQuery) {
                data = window.jQuery(details).data('product');
            }
            if (!data) {
                try {
                    data = JSON.parse(details.getAttribute('data-product'));
                } catch (e) {
                    data = null;
                }
            }
            if (data && typeof data.id_product_attribute !== 'undefined') {
                return parseInt(data.id_product_attribute, 10) || 0;
            }
        }
        var input = document.querySelector('#add-to-cart-or-refresh input[name="id_product_attribute"]');
        if (input && input.value) {
            return parseInt(input.value, 10) || 0;
        }

        return parseInt(button.getAttribute('data-id-product-attribute'), 10) || 0;
    }

    function currentQty() {
        var input = document.querySelector('#quantity_wanted, #add-to-cart-or-refresh input[name="qty"]');
        var qty = input ? parseInt(input.value, 10) : 1;

        return qty > 0 ? qty : 1;
    }

    function updateCounts(count, cartUrl) {
        Array.prototype.forEach.call(document.querySelectorAll('.js-dqr-count'), function (el) {
            el.textContent = String(count);
        });
        Array.prototype.forEach.call(document.querySelectorAll('.js-dqr-cart-link'), function (el) {
            if (cartUrl) {
                el.href = cartUrl;
            }
            el.hidden = !count;
        });
    }

    function showMessage(box, text, ok) {
        if (!box) {
            return;
        }
        box.textContent = text || '';
        box.classList.toggle('is-error', !ok);
        box.classList.toggle('is-success', !!ok);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('.js-dqr-add') : null;
        if (!button) {
            return;
        }
        event.preventDefault();
        if (button.disabled) {
            return;
        }
        button.disabled = true;
        button.classList.add('is-loading');

        var container = button.closest('.dqr-add');
        var box = container ? container.querySelector('.js-dqr-message') : null;

        var params = new URLSearchParams();
        params.append('id_product', button.getAttribute('data-id-product'));
        params.append('id_product_attribute', currentAttributeId(button));
        params.append('qty', currentQty());
        params.append('token', button.getAttribute('data-token'));

        fetch(button.getAttribute('data-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: params.toString(),
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    if (cfg.afterAdd !== 'stay' && data.quote_url) {
                        window.location.href = data.quote_url;
                        return;
                    }
                    updateCounts(data.line_count || 0, data.cart_url);
                    showMessage(box, data.message, true);
                } else if (data.login_url) {
                    window.location.href = data.login_url;
                } else {
                    showMessage(box, data.message, false);
                }
            })
            .catch(function () {
                showMessage(box, cfg.networkError, false);
            })
            .then(function () {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
    });

    /* ---------------------------------------------------------------
     * Carrello preventivi: quantità con +/−, invio automatico, svuota
     * ------------------------------------------------------------- */

    ready(function () {
        var cart = document.getElementById('dotmquoterequest-cart');
        if (!cart) {
            return;
        }

        var message = cart.querySelector('.js-dqr-message');

        function submitQty(form) {
            // le note già scritte non vanno perse
            if (message && !form.querySelector('input[name="customer_message"]')) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'customer_message';
                hidden.value = message.value;
                form.appendChild(hidden);
            }
            form.classList.add('is-loading');
            form.submit();
        }

        Array.prototype.forEach.call(cart.querySelectorAll('.js-dqr-qty-form'), function (form) {
            var input = form.querySelector('.js-dqr-qty');
            var initial = input.value;
            var timer = null;

            function schedule() {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    if (input.value !== initial && input.value !== '') {
                        submitQty(form);
                    }
                }, 600);
            }

            Array.prototype.forEach.call(form.querySelectorAll('.js-dqr-step'), function (step) {
                step.addEventListener('click', function () {
                    var value = (parseInt(input.value, 10) || 0) + parseInt(step.getAttribute('data-step'), 10);
                    var max = parseInt(input.getAttribute('max'), 10) || 99999;
                    input.value = String(Math.max(0, Math.min(max, value)));
                    schedule();
                });
            });
            input.addEventListener('change', schedule);
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitQty(form);
            });
        });

        Array.prototype.forEach.call(cart.querySelectorAll('.js-dqr-clear'), function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    });

    /* ---------------------------------------------------------------
     * Checkout di un preventivo approvato: banner con "Annulla il preventivo"
     * e quantità in sola lettura (il server rifiuta comunque le modifiche)
     * ------------------------------------------------------------- */

    if (typeof dqrLock === 'undefined') {
        return;
    }

    function lockCartLines() {
        document.body.classList.add('dqr-locked');
        Array.prototype.forEach.call(document.querySelectorAll('.js-cart-line-product-quantity'), function (input) {
            input.readOnly = true;
        });
    }

    function renderBanner() {
        if (document.getElementById('dqr-lock')) {
            return;
        }
        var target = document.querySelector('.cart-grid-body') || document.querySelector('#main') || document.querySelector('#content-wrapper');
        if (!target) {
            return;
        }

        var box = document.createElement('div');
        box.id = 'dqr-lock';
        box.className = 'dqr dqr-lock';
        box.setAttribute('role', 'status');

        var text = document.createElement('p');
        text.textContent = dqrLock.message;
        box.appendChild(text);

        var cancel = document.createElement('a');
        cancel.className = 'dqr-btn dqr-btn-ghost dqr-btn-sm';
        cancel.href = dqrLock.cancelUrl;
        cancel.textContent = dqrLock.cancelLabel;
        cancel.addEventListener('click', function (event) {
            if (!window.confirm(dqrLock.cancelConfirm)) {
                event.preventDefault();
            }
        });
        box.appendChild(cancel);

        target.insertBefore(box, target.firstChild);
    }

    ready(function () {
        renderBanner();
        lockCartLines();
        if (typeof prestashop !== 'undefined' && prestashop.on) {
            // il tema ridisegna le righe del carrello a ogni aggiornamento
            prestashop.on('updatedCart', function () {
                setTimeout(lockCartLines, 0);
            });
        }
    });
})();
