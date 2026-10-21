(function ($) {
	'use strict';

	function nextIndex() {
		var max = -1;
		$('.eb-ticket-rows .eb-ticket-row').each(function () {
			$(this)
				.find('input[name*="[name]"]')
				.each(function () {
					var match = (this.name || '').match(/eb_tickets\[(\d+)\]/);
					if (match) {
						max = Math.max(max, parseInt(match[1], 10));
					}
				});
		});
		return max + 1;
	}

	$(document).on('click', '.eb-add-ticket', function (e) {
		e.preventDefault();
		var tmpl = $('#tmpl-eb-ticket-row').html();
		if (!tmpl) {
			return;
		}
		var index = nextIndex();
		var html = tmpl.replace(/\{\{INDEX\}\}/g, String(index));
		$('.eb-ticket-rows').append(html);
	});

	$(document).on('click', '.eb-remove-ticket', function (e) {
		e.preventDefault();
		var $rows = $('.eb-ticket-rows .eb-ticket-row');
		if ($rows.length <= 1) {
			$(this).closest('tr').find('input[type="text"], input[type="number"]').val('');
			$(this).closest('tr').find('input[type="checkbox"]').prop('checked', true);
			return;
		}
		$(this).closest('tr').remove();
	});
})(jQuery);
