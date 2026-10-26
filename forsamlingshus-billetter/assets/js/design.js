(function () {
	var preview = document.getElementById("fhb-ticket-preview");
	if (!preview) {
		return;
	}

	var spec = {
		photoMax: 420,
		gap: 22,
		marginX: 48,
		marginBottom: 36,
		columnGap: 28,
		qrW: 400,
		logoH: 100,
		logoGap: 12
	};
	try {
		spec = Object.assign(spec, JSON.parse(preview.getAttribute("data-sheet") || "{}"));
	} catch (error) {
		spec = spec;
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
		applyInk();
	}

	function luma(hex) {
		var n = parseInt(String(hex).replace("#", ""), 16);
		if (!n && n !== 0) {
			return 0;
		}
		var r = (n >> 16) & 255;
		var g = (n >> 8) & 255;
		var b = n & 255;
		return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
	}

	function onSurface(ink, surface) {
		if (Math.abs(luma(ink) - luma(surface)) >= 0.45) {
			return ink;
		}
		return luma(surface) > 0.55 ? "#1C1C1C" : "#FFFFFF";
	}

	function applyInk() {
		var primary = preview.style.getPropertyValue("--fhb-primary") || "#1F4B3A";
		var text = preview.style.getPropertyValue("--fhb-text") || "#1C1C1C";
		var paper = preview.style.getPropertyValue("--fhb-bg") || "#FFFBF5";
		preview.style.setProperty("--fhb-label", onSurface(primary.trim(), paper.trim()));
		preview.style.setProperty("--fhb-value", onSurface(text.trim(), paper.trim()));
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
	}

	// Same arithmetic as TicketFrame::sheet().
	function sheet(srcW, srcH, pageW, pageH, fields, logo) {
		var maxH = (pageH * spec.photoMax) / 1000;
		var photo;
		var contentTop;
		if (!srcW || !srcH) {
			photo = { x: 0, y: 0, w: 0, h: 0 };
			contentTop = (pageH * spec.gap) / 1000;
		} else {
			var fit = Math.min(pageW / srcW, maxH / srcH);
			var dw = srcW * fit;
			var dh = srcH * fit;
			photo = { x: (pageW - dw) / 2, y: 0, w: dw, h: dh };
			contentTop = dh + (pageH * spec.gap) / 1000;
		}
		var marginX = (pageW * spec.marginX) / 1000;
		var bottom = pageH - (pageH * spec.marginBottom) / 1000;
		var qrW = (pageW * spec.qrW) / 1000;
		var gapX = (pageW * spec.columnGap) / 1000;
		var qrX = pageW - marginX - qrW;
		var fieldsW = Math.max(36, qrX - gapX - marginX);
		var logoH = 0;
		if (logo) {
			logoH = Math.min((pageH * spec.logoH) / 1000, (bottom - contentTop) * 0.22);
		}
		var blocks = {};
		if (logoH > 0) {
			blocks.logo = { x: qrX, y: contentTop, w: qrW, h: logoH };
		}
		var qrY = contentTop + (logoH > 0 ? logoH + (pageH * spec.logoGap) / 1000 : 0);
		blocks.qr = { x: qrX, y: qrY, w: qrW, h: Math.max(36, bottom - qrY) };
		var names = fields.length ? fields : ["heading"];
		var slot = (bottom - contentTop) / names.length;
		names.forEach(function (name, index) {
			blocks[name] = { x: marginX, y: contentTop + index * slot, w: fieldsW, h: slot };
		});
		return { photo: photo, blocks: blocks };
	}

	function shownFields() {
		var names = [];
		["heading", "event", "date", "time", "buyer", "price"].forEach(function (name) {
			var el = preview.querySelector("[data-block='" + name + "']");
			if (el && !el.hidden) {
				names.push(name);
			}
		});
		return names;
	}

	function placeBox(el, box, pageW, pageH) {
		if (!el || !box) {
			return;
		}
		el.style.left = (box.x / pageW) * 100 + "%";
		el.style.top = (box.y / pageH) * 100 + "%";
		el.style.width = (box.w / pageW) * 100 + "%";
		el.style.height = (box.h / pageH) * 100 + "%";
	}

	function layout() {
		var pageW = preview.clientWidth;
		var pageH = preview.clientHeight;
		if (pageW < 1 || pageH < 1) {
			return;
		}
		var photo = document.getElementById("fhb-ticket-photo");
		var srcW = 0;
		var srcH = 0;
		if (photo && !photo.hidden && photo.naturalWidth && photo.naturalHeight) {
			srcW = photo.naturalWidth;
			srcH = photo.naturalHeight;
		}
		var logo = preview.querySelector("[data-block='logo']");
		var placed = sheet(srcW, srcH, pageW, pageH, shownFields(), logo && !logo.hidden);
		if (photo && srcW) {
			placeBox(photo, placed.photo, pageW, pageH);
		}
		Object.keys(placed.blocks).forEach(function (name) {
			placeBox(preview.querySelector("[data-block='" + name + "']"), placed.blocks[name], pageW, pageH);
		});
	}

	document.addEventListener("input", function () {
		sync();
		layout();
	});
	document.addEventListener("change", function (event) {
		sync();
		if (event.target && event.target.id === "fhb-ticket-size") {
			syncSize();
		}
		window.requestAnimationFrame(layout);
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
			layout();
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
				layout();
			}
		}
	});

	var photoNode = document.getElementById("fhb-ticket-photo");
	if (photoNode) {
		photoNode.addEventListener("load", layout);
		if (photoNode.complete) {
			layout();
		}
	}
	window.addEventListener("resize", layout);
	sync();
	syncSize();
	layout();
})();
