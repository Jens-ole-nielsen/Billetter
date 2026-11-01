(function () {
	var button = document.getElementById('fhb-reset-sales');
	if (!button) {
		return;
	}

	button.addEventListener('click', function () {
		var message = button.getAttribute('data-confirm') || '';
		if (!window.confirm(message)) {
			return;
		}

		var form = document.createElement('form');
		form.method = 'post';
		form.action = button.getAttribute('data-action') || '';
		var fields = {
			action: 'fhb_reset_event',
			event_id: button.getAttribute('data-event') || '',
			_wpnonce: button.getAttribute('data-nonce') || ''
		};
		Object.keys(fields).forEach(function (name) {
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.value = fields[name];
			form.appendChild(input);
		});
		document.body.appendChild(form);
		form.submit();
	});
})();
