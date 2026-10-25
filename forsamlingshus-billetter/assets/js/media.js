(function () {
	function bind() {
		if (!window.wp || !wp.media) {
			return;
		}
		document.querySelectorAll(".fhb-media-pick").forEach(function (button) {
			button.addEventListener("click", function (event) {
				event.preventDefault();
				var target = document.getElementById(button.getAttribute("data-target"));
				var preview = document.getElementById(button.getAttribute("data-preview"));
				var frame = wp.media({
					title: "Vælg billede",
					button: { text: "Brug dette billede" },
					multiple: false,
					library: { type: "image" }
				});
				frame.on("select", function () {
					var attachment = frame.state().get("selection").first().toJSON();
					var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url;
					if (target) {
						target.value = String(attachment.id);
					}
					if (preview) {
						preview.textContent = "";
						var img = document.createElement("img");
						img.src = url;
						img.alt = "";
						img.style.maxWidth = "240px";
						img.style.height = "auto";
						preview.appendChild(img);
					}
					document.dispatchEvent(new CustomEvent("fhb-media", {
						detail: { url: url, target: button.getAttribute("data-target") }
					}));
				});
				frame.open();
			});
		});
		document.querySelectorAll(".fhb-media-clear").forEach(function (button) {
			button.addEventListener("click", function (event) {
				event.preventDefault();
				var target = document.getElementById(button.getAttribute("data-target"));
				var preview = document.getElementById(button.getAttribute("data-preview"));
				if (target) {
					target.value = "0";
				}
				if (preview) {
					preview.textContent = "";
				}
				document.dispatchEvent(new CustomEvent("fhb-media", {
					detail: { url: "", target: button.getAttribute("data-target") }
				}));
			});
		});
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", bind);
	} else {
		bind();
	}
})();
