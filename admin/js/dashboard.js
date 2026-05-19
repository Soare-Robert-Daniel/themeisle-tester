/**
 * Themeisle Tester Dashboard — client enhancements.
 *
 * 1. Tab controller — switches one panel at a time, arrow/Home/End
 *    keyboard navigation (vertical sidebar), syncs the active tab to the URL hash.
 * 2. List field controller — Add/Remove rows for [data-ttp-list] fields
 *    (e.g. the multi-URL plugin installer). Inputs submit as ttp_params[id][].
 * 3. PPOM inspect pagination — shows up to [data-ttp-ppom-per-page] field groups
 *    per page inside [data-ttp-ppom-pagination] containers.
 * 4. WooCommerce generate progress — creates products sequentially with a
 *    progress bar beside the Run button.
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
				const vertical =
					tablist.getAttribute("aria-orientation") === "vertical";
				let next = -1;

				switch (event.key) {
					case "ArrowDown":
						if (vertical) {
							next = (index + 1) % tabs.length;
						}
						break;
					case "ArrowUp":
						if (vertical) {
							next = (index - 1 + tabs.length) % tabs.length;
						}
						break;
					case "ArrowRight":
						if (!vertical) {
							next = (index + 1) % tabs.length;
						}
						break;
					case "ArrowLeft":
						if (!vertical) {
							next = (index - 1 + tabs.length) % tabs.length;
						}
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

				if (next < 0) {
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

	function formatPpomPageStatus(template, currentPage, totalPages) {
		return template
			.replace("%1$d", String(currentPage))
			.replace("%2$d", String(totalPages));
	}

	function initPpomPagination(pager) {
		if (!(pager instanceof HTMLElement) || pager.dataset.ttpPpomPaginationReady === "true") {
			return;
		}

		const groups = Array.from(pager.querySelectorAll("[data-ttp-ppom-group]"));
		const nav = pager.querySelector("[data-ttp-ppom-pagination-nav]");
		const perPage = Number.parseInt(pager.getAttribute("data-ttp-ppom-per-page") || "5", 10);
		const pageSize = Number.isFinite(perPage) && perPage > 0 ? perPage : 5;

		if (groups.length <= pageSize || !(nav instanceof HTMLElement)) {
			pager.dataset.ttpPpomPaginationReady = "true";
			return;
		}

		const prevBtn = nav.querySelector("[data-ttp-ppom-prev]");
		const nextBtn = nav.querySelector("[data-ttp-ppom-next]");
		const status = nav.querySelector("[data-ttp-ppom-page-status]");
		const statusFormat = nav.getAttribute("data-ttp-ppom-status-format") || "Page %1$d of %2$d";
		const totalPages = Math.ceil(groups.length / pageSize);
		let currentPage = 1;

		const showPage = (page) => {
			currentPage = Math.min(Math.max(page, 1), totalPages);

			groups.forEach((group, index) => {
				const groupPage = Math.floor(index / pageSize) + 1;
				if (groupPage === currentPage) {
					group.removeAttribute("hidden");
				} else {
					group.setAttribute("hidden", "");
				}
			});

			if (status instanceof HTMLElement) {
				status.textContent = formatPpomPageStatus(
					statusFormat,
					currentPage,
					totalPages,
				);
			}

			if (prevBtn instanceof HTMLButtonElement) {
				prevBtn.disabled = currentPage <= 1;
			}

			if (nextBtn instanceof HTMLButtonElement) {
				nextBtn.disabled = currentPage >= totalPages;
			}
		};

		if (prevBtn instanceof HTMLButtonElement) {
			prevBtn.addEventListener("click", () => {
				showPage(currentPage - 1);
			});
		}

		if (nextBtn instanceof HTMLButtonElement) {
			nextBtn.addEventListener("click", () => {
				showPage(currentPage + 1);
			});
		}

		pager.dataset.ttpPpomPaginationReady = "true";
		showPage(1);
	}

	function initPpomPaginationIn(root) {
		const scope = root instanceof Element ? root : document;
		scope.querySelectorAll("[data-ttp-ppom-pagination]").forEach(initPpomPagination);
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

	function shortcutFormFromTarget(target) {
		if (!(target instanceof Element)) {
			return null;
		}

		return target.matches("form[data-ttp-shortcut-form]")
			? target
			: target.closest("form[data-ttp-shortcut-form]");
	}

	function setShortcutBusy(shortcutItem, busy) {
		if (!(shortcutItem instanceof HTMLElement)) {
			return;
		}

		const status = shortcutItem.querySelector("[data-ttp-shortcut-status]");
		const button = shortcutItem.querySelector("[data-ttp-shortcut-submit]");
		const workingLabel = shortcutItem.dataset.ttpShortcutWorking || "";

		if (busy) {
			shortcutItem.classList.add("ttp-plugin-shortcuts__item--busy");
			shortcutItem.setAttribute("aria-busy", "true");

			if (status instanceof HTMLElement) {
				status.textContent = workingLabel;
				status.hidden = false;
			}

			if (button instanceof HTMLButtonElement) {
				button.disabled = true;

				if (workingLabel) {
					button.textContent = workingLabel;
				}
			}

			return;
		}

		shortcutItem.classList.remove("ttp-plugin-shortcuts__item--busy");
		shortcutItem.removeAttribute("aria-busy");

		if (status instanceof HTMLElement) {
			status.textContent = "";
			status.hidden = true;
		}

		if (button instanceof HTMLButtonElement) {
			button.disabled = false;

			const defaultLabel = button.dataset.ttpShortcutDefault;

			if (defaultLabel) {
				button.textContent = defaultLabel;
			}
		}
	}

	function zipInstallFormFromTarget(target) {
		if (!(target instanceof Element)) {
			return null;
		}

		return target.matches("form[data-ttp-zip-install-form]")
			? target
			: target.closest("form[data-ttp-zip-install-form]");
	}

	function setZipInstallBusy(form, busy) {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const card = form.closest(".ttp-card");
		const status = card?.querySelector("[data-ttp-zip-install-status]");
		const button = card?.querySelector("[data-ttp-zip-install-submit]");

		if (busy) {
			if (status instanceof HTMLElement) {
				status.hidden = false;
			}

			if (button instanceof HTMLButtonElement) {
				button.disabled = true;
			}

			return;
		}

		if (status instanceof HTMLElement) {
			status.hidden = true;
		}

		if (button instanceof HTMLButtonElement) {
			button.disabled = false;
		}
	}

	function licenseRefreshFormFromTarget(target) {
		if (!(target instanceof Element)) {
			return null;
		}

		return target.matches("form[data-ttp-license-refresh-form]")
			? target
			: target.closest("form[data-ttp-license-refresh-form]");
	}

	function loggerSendFormFromTarget(target) {
		if (!(target instanceof Element)) {
			return null;
		}

		return target.matches("form[data-ttp-logger-send-form]")
			? target
			: target.closest("form[data-ttp-logger-send-form]");
	}

	function setLoggerSendBusy(form, busy) {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const status = form.querySelector("[data-ttp-logger-send-status]");
		const button = form.querySelector("[data-ttp-logger-send-submit]");
		const workingLabel =
			(button instanceof HTMLButtonElement && button.dataset.ttpWorkingLabel) ||
			"Sending…";

		if (busy) {
			form.classList.add("ttp-logger-send-form--busy");
			form.setAttribute("aria-busy", "true");

			if (status instanceof HTMLElement) {
				status.textContent = workingLabel;
				status.hidden = false;
			}

			if (button instanceof HTMLButtonElement) {
				button.disabled = true;
				button.textContent = workingLabel;
			}

			return;
		}

		form.classList.remove("ttp-logger-send-form--busy");
		form.removeAttribute("aria-busy");

		if (status instanceof HTMLElement) {
			status.textContent = "";
			status.hidden = true;
		}

		if (button instanceof HTMLButtonElement) {
			button.disabled = false;

			const defaultLabel = button.dataset.ttpDefaultLabel;

			if (defaultLabel) {
				button.textContent = defaultLabel;
			}
		}
	}

	function setLicenseRefreshBusy(form, busy) {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const status = form.querySelector("[data-ttp-license-refresh-status]");
		const button = form.querySelector("[data-ttp-license-refresh-submit]");
		const workingLabel =
			(button instanceof HTMLButtonElement && button.dataset.ttpWorkingLabel) ||
			"Refreshing…";

		if (busy) {
			form.classList.add("ttp-card__toolbar--busy");
			form.setAttribute("aria-busy", "true");

			if (status instanceof HTMLElement) {
				status.textContent = workingLabel;
				status.hidden = false;
			}

			if (button instanceof HTMLButtonElement) {
				button.disabled = true;
				button.textContent = workingLabel;
			}

			return;
		}

		form.classList.remove("ttp-card__toolbar--busy");
		form.removeAttribute("aria-busy");

		if (status instanceof HTMLElement) {
			status.textContent = "";
			status.hidden = true;
		}

		if (button instanceof HTMLButtonElement) {
			button.disabled = false;

			const defaultLabel = button.dataset.ttpDefaultLabel;

			if (defaultLabel) {
				button.textContent = defaultLabel;
			}
		}
	}

	function formatProgressLabel(template, ...values) {
		let text = template;

		values.forEach((value, index) => {
			const tokenNumber = `%${index + 1}$d`;
			const tokenString = `%${index + 1}$s`;

			text = text.split(tokenNumber).join(String(value));
			text = text.split(tokenString).join(String(value));
		});

		return text;
	}

	function readWcGenerateTotal(form) {
		const countInput = form.querySelector('[name="ttp_params[count]"]');
		const raw = countInput instanceof HTMLInputElement ? countInput.value : "5";
		const total = Number.parseInt(raw, 10);

		if (!Number.isFinite(total) || total <= 0) {
			return 5;
		}

		return Math.min(total, 25);
	}

	function wcGeneratePercent(current, total, inFlight) {
		if (total <= 0) {
			return 0;
		}

		const percent = Math.round((current / total) * 100);

		if (inFlight && current < total) {
			return Math.max(percent, 5);
		}

		return percent;
	}

	function restErrorMessage(data, fallback) {
		if (typeof data?.message === "string") {
			return data.message;
		}

		if (typeof data?.message?.raw === "string") {
			return data.message.raw;
		}

		return fallback;
	}

	function setWcGenerateBusy(form, busy) {
		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		const card = form.closest(".ttp-card");
		const progress = card?.querySelector("[data-ttp-wc-generate-progress]");
		const button = card?.querySelector("[data-ttp-wc-generate-submit]");

		if (busy) {
			card?.classList.add("ttp-card--busy");
			card?.setAttribute("aria-busy", "true");

			if (progress instanceof HTMLElement) {
				progress.hidden = false;
			}

			if (button instanceof HTMLButtonElement) {
				button.disabled = true;
			}

			return;
		}

		card?.classList.remove("ttp-card--busy");
		card?.removeAttribute("aria-busy");

		if (button instanceof HTMLButtonElement) {
			button.disabled = false;
		}
	}

	function updateWcGenerateProgress(
		card,
		current,
		total,
		statusText,
		inFlight = false,
	) {
		if (!(card instanceof HTMLElement)) {
			return;
		}

		const progress = card.querySelector("[data-ttp-wc-generate-progress]");
		const track = card.querySelector("[data-ttp-wc-generate-progress-track]");
		const bar = card.querySelector("[data-ttp-wc-generate-progress-bar]");
		const status = card.querySelector("[data-ttp-wc-generate-status]");
		const percent = wcGeneratePercent(current, total, inFlight);

		if (track instanceof HTMLElement) {
			track.setAttribute("aria-valuenow", String(percent));
		}

		if (bar instanceof HTMLElement) {
			bar.style.width = `${percent}%`;
		}

		if (status instanceof HTMLElement && statusText) {
			status.textContent = statusText;
		}

		if (progress instanceof HTMLElement) {
			progress.hidden = false;
		}
	}

	async function runWcGenerateForm(form) {
		const config = window.ttpDashboard;
		const card = form.closest(".ttp-card");
		const labels = config?.wcGenerate || {};

		if (!config?.restUrl || !config?.restNonce) {
			updateWcGenerateProgress(
				card,
				0,
				1,
				labels.config ||
					"Dashboard configuration is missing. Reload the page and try again.",
			);

			return;
		}

		const endpoint = `${config.restUrl}utilities/woocommerce_generate_random_products/run`;
		const total = readWcGenerateTotal(form);
		let batch = "";
		const stepTimeoutMs = 120000;

		setWcGenerateBusy(form, true);

		try {
			for (let index = 1; index <= total; index++) {
				const body = new FormData(form);

				body.append("ttp_product_index", String(index));
				body.append("ttp_total", String(total));

				if (batch) {
					body.append("ttp_batch", batch);
				}

				const creatingLabel = formatProgressLabel(
					labels.creating || "Creating product %1$d of %2$d…",
					index,
					total,
				);

				updateWcGenerateProgress(
					card,
					index - 1,
					total,
					creatingLabel,
					true,
				);

				let response;

				try {
					response = await fetch(endpoint, {
						method: "POST",
						credentials: "same-origin",
						headers: {
							"X-WP-Nonce": config.restNonce,
							Accept: "application/json",
						},
						body,
						signal: AbortSignal.timeout(stepTimeoutMs),
					});
				} catch (fetchError) {
					if (
						fetchError instanceof DOMException &&
						fetchError.name === "TimeoutError"
					) {
						throw new Error(
							labels.timeout ||
								"The request timed out. Try fewer products or a simpler product type.",
						);
					}

					throw fetchError;
				}

				const data = await response.json().catch(() => ({}));

				if (!response.ok) {
					throw new Error(
						restErrorMessage(
							data,
							labels.failed || "Product creation failed.",
						),
					);
				}

				if (typeof data.batch === "string" && data.batch) {
					batch = data.batch;
				}

				const detail =
					Array.isArray(data.details) && typeof data.details[0] === "string"
						? data.details[0]
						: "";

				updateWcGenerateProgress(
					card,
					index,
					total,
					detail
						? formatProgressLabel(
								labels.created || "Created product %1$d — %2$s",
								index,
								detail,
							)
						: typeof data.message === "string"
							? data.message
							: "",
				);
			}

			updateWcGenerateProgress(
				card,
				total,
				total,
				formatProgressLabel(
					labels.complete || "Created %1$d products (batch %2$s).",
					total,
					batch,
				),
			);
		} catch (error) {
			const status = card?.querySelector("[data-ttp-wc-generate-status]");

			if (status instanceof HTMLElement) {
				status.textContent =
					error instanceof Error
						? error.message
						: labels.failed || "Product creation failed.";
			}

			const progress = card?.querySelector("[data-ttp-wc-generate-progress]");

			if (progress instanceof HTMLElement) {
				progress.hidden = false;
			}
		} finally {
			setWcGenerateBusy(form, false);
		}
	}

	function initWcGenerateForm(form) {
		if (!(form instanceof HTMLFormElement) || form.dataset.ttpWcGenerateReady === "true") {
			return;
		}

		form.dataset.ttpWcGenerateReady = "true";

		form.addEventListener("submit", (event) => {
			event.preventDefault();
			void runWcGenerateForm(form);
		});
	}

	function initWcGenerateFormsIn(root) {
		const scope = root instanceof Element ? root : document;

		scope.querySelectorAll("form[data-ttp-wc-generate-form]").forEach(initWcGenerateForm);
	}

	function handleDatastarFetch(event) {
		if (!(event instanceof CustomEvent)) {
			return;
		}

		const fetchType = event.detail?.type;
		const target = event.target;

		if (!(target instanceof Element)) {
			return;
		}

		const licenseRefreshForm = licenseRefreshFormFromTarget(target);

		if (licenseRefreshForm) {
			if (fetchType === "started") {
				setLicenseRefreshBusy(licenseRefreshForm, true);
			} else if (fetchType === "finished" || fetchType === "error") {
				setLicenseRefreshBusy(licenseRefreshForm, false);
			}

			return;
		}

		const loggerSendForm = loggerSendFormFromTarget(target);

		if (loggerSendForm) {
			if (fetchType === "started") {
				setLoggerSendBusy(loggerSendForm, true);
			} else if (fetchType === "finished" || fetchType === "error") {
				setLoggerSendBusy(loggerSendForm, false);
			}

			return;
		}

		const shortcutForm = shortcutFormFromTarget(target);

		if (shortcutForm) {
			const shortcutItem = shortcutForm.closest("[data-ttp-shortcut]");

			if (fetchType === "started") {
				setShortcutBusy(shortcutItem, true);
			} else if (fetchType === "finished" || fetchType === "error") {
				setShortcutBusy(shortcutItem, false);
			}

			return;
		}

		const zipForm = zipInstallFormFromTarget(target);

		if (!zipForm) {
			return;
		}

		if (fetchType === "started") {
			setZipInstallBusy(zipForm, true);
		} else if (fetchType === "finished" || fetchType === "error") {
			setZipInstallBusy(zipForm, false);
		}
	}

	ready(() => {
		document.querySelectorAll('.ttp-tabs[role="tablist"]').forEach(initTablist);
		initListFieldsIn(document);
		initPpomPaginationIn(document);
		initWcGenerateFormsIn(document);
		initToastsIn(document);

		document.addEventListener("datastar-fetch", (event) => {
			handleDatastarFetch(event);

			if (!(event instanceof CustomEvent) || event.detail?.type !== "finished") {
				return;
			}

			const target = event.target;
			if (target instanceof Element) {
				initListFieldsIn(target);
				initPpomPaginationIn(target);
				initWcGenerateFormsIn(target);
			}

			initToastsIn(document.getElementById("ttp-flash") || document);
		});
	});
})();
