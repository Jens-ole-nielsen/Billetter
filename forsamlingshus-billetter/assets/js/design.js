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

	function padsOn() {
		var choice = document.getElementById("fhb-pad-blocks");
		var value = choice ? choice.value : "auto";
		if (value === "0") {
			return false;
		}
		if (value === "1") {
			return true;
		}
		return preview.classList.contains("has-photo");
	}

	function syncPads() {
		preview.classList.toggle("has-pad", padsOn());
	}

	function syncSize() {
		var size = document.getElementById("fhb-ticket-size");
		if (!size) {
			return;
		}
		var option = size.options[size.selectedIndex];
		if (!option) {
			return;
		}
		var ratio = option.getAttribute("data-ratio");
		if (ratio) {
			preview.style.aspectRatio = ratio;
		}
		var hint = document.getElementById("fhb-size-hint");
		var label = option.getAttribute("data-label");
		if (hint && label) {
			hint.textContent = "Billedet skal være " + label + ".";
		}
	}

	document.addEventListener("input", function () {
		sync();
		syncPads();
	});
	document.addEventListener("change", function (event) {
		sync();
		syncPads();
		if (event.target && event.target.id === "fhb-ticket-size") {
			syncSize();
		}
	});
	document.addEventListener("fhb-media", function (event) {
		if (!event.detail) {
			return;
		}
		if (event.detail.target === "fhb-logo-id") {
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
			return;
		}
		if (event.detail.target === "fhb-background-id") {
			if (event.detail.url) {
				preview.style.backgroundImage = 'url("' + String(event.detail.url).replace(/["\\]/g, "") + '")';
				preview.classList.add("has-photo");
			} else {
				preview.style.backgroundImage = "none";
				preview.classList.remove("has-photo");
			}
			syncPads();
		}
	});

	preview.querySelectorAll("[data-block]").forEach(function (block) {
		block.addEventListener("pointerdown", function (event) {
			if (event.button !== undefined && event.button !== 0) {
				return;
			}
			event.preventDefault();
			var name = block.getAttribute("data-block");
			var inputX = document.getElementById("fhb-block-" + name + "-x");
			var inputY = document.getElementById("fhb-block-" + name + "-y");
			var offsetX = event.clientX - block.getBoundingClientRect().left;
			var offsetY = event.clientY - block.getBoundingClientRect().top;
			block.classList.add("is-dragging");

			function move(pointer) {
				var bounds = preview.getBoundingClientRect();
				var x = pointer.clientX - bounds.left - offsetX;
				var y = pointer.clientY - bounds.top - offsetY;
				var maxX = Math.max(0, bounds.width - block.offsetWidth);
				var maxY = Math.max(0, bounds.height - block.offsetHeight);
				x = Math.max(0, Math.min(maxX, x));
				y = Math.max(0, Math.min(maxY, y));
				var px = bounds.width > 0 ? Math.round((x / bounds.width) * 1000) : 0;
				var py = bounds.height > 0 ? Math.round((y / bounds.height) * 1000) : 0;
				block.style.left = px / 10 + "%";
				block.style.top = py / 10 + "%";
				if (inputX) {
					inputX.value = String(px);
				}
				if (inputY) {
					inputY.value = String(py);
				}
			}

			function end() {
				block.classList.remove("is-dragging");
				block.removeEventListener("pointermove", move);
				block.removeEventListener("pointerup", end);
				block.removeEventListener("pointercancel", end);
			}

			block.addEventListener("pointermove", move);
			block.addEventListener("pointerup", end);
			block.addEventListener("pointercancel", end);
			if (block.setPointerCapture) {
				block.setPointerCapture(event.pointerId);
			}
		});
	});

	sync();
	syncPads();
	syncSize();
})();
