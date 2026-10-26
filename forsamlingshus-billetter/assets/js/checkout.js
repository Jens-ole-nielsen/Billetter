(function () {
	function ore(value) {
		var amount = Math.max(0, parseInt(value, 10) || 0);
		var kroner = Math.floor(amount / 100);
		var cents = String(amount % 100).padStart(2, "0");
		var grouped = String(kroner).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
		return grouped + "," + cents + " kr.";
	}

	function bindCoupon(form) {
		var unit = parseInt(form.getAttribute("data-unit") || "0", 10);
		var field = form.querySelector("#fhb-coupon");
		var qty = form.querySelector("#fhb-qty");
		var payable = form.querySelector("[data-fhb-payable]");
		var note = form.querySelector("#fhb-coupon-message");
		if (!field || !payable || unit < 1) {
			return;
		}
		var timer = 0;

		function show(amount, message, failed) {
			payable.textContent = ore(amount);
			if (!note) {
				return;
			}
			if (!message) {
				note.hidden = true;
				note.textContent = "";
				note.className = "description";
				return;
			}
			note.hidden = false;
			note.textContent = message;
			note.className = failed ? "fhb-error" : "description";
		}

		function refresh() {
			var count = qty ? parseInt(qty.value, 10) || 1 : 1;
			var code = field.value.trim();
			if (!code) {
				show(unit * count, "", false);
				return;
			}
			var body = new FormData();
			body.append("action", "fhb_coupon");
			body.append("event_id", form.getAttribute("data-event") || "");
			body.append("quantity", String(count));
			body.append("coupon_code", code);
			var nonce = form.querySelector("[name='fhb_coupon_nonce']");
			if (nonce) {
				body.append("fhb_coupon_nonce", nonce.value);
			}
			fetch(form.getAttribute("data-ajax") || "", {
				method: "POST",
				body: body,
				credentials: "same-origin"
			}).then(function (response) {
				return response.json();
			}).then(function (data) {
				if (!data) {
					return;
				}
				show(data.payable_ore, data.message || "", !data.ok);
			}).catch(function () {
				show(unit * count, "", false);
			});
		}

		function schedule() {
			window.clearTimeout(timer);
			timer = window.setTimeout(refresh, 300);
		}

		field.addEventListener("input", schedule);
		field.addEventListener("change", refresh);
		if (qty) {
			qty.addEventListener("input", schedule);
		}
	}

	function bindInvoice(form) {
		var message = form.getAttribute("data-invoice-confirm") || "";
		var invoice = form.querySelector("input[name='payment_method'][value='invoice']");
		var stripe = form.querySelector("input[name='payment_method'][value='stripe']");
		if (!invoice || !stripe) {
			return;
		}

		invoice.addEventListener("change", function () {
			if (!invoice.checked) {
				return;
			}
			if (window.confirm(message)) {
				invoice.setAttribute("data-confirmed", "1");
				return;
			}
			invoice.setAttribute("data-confirmed", "0");
			stripe.checked = true;
		});

		stripe.addEventListener("change", function () {
			invoice.setAttribute("data-confirmed", "0");
		});

		form.addEventListener("submit", function (event) {
			if (!invoice.checked) {
				return;
			}
			if (invoice.getAttribute("data-confirmed") === "1") {
				return;
			}
			event.preventDefault();
			if (window.confirm(message)) {
				invoice.setAttribute("data-confirmed", "1");
				form.submit();
				return;
			}
			stripe.checked = true;
		});
	}

	document.querySelectorAll("form.fhb-form").forEach(function (form) {
		bindInvoice(form);
		bindCoupon(form);
	});

	var thanks = document.getElementById("fhb-thanks");
	if (thanks && typeof thanks.showModal === "function") {
		thanks.showModal();
		var closeThanks = document.getElementById("fhb-thanks-close");
		if (closeThanks) {
			closeThanks.addEventListener("click", function () {
				thanks.close();
			});
			closeThanks.focus();
		}
	}
})();
