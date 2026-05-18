/**
 * Themeisle Tester Dashboard — tab controller.
 *
 * Activates one panel at a time, supports click + Left/Right/Home/End keyboard
 * navigation, and keeps the current tab in the URL hash so reloads remember
 * the selection.
 */
(() => {
	function activate(tabs, panels, index, shouldFocus) {
		tabs.forEach((tab, i) => {
			const selected = i === index;
			tab.setAttribute("aria-selected", selected ? "true" : "false");
			tab.setAttribute("tabindex", selected ? "0" : "-1");

			const panel = panels[i];

			if (!panel) {
				return;
			}

			if (selected) {
				panel.removeAttribute("hidden");
			} else {
				panel.setAttribute("hidden", "");
			}
		});

		if (shouldFocus) {
			tabs[index].focus();
		}

		if (window.history && typeof window.history.replaceState === "function") {
			window.history.replaceState(null, "", `#${tabs[index].id}`);
		}
	}

	function initTablist(tablist) {
		const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));

		if (tabs.length === 0) {
			return;
		}

		const panels = tabs.map((tab) =>
			document.getElementById(tab.getAttribute("aria-controls")),
		);

		tabs.forEach((tab, index) => {
			tab.addEventListener("click", (event) => {
				event.preventDefault();
				activate(tabs, panels, index, false);
			});

			tab.addEventListener("keydown", (event) => {
				let next = -1;

				switch (event.key) {
					case "ArrowRight":
						next = (index + 1) % tabs.length;
						break;
					case "ArrowLeft":
						next = (index - 1 + tabs.length) % tabs.length;
						break;
					case "Home":
						next = 0;
						break;
					case "End":
						next = tabs.length - 1;
						break;
					default:
						return;
				}

				event.preventDefault();
				activate(tabs, panels, next, true);
			});
		});

		const matched = window.location.hash
			? tabs.findIndex((tab) => `#${tab.id}` === window.location.hash)
			: -1;
		const initialIndex = matched >= 0 ? matched : 0;

		activate(tabs, panels, initialIndex, false);
	}

	function ready(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn);
		}
	}

	ready(() => {
		document.querySelectorAll('.ttp-tabs[role="tablist"]').forEach(initTablist);
	});
})();
