(function () {
	function bind(form) {
		var message = form.getAttribute('data-invoice-confirm') || '';
		var invoice = form.querySelector('input[name="payment_method"][value="invoice"]');
		var stripe = form.querySelector('input[name="payment_method"][value="stripe"]');
		if (!invoice || !stripe) {
			return;
		}

		invoice.addEventListener('change', function () {
			if (!invoice.checked) {
				return;
			}
			if (window.confirm(message)) {
				invoice.setAttribute('data-confirmed', '1');
				return;
			}
			invoice.setAttribute('data-confirmed', '0');
			stripe.checked = true;
		});

		stripe.addEventListener('change', function () {
			invoice.setAttribute('data-confirmed', '0');
		});

		form.addEventListener('submit', function (event) {
			if (!invoice.checked) {
				return;
			}
			if (invoice.getAttribute('data-confirmed') === '1') {
				return;
			}
			event.preventDefault();
			if (window.confirm(message)) {
				invoice.setAttribute('data-confirmed', '1');
				form.submit();
				return;
			}
			stripe.checked = true;
		});
	}

	document.querySelectorAll('form.fhb-form').forEach(bind);
})();
