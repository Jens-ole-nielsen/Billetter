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
		var qrBlock = preview.querySelector("[data-block='qr']");
		var qrField = document.querySelector("[data-fhb-field='show_qr']");
		if (qrBlock && qrField) {
			qrBlock.classList.toggle("is-plain", !qrField.checked);
		}
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
			window.requestAnimationFrame(layoutPhoto);
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
			var photo = document.getElementById("fhb-ticket-photo");
			if (!photo) {
				return;
			}
			if (event.detail.url) {
				photo.hidden = false;
				photo.src = String(event.detail.url);
				preview.classList.add("has-photo");
			} else {
				photo.removeAttribute("src");
				photo.hidden = true;
				preview.classList.remove("has-photo");
			}
			syncPads();
			layoutPhoto();
		}
	});

	function layoutPhoto() {
		var photo = document.getElementById("fhb-ticket-photo");
		var scaleInput = document.getElementById("fhb-bg-scale");
		var xInput = document.getElementById("fhb-bg-x");
		var yInput = document.getElementById("fhb-bg-y");
		var out = document.getElementById("fhb-bg-scale-value");
		if (scaleInput && out) {
			out.textContent = Math.round(Number(scaleInput.value) / 10) + " %";
		}
		if (!photo || photo.hidden || !photo.naturalWidth || !photo.naturalHeight) {
			return;
		}
		var bounds = preview.getBoundingClientRect();
		if (bounds.width < 1 || bounds.height < 1) {
			return;
		}
		var scale = scaleInput ? Number(scaleInput.value) : 1000;
		var ox = xInput ? Number(xInput.value) : 0;
		var oy = yInput ? Number(yInput.value) : 0;
		if (!scale) {
			scale = 1000;
		}
		// Same fit as TicketFrame::photo_frame: 1000 shows the whole photo.
		var fit = Math.min(bounds.width / photo.naturalWidth, bounds.height / photo.naturalHeight);
		var w = photo.naturalWidth * fit * (scale / 1000);
		var h = photo.naturalHeight * fit * (scale / 1000);
		var x = (bounds.width - w) / 2 + (bounds.width * ox) / 1000;
		var y = (bounds.height * oy) / 1000;
		photo.style.setProperty("width", w + "px", "important");
		photo.style.setProperty("height", h + "px", "important");
		photo.style.setProperty("max-width", "none", "important");
		photo.style.setProperty("max-height", "none", "important");
		photo.style.setProperty("left", x + "px", "important");
		photo.style.setProperty("top", y + "px", "important");
	}

	var photoNode = document.getElementById("fhb-ticket-photo");
	if (photoNode) {
		photoNode.addEventListener("load", layoutPhoto);
	}
	var zoom = document.getElementById("fhb-bg-scale");
	if (zoom) {
		zoom.addEventListener("input", layoutPhoto);
	}
	window.addEventListener("resize", layoutPhoto);

	preview.addEventListener("pointerdown", function (event) {
		if (event.button !== undefined && event.button !== 0) {
			return;
		}
		if (event.target && event.target.closest && event.target.closest("[data-block]")) {
			return;
		}
		if (!preview.classList.contains("has-photo")) {
			return;
		}
		var xInput = document.getElementById("fhb-bg-x");
		var yInput = document.getElementById("fhb-bg-y");
		if (!xInput || !yInput) {
			return;
		}
		event.preventDefault();
		var startX = event.clientX;
		var startY = event.clientY;
		var originX = Number(xInput.value) || 0;
		var originY = Number(yInput.value) || 0;
		preview.classList.add("is-moving-photo");

		function move(pointer) {
			var bounds = preview.getBoundingClientRect();
			if (bounds.width < 1 || bounds.height < 1) {
				return;
			}
			var px = Math.round(originX + ((pointer.clientX - startX) / bounds.width) * 1000);
			var py = Math.round(originY + ((pointer.clientY - startY) / bounds.height) * 1000);
			px = Math.max(-2000, Math.min(2000, px));
			py = Math.max(-2000, Math.min(2000, py));
			xInput.value = String(px);
			yInput.value = String(py);
			layoutPhoto();
		}

		function end() {
			preview.classList.remove("is-moving-photo");
			preview.removeEventListener("pointermove", move);
			preview.removeEventListener("pointerup", end);
			preview.removeEventListener("pointercancel", end);
		}

		preview.addEventListener("pointermove", move);
		preview.addEventListener("pointerup", end);
		preview.addEventListener("pointercancel", end);
		if (preview.setPointerCapture) {
			preview.setPointerCapture(event.pointerId);
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
	layoutPhoto();
})();
