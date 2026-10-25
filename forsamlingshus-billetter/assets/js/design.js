(function () {
	var preview = document.getElementById("fhb-ticket-preview");
	if (!preview) {
		return;
	}

	function sync() {
		var heading = document.getElementById("fhb-heading");
		var headingOut = preview.querySelector("[data-fhb-preview='heading']");
		if (heading && headingOut) {
			headingOut.textContent = heading.value || "Adgangsbillet";
		}
		document.querySelectorAll("[data-fhb-color]").forEach(function (input) {
			var key = input.getAttribute("data-fhb-color");
			preview.style.setProperty("--fhb-" + key, input.value);
		});
		document.querySelectorAll("[data-fhb-field]").forEach(function (input) {
			var row = preview.querySelector("[data-fhb-row='" + input.getAttribute("data-fhb-field") + "']");
			if (row) {
				row.hidden = !input.checked;
			}
		});
	}

	document.addEventListener("input", sync);
	document.addEventListener("change", sync);
	document.addEventListener("fhb-media", function (event) {
		if (!event.detail || event.detail.target !== "fhb-logo-id") {
			return;
		}
		var logo = preview.querySelector("[data-fhb-preview='logo']");
		if (!logo) {
			return;
		}
		if (event.detail.url) {
			logo.src = event.detail.url;
			logo.hidden = false;
		} else {
			logo.removeAttribute("src");
			logo.hidden = true;
		}
	});
	sync();
})();
