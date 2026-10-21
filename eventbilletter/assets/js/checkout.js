(function () {
	'use strict';

	function formatMoney(amount, currency) {
		try {
			return new Intl.NumberFormat('da-DK', {
				style: 'currency',
				currency: currency || 'DKK',
				minimumFractionDigits: 2,
			}).format(amount);
		} catch (e) {
			return amount.toFixed(2) + ' ' + (currency || 'DKK');
		}
	}

	function initForm(root) {
		var form = root.querySelector('.eb-form');
		if (!form || typeof ebCheckout === 'undefined') {
			return;
		}

		var qtyInputs = form.querySelectorAll('.eb-qty');
		var totalEl = form.querySelector('.eb-total-amount');
		var submitBtn = form.querySelector('button[type="submit"]');
		var messageEl = form.querySelector('.eb-form__message');
		var currency = root.getAttribute('data-currency') || 'DKK';

		function recalc() {
			var total = 0;
			var any = false;
			qtyInputs.forEach(function (input) {
				var qty = parseInt(input.value, 10) || 0;
				var price = parseFloat(input.getAttribute('data-price')) || 0;
				if (qty > 0) {
					any = true;
					total += qty * price;
				}
			});
			if (totalEl) {
				totalEl.textContent = formatMoney(total, currency);
			}
			if (submitBtn) {
				submitBtn.disabled = !any;
			}
		}

		qtyInputs.forEach(function (input) {
			input.addEventListener('change', recalc);
			input.addEventListener('input', recalc);
		});
		recalc();

		function showMessage(text, isError) {
			if (!messageEl) {
				return;
			}
			messageEl.hidden = !text;
			messageEl.textContent = text || '';
			messageEl.classList.toggle('is-error', !!isError);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			showMessage('');

			var name = form.querySelector('[name="customer_name"]');
			var email = form.querySelector('[name="customer_email"]');
			if (!name.value.trim() || !email.value.trim()) {
				showMessage(ebCheckout.i18n.error, true);
				return;
			}

			var quantities = {};
			qtyInputs.forEach(function (input) {
				var qty = parseInt(input.value, 10) || 0;
				if (qty > 0) {
					var match = input.name.match(/quantities\[([^\]]+)\]/);
					if (match) {
						quantities[match[1]] = qty;
					}
				}
			});

			submitBtn.disabled = true;
			submitBtn.textContent = ebCheckout.i18n.processing;

			var body = new FormData();
			body.append('action', 'eb_create_checkout');
			body.append('nonce', ebCheckout.nonce);
			body.append('event_id', root.getAttribute('data-event-id'));
			body.append('customer_name', name.value.trim());
			body.append('customer_email', email.value.trim());
			Object.keys(quantities).forEach(function (id) {
				body.append('quantities[' + id + ']', String(quantities[id]));
			});

			fetch(ebCheckout.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			})
				.then(function (res) {
					return res.json().then(function (data) {
						return { ok: res.ok, data: data };
					});
				})
				.then(function (result) {
					if (result.data && result.data.success && result.data.data && result.data.data.checkout_url) {
						window.location.href = result.data.data.checkout_url;
						return;
					}
					var msg =
						(result.data && result.data.data && result.data.data.message) ||
						ebCheckout.i18n.error;
					showMessage(msg, true);
					submitBtn.disabled = false;
					submitBtn.textContent = submitBtn.getAttribute('data-label') || 'Gå til betaling';
					recalc();
				})
				.catch(function () {
					showMessage(ebCheckout.i18n.error, true);
					submitBtn.disabled = false;
					recalc();
				});
		});

		if (submitBtn) {
			submitBtn.setAttribute('data-label', submitBtn.textContent);
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.eb-ticket-form').forEach(initForm);
	});
})();
