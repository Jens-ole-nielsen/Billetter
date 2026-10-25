(function () {
	var cfg = window.fhbCheckin || {};
	var video = document.getElementById("fhb-video");
	var canvas = document.getElementById("fhb-canvas");
	var start = document.getElementById("fhb-start-camera");
	var form = document.getElementById("fhb-manual");
	var result = document.getElementById("fhb-checkin-result");
	if (!form || !result) {
		return;
	}

	var busy = false;
	var lastToken = "";
	var lastAt = 0;
	var detector = null;

	function el(tag, text, className) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text != null) {
			node.textContent = text;
		}
		return node;
	}

	function show(data) {
		result.textContent = "";
		var code = data.result || "unknown";
		var klass = code === "checked_in" ? "is-ok" : (code === "already" ? "is-warn" : "is-bad");
		var card = el("div", null, "fhb-checkin-card " + klass);
		card.appendChild(el("p", null)).appendChild(el("strong", data.message || ""));
		if (data.buyer) {
			card.appendChild(el("p", data.buyer));
		}
		if (data.event) {
			card.appendChild(el("p", data.event));
		}
		var unused = Array.isArray(data.unused) ? data.unused : [];
		if (Array.isArray(data.tickets) && data.tickets.length) {
			card.appendChild(el("p", unused.length ? (cfg.i18n.unused + " " + unused.join(", ")) : cfg.i18n.noneUnused));
			var list = el("ul");
			data.tickets.forEach(function (ticket) {
				var status = ticket.status === "checked_in"
					? cfg.i18n.checked + (ticket.checked_in_at ? " " + ticket.checked_in_at : "")
					: cfg.i18n.valid;
				list.appendChild(el("li", "Billet " + ticket.sequence + ": " + status));
			});
			card.appendChild(list);
		}
		result.appendChild(card);
	}

	function submit(token) {
		token = String(token || "").replace(/\s+/g, "").toLowerCase();
		if (!/^[a-f0-9]{32}$/.test(token)) {
			show({ result: "unknown", message: "Koden skal være 32 tegn (0-9 og a-f)." });
			return;
		}
		var now = Date.now();
		if (token === lastToken && now - lastAt < 4000) {
			return;
		}
		lastToken = token;
		lastAt = now;
		if (busy) {
			return;
		}
		busy = true;
		var body = new FormData();
		body.append("action", "fhb_checkin");
		body.append("fhb_checkin_nonce", cfg.nonce || "");
		body.append("token", token);
		fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: body })
			.then(function (response) { return response.json(); })
			.then(function (json) {
				busy = false;
				if (!json || !json.success) {
					show({ result: "unknown", message: (json && json.data && json.data.message) || cfg.i18n.network });
					return;
				}
				show(json.data || {});
				var input = document.getElementById("fhb-token");
				if (input) {
					input.value = "";
				}
			})
			.catch(function () {
				busy = false;
				show({ result: "unknown", message: cfg.i18n.network });
			});
	}

	form.addEventListener("submit", function (event) {
		event.preventDefault();
		submit(form.token.value);
	});

	async function tick() {
		if (!video || !video.videoWidth) {
			window.requestAnimationFrame(tick);
			return;
		}
		try {
			if ("BarcodeDetector" in window) {
				if (!detector) {
					detector = new window.BarcodeDetector({ formats: ["qr_code"] });
				}
				var codes = await detector.detect(video);
				if (codes && codes[0] && codes[0].rawValue) {
					submit(codes[0].rawValue);
				}
			} else if (window.jsQR && canvas) {
				var context = canvas.getContext("2d", { willReadFrequently: true });
				canvas.width = video.videoWidth;
				canvas.height = video.videoHeight;
				context.drawImage(video, 0, 0);
				var image = context.getImageData(0, 0, canvas.width, canvas.height);
				var code = window.jsQR(image.data, image.width, image.height, { inversionAttempts: "dontInvert" });
				if (code && code.data) {
					submit(code.data);
				}
			}
		} catch (error) {
			// Keep scanning. Manual entry still works.
		}
		window.setTimeout(function () {
			window.requestAnimationFrame(tick);
		}, 250);
	}

	if (start && video) {
		start.addEventListener("click", async function () {
			if (!window.isSecureContext) {
				show({ result: "unknown", message: cfg.i18n.needHttps });
				return;
			}
			try {
				var stream = await navigator.mediaDevices.getUserMedia({
					video: { facingMode: "environment" },
					audio: false
				});
				video.srcObject = stream;
				video.hidden = false;
				await video.play();
				tick();
			} catch (error) {
				show({ result: "unknown", message: cfg.i18n.cameraError });
			}
		});
	}
})();
