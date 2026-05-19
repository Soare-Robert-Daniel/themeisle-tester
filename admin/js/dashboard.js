/**
 * Themeisle Tester Dashboard — client enhancements.
 *
 * 1. Tab controller — switches one panel at a time, Left/Right/Home/End
 *    keyboard navigation, syncs the active tab to the URL hash.
 * 2. List field controller — Add/Remove rows for [data-ttp-list] fields
 *    (e.g. the multi-URL plugin installer). Inputs submit as ttp_params[id][].
 *
 * Datastar (admin/js/libs/datastar.min.js) handles in-place form submits;
 * this file only enhances tabs and list fields. See ADR-0006 and ADR-0008.
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

	function initListField(field) {
		const rows = field.querySelector("[data-ttp-list-rows]");
		const template = field.querySelector("[data-ttp-list-template]");
		const addBtn = field.querySelector("[data-ttp-list-add]");

		if (!rows || !template || !addBtn) {
			return;
		}

		addBtn.addEventListener("click", () => {
			const fragment = template.content.cloneNode(true);
			const newRow = fragment.firstElementChild;
			rows.appendChild(fragment);

			if (newRow) {
				const input = newRow.querySelector("input");
				if (input) {
					input.focus();
				}
			}
		});

		rows.addEventListener("click", (event) => {
			const btn = event.target.closest("[data-ttp-list-remove]");

			if (!btn) {
				return;
			}

			event.preventDefault();

			const row = btn.closest("[data-ttp-list-row]");

			if (!row) {
				return;
			}

			const remaining = rows.querySelectorAll("[data-ttp-list-row]");

			if (remaining.length > 1) {
				row.remove();
				return;
			}

			// Last row: keep it but clear its input instead of removing.
			const input = row.querySelector("input");
			if (input) {
				input.value = "";
				input.focus();
			}
		});
	}

	function ready(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn);
		}
	}

	function initListFieldsIn(root) {
		const scope = root instanceof Element ? root : document;
		scope.querySelectorAll("[data-ttp-list]").forEach(initListField);
	}

	function dismissToast(toast) {
		if (!(toast instanceof HTMLElement)) {
			return;
		}

		toast.classList.add("ttp-flash--leaving");
		window.setTimeout(() => {
			toast.remove();
		}, 220);
	}

	function initToast(toast) {
		if (!(toast instanceof HTMLElement) || toast.dataset.ttpToastReady === "true") {
			return;
		}

		toast.dataset.ttpToastReady = "true";

		const dismiss = toast.querySelector("[data-ttp-toast-dismiss]");
		if (dismiss) {
			dismiss.addEventListener("click", () => dismissToast(toast));
		}

		toast.addEventListener("keydown", (event) => {
			if (event.key === "Escape") {
				event.preventDefault();
				dismissToast(toast);
			}
		});

		if (toast.hasAttribute("data-ttp-toast-autohide")) {
			window.setTimeout(() => dismissToast(toast), 4200);
		}
	}

	function initToastsIn(root) {
		const scope = root instanceof Element ? root : document;

		if (scope.matches?.("[data-ttp-toast]")) {
			initToast(scope);
		}

		scope.querySelectorAll("[data-ttp-toast]").forEach(initToast);
	}

	ready(() => {
		document.querySelectorAll('.ttp-tabs[role="tablist"]').forEach(initTablist);
		initListFieldsIn(document);
		initToastsIn(document);

		document.addEventListener("datastar-fetch", (event) => {
			if (!(event instanceof CustomEvent) || event.detail?.type !== "finished") {
				return;
			}

			const target = event.target;
			if (target instanceof Element) {
				initListFieldsIn(target);
			}

			initToastsIn(document.getElementById("ttp-flash") || document);
		});
	});
})();
