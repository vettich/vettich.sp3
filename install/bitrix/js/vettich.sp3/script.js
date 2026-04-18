VettichSP3 = {
	dialogs: {
		templatesList: new BX.CDialog({
			title: BX.message('VETTICH_SP3_LIST_TEMPLATES'),
			content: '',
			buttons: [],
		}),
		result: new BX.CDialog({
			title: BX.message('VETTICH_SP3_RESULT'),
			content: '',
			buttons: [BX.CDialog.prototype.btnClose],
		}),
	},
}

VettichSP3.m = function (langKey) {
	var msg = BX.message('VETTICH_SP3_' + langKey);
	if (!msg) msg = langKey;
	return msg;
}

VettichSP3.ajaxUrl = '/bitrix/tools/vettich.sp3.ajax.php';
VettichSP3.startUseUrl = '/bitrix/admin/vettich.sp3.start_use.php';

VettichSP3.clearResult = function (elem) {
	elem.innerHTML = '';
}

VettichSP3.setResult = function (elem, text, color) {
	elem.innerHTML = '<span style="color:' + color + '">' + text + '</span>';
}

VettichSP3.logout = function () {
	var rresult = document.getElementById('logout_res');
	VettichSP3.clearResult(rresult);
	var show = BX.showWait("FORM_devform");
	var queries = VettichSP3.queryStringify({ method: 'logout' });
	jQuery.get(VettichSP3.ajaxUrl + queries, function (data) {
		var dataJson = JSON.parse(data)
		if (!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('SUCCESS'), 'green');
			window.location = VettichSP3.startUseUrl;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function () {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.MenuSendWithTemplate = function (query) {
	var show = BX.showWait('adm-workarea');
	// VettichSP3.fixClosePopupMenu();
	jQuery.get(VettichSP3.ajaxUrl + VettichSP3.queryStringify(jQuery.extend({ method: 'listTemplates' }, query)), function (data) {
		var publishBtn = {
			title: VettichSP3.m('PUBLISH'),
			onclick: 'VettichSP3.MenuSendWithTemplateStep2(' + JSON.stringify(query) + ');',
		};
		var buttons = [publishBtn, BX.CDialog.prototype.btnClose];
		var html = '';
		var htmlTemplate = '<input type="checkbox" name="{id}[{val}]" {checked} id="{id}-{val}" value="{val}"> <label for="{id}-{val}">{label}</label><br>';
		try {
			var json = JSON.parse(data);
			var templatesKeys = Object.keys(json.templates);
			if (templatesKeys.length == 0) {
				html = VettichSP3.m('TEMPLATES_NOT_FOUND');
				buttons = [BX.CDialog.prototype.btnClose];
			} else {
				var checked = templatesKeys.length > 1 ? '' : 'checked="checked"';
				htmlTemplate = htmlTemplate.split('{id}').join('TEMPLATES');
				html = VettichSP3.m('CHOOSE_TEMPLATE') + ' <br/><br/>';
				for (var i = 0; i < templatesKeys.length; i++) {
					inputHtml = htmlTemplate
						.split('{val}').join(templatesKeys[i])
						.split('{label}').join(json.templates[templatesKeys[i]])
						.split('{checked}').join(checked);
					html += inputHtml;
				}
			}
		} catch (e) {
			html = VettichSP3.m('SOME_ERROR');
		}
		if (!query.ELEMS && !query.SECTIONS) {
			query = VettichSP3.getSelectedIblockElements(query);
		}
		var link = '/bitrix/admin/vettich.sp3.posts_custom.php' + VettichSP3.queryStringify(query);
		html += '<br/><br/><a href="{link}" target="_blank" onclick="{onclick}">{text}</a>'
			.split('{link}').join(link)
			.split('{onclick}').join('VettichSP3.GoToCustomPostCreate(event)')
			.split('{text}').join(VettichSP3.m('WITHOUT_TEMPLATE'));
		var publishBtn = {
			title: VettichSP3.m('PUBLISH'),
			onclick: 'VettichSP3.MenuSendWithTemplateStep2(' + JSON.stringify(query) + ');',
		};
		VettichSP3.dialogs.templatesList.SetContent(html);
		VettichSP3.dialogs.templatesList.ClearButtons();
		VettichSP3.dialogs.templatesList.SetButtons(buttons);
		VettichSP3.dialogs.templatesList.Show();
	}).always(function () {
		BX.closeWait('adm-workarea', show);
	});
}

VettichSP3.GoToCustomPostCreate = function (event) {
	event.preventDefault();

	VettichSP3.dialogs.templatesList.Close();

	window.open(event.target.href);
}

VettichSP3.MenuSendWithTemplateStep2 = function (query) {
	prevDialog = VettichSP3.dialogs.templatesList
	var show = BX.showWait(prevDialog.DIV.id);
	var selectedTemplates = prevDialog.PARTS.CONTENT_DATA.querySelectorAll('input:checked');
	if (selectedTemplates.length == 0) {
		alert(VettichSP3.m('CHOOSE_TEMPLATE_FROM_LIST'));
		return;
	}
	if (!query.ELEMS && !query.SECTIONS) {
		query = VettichSP3.getSelectedIblockElements(query);
	}
	query.TEMPLATES = [];
	for (var i = 0; i < selectedTemplates.length; i++) {
		query.TEMPLATES.push(selectedTemplates[i].value);
	}
	query.method = 'publishWithTemplate';
	squery = VettichSP3.queryStringify(query);
	jQuery.get(VettichSP3.ajaxUrl + squery).always(function (data) {
		BX.closeWait(prevDialog.DIV.id, show);
		var html = '';
		try {
			var dataJson = JSON.parse(data);
			if (dataJson.error) {
				html = dataJson.error.msg;
			} else {
				html = VettichSP3.m('ADDED_N_POST') + dataJson.length;
			}
		} catch (e) {
			console.log(e);
			html = VettichSP3.m('SOME_ERROR2');
		}
		prevDialog.AllowClose();
		prevDialog.Close();

		VettichSP3.dialogs.result.SetContent(html);
		VettichSP3.dialogs.result.Show();
	});
}

VettichSP3.fixClosePopupMenu = function () {
	Object.keys(BX.PopupMenu.Data).map(function (v, i) {
		BX.PopupMenu.Data[v].close();
	})
}

VettichSP3.getSelectedIblockElements = function (query) {
	var elems = [];
	var sections = [];
	document.querySelectorAll('.adm-list-table-checkbox input:checked, .main-grid-row-checkbox.main-grid-checkbox:checked').forEach(function (node) {
		var name = node.name;
		var value = node.value;
		if (name && name.length && value.length > 1) {
			if (value[0] == 'E') {
				elems.push(value.substr(1));
			} else if (value[0] == 'S') {
				sections.push(value.substr(1));
			} else {
				elems.push(value);
			}
		}
	});
	if (elems.length) {
		query.ELEMS = elems;
	}
	if (sections.length) {
		query.SECTIONS = sections;
	}
	return query;
}

VettichSP3.queryStringify = function (query, onlyQS, prefix) {
	var res = [];
	prefix = prefix || "";
	var src = query;
	if (!prefix) {
		src = jQuery.extend({}, query || {});
		if (typeof BX !== "undefined" && BX.bitrix_sessid && !src.sessid) {
			src.sessid = BX.bitrix_sessid();
		}
	} else {
		src = query || {};
	}
	function buildKey(key) {
		if (!prefix.length) {
			return key
		}
		return prefix + '[' + key + ']';
	}

	Object.keys(src).map(function (key) {
		if (Array.isArray(src[key])) {
			src[key].map(function (val) {
				res.push(buildKey(key) + '[]=' + encodeURIComponent(val));
			});
		} else if (typeof src[key] == "object") {
			res = res.concat(VettichSP3.queryStringify(src[key], true, buildKey(key)).split('&'));
		} else {
			res.push(buildKey(key) + '=' + encodeURIComponent(src[key]));
		}
	});
	var s = res.join('&');
	return onlyQS ? s : '?' + s;
}

VettichSP3.unloadDateTimeAdd = function (event, weekKey) {
	var val;
	var select = '<span class="vettich-sp3-time-item-wrap">';
	select += '<select class="vettich-sp3-time-item" name="_UNLOAD_DATETIME[' + weekKey + '][]">';
	for (var i = 0; i < 24; i++) {
		val = '' + i + ':00';
		select += '<option value="' + val + '">' + val + '</option>';
		val = '' + i + ':30';
		select += '<option value="' + val + '">' + val + '</option>';
	}
	select += '</select>';
	select += '<span class="vettich-sp3-time-remove-btn" onclick="VettichSP3.unloadDateTimeRemove(event)">x</span>';
	select += '</span>';
	select = jQuery(select);
	select.insertBefore(event.target);
}

VettichSP3.unloadDateTimeRemove = function (event) {
	jQuery(event.target).parent().remove();
}

// ================================
// VettichSP3 iframe loader
// ================================

VettichSP3.initIframe = function (config) {
	const STORAGE_KEY = "vettichsp3_endpoint";

	// pingPath — быстрый GET (напр. "/api/v1/ping"); pingTimeout — таймаут ping, обычно меньше timeout iframe
	// extendedHandshakeTimeout — после успешного ping (и по возможности после load iframe) столько мс ждём postMessage pp:ready
	const {
		container,
		endpoints,
		path,
		token,
		lang,
		moduleReadOnly = 1,
		timeout = 5000,
		pingPath,
		pingTimeout = 2500,
		extendedHandshakeTimeout = 60000,
		debug = false,
		/** Задержка появления «Загрузка…» над iframe (мс), 0 — показывать сразу. */
		loadingLabelDelayMs = 450,
		pp_unavailable = false,
		/** Сколько раз повторить загрузку после неудачи (VPN / NS_BINDING_ABORT и т.п.). 0 — только первая попытка. */
		iframeAutoRetries = 2,
		/** Пауза перед следующей автоматической попыткой (мс). */
		iframeAutoRetryDelayMs = 3000,
	} = config;

	const el =
		typeof container === "string"
			? document.querySelector(container)
			: container;

	if (!el) {
		console.error("VettichSP3: container not found");
		return;
	}

	VettichSP3._lastIframeEmbedConfig = config;

	function iframeReloadButtonLabel() {
		if (typeof VettichSP3.m !== "function") {
			return "Перезагрузить";
		}
		const t = String(VettichSP3.m("IFRAME_RELOAD") || "").trim();
		return t && t !== "IFRAME_RELOAD" ? t : "Перезагрузить";
	}

	function sleep(ms) {
		return new Promise((resolve) => setTimeout(resolve, ms));
	}

	/** Показ между автоматическими повторными попытками (после сбоя сети / VPN). */
	function showReconnectingState(attemptNumber, totalAttempts) {
		const wrap = document.createElement("div");
		wrap.className = "pp-iframe-loading-indicator vettich-sp3-iframe-reconnecting";
		let text = "";
		if (typeof VettichSP3.m === "function") {
			const tpl = String(VettichSP3.m("IFRAME_RECONNECTING") || "").trim();
			if (tpl && tpl !== "IFRAME_RECONNECTING") {
				text = tpl
					.replace(/#CURRENT#/g, String(attemptNumber))
					.replace(/#TOTAL#/g, String(totalAttempts));
			}
		}
		if (!text) {
			text =
				"Повторная попытка подключения... (" +
				attemptNumber +
				" из " +
				totalAttempts +
				")";
		}
		wrap.textContent = text;
		el.replaceChildren(wrap);
	}

	function showIframeLoadError(messageHtml) {
		const wrap = document.createElement("div");
		wrap.className = "vettich-sp3-iframe-error-state";
		const body = document.createElement("div");
		body.className = "vettich-sp3-iframe-error-body";
		body.innerHTML = messageHtml;
		wrap.appendChild(body);
		const actions = document.createElement("p");
		actions.className = "vettich-sp3-iframe-reload-row";
		const btn = document.createElement("button");
		btn.type = "button";
		btn.className = "adm-btn adm-btn-save";
		btn.textContent = iframeReloadButtonLabel();
		btn.addEventListener("click", function () {
			const cfg = VettichSP3._lastIframeEmbedConfig;
			if (cfg) {
				VettichSP3.initIframe(cfg);
			} else {
				window.location.reload();
			}
		});
		actions.appendChild(btn);
		wrap.appendChild(actions);
		el.replaceChildren(wrap);
	}

	if (pp_unavailable) {
		let html = "";
		if (typeof VettichSP3.m === "function") {
			const t = String(VettichSP3.m("PP_UNAVAILABLE_HTML") || "").trim();
			if (t && t !== "PP_UNAVAILABLE_HTML") {
				html = t;
			}
		}
		if (!html) {
			html =
				'<div class="vettich-sp3-iframe-load-error"><p><b>Сервис ParrotPoster временно недоступен.</b></p><p>Повторите попытку позже.</p></div>';
		}
		showIframeLoadError(html);
		return;
	}

	const DBG = "[VettichSP3 iframe]";
	const dbgOn = Boolean(debug);
	function dbg(...args) {
		if (!dbgOn) return;
		console.log(DBG, ...args);
	}
	function dbgWarn(...args) {
		if (!dbgOn) return;
		console.warn(DBG, ...args);
	}
	function dbgErr(...args) {
		if (!dbgOn) return;
		console.error(DBG, ...args);
	}

	// Текст из lang script.js.php → BX.message → VettichSP3.m (на странице до init не показываем)
	let loadingHtml = "";
	if (typeof VettichSP3.m === "function") {
		const t = String(VettichSP3.m("IFRAME_LOADING") || "").trim();
		if (t && t !== "IFRAME_LOADING") {
			loadingHtml = t;
		}
	}

	dbg("init", {
		endpoints: endpoints.length,
		path,
		pingPath: pingPath || null,
		pingTimeout,
		timeout,
		extendedHandshakeTimeout,
		debug: dbgOn,
		loadingLabelDelayMs,
		lang,
		readOnly: !!moduleReadOnly,
		iframeAutoRetries,
		iframeAutoRetryDelayMs,
	});

	// ================================
	// CSP: securitypolicyviolation (frame-src / child-src / connect-src → наши origin)
	// ================================

	const ourOriginSet = Object.create(null);
	for (let i = 0; i < endpoints.length; i++) {
		try {
			ourOriginSet[new URL(endpoints[i]).origin] = true;
		} catch (e) { /* skip */ }
	}

	let cspBlockedOurService = false;

	function cspDirectiveBase(ev) {
		const raw = (ev.effectiveDirective || ev.violatedDirective || "").trim();
		if (!raw) {
			return "";
		}
		return raw.split(/\s+/)[0].toLowerCase();
	}

	function cspBlockedUriMatchesOurService(blockedURI) {
		if (
			!blockedURI ||
			blockedURI === "inline" ||
			blockedURI === "eval" ||
			blockedURI === "wasm-eval"
		) {
			return false;
		}
		try {
			return !!ourOriginSet[new URL(blockedURI).origin];
		} catch (e) {
			return false;
		}
	}

	function onCspViolation(ev) {
		const dir = cspDirectiveBase(ev);
		const isFrame = dir === "frame-src" || dir === "child-src";
		const isConnect = dir === "connect-src";
		if (!isFrame && !isConnect) {
			return;
		}
		if (cspBlockedUriMatchesOurService(ev.blockedURI)) {
			cspBlockedOurService = true;
			dbgWarn("CSP violation (our origin)", {
				directive: dir,
				blockedURI: ev.blockedURI,
			});
		}
	}

	document.addEventListener("securitypolicyviolation", onCspViolation);

	// ================================
	// Cache helpers
	// ================================

	function loadCache() {
		try {
			return localStorage.getItem(STORAGE_KEY);
		} catch {
			return null;
		}
	}

	function saveCache(endpoint) {
		try {
			localStorage.setItem(STORAGE_KEY, endpoint);
		} catch { }
	}

	function clearCache() {
		try {
			localStorage.removeItem(STORAGE_KEY);
		} catch { }
	}

	// ================================
	// Фоновый ping (health) по pingPath
	// ================================

	/**
	 * @returns {Promise<boolean|null>} true — ответ ok; false — явный отказ/ошибка; null — не удалось оценить (CORS и т.п.)
	 */
	async function pingOnce(endpoint, ms) {
		const url = new URL(pingPath, endpoint).href;
		const t0 = Date.now();

		const controller = typeof AbortController !== "undefined" ? new AbortController() : null;
		let timer = null;

		if (controller && ms > 0) {
			timer = setTimeout(() => controller.abort(), ms);
		}

		let res;
		try {
			res = await fetch(url, {
				method: "GET",
				signal: controller ? controller.signal : undefined,
				cache: "no-store",
				credentials: "omit",
				mode: "cors",
			});
		} catch (err) {
			if (timer) clearTimeout(timer);
			const dt = Date.now() - t0;
			if (err?.name === "AbortError" || err?.name === "TypeError" || err?.name === "NetworkError") {
				dbg("ping", { endpoint, ok: false, ms: dt, reason: err?.name || "network" });
				return false;
			}
			dbg("ping", { endpoint, ok: null, ms: dt, reason: err?.name || String(err) });
			return null;
		}
		if (timer) clearTimeout(timer);
		const dt = Date.now() - t0;
		const ok = res.ok ? true : false;
		dbg("ping", { endpoint, ok, ms: dt, status: res.status });
		return ok;
	}

	/** Запускает ping по всем endpoint сразу, без await — используется в фоне параллельно с iframe */
	function startBackgroundPings() {
		if (!pingPath) {
			dbg("background pings skipped (no pingPath)");
			return null;
		}
		dbg("background pings started", { count: endpoints.length, pingTimeout });
		const map = Object.create(null);
		for (let i = 0; i < endpoints.length; i++) {
			const ep = endpoints[i];
			if (!map[ep]) {
				map[ep] = pingOnce(ep, pingTimeout);
			}
		}
		return map;
	}

	// ================================
	// iframe + handshake
	// ================================

	function createIframe(src) {
		const iframe = document.createElement("iframe");
		iframe.id = "pp-iframe";
		iframe.style.width = "100%";
		iframe.style.border = "0";
		iframe.frameborder = "0";
		iframe.allowtransparency = "true";
		iframe.src = src;
		return iframe;
	}

	function waitForHandshake(origin, initialTimeoutMs) {
		let done = false;
		let timer = null;
		let handler = null;
		let rejectFn = null;
		let deadline = Date.now() + initialTimeoutMs;
		const hsStarted = Date.now();

		function cleanup() {
			if (done) return;
			done = true;
			if (handler) window.removeEventListener("message", handler);
			if (timer) clearTimeout(timer);
		}

		function scheduleTimer() {
			if (done) return;
			if (timer) clearTimeout(timer);
			const ms = Math.max(0, deadline - Date.now());
			timer = setTimeout(() => {
				if (done) return;
				dbg("handshake timeout fired", { origin, sinceHandshakeMs: Date.now() - hsStarted });
				cleanup();
				rejectFn(new Error("Handshake timeout"));
			}, ms);
		}

		/** Продлевает ожидание postMessage минимум на msFromNow мс от текущего момента (медленная сеть / тяжёлый SPA). */
		function ensureMinWait(msFromNow) {
			if (done) return;
			const minDeadline = Date.now() + msFromNow;
			if (minDeadline > deadline) {
				const prev = deadline;
				deadline = minDeadline;
				dbg("handshake extend", {
					origin,
					minWaitMs: msFromNow,
					prevDeadlineIn: Math.max(0, prev - Date.now()),
					newDeadlineIn: Math.max(0, deadline - Date.now()),
				});
				scheduleTimer();
			}
		}

		dbg("handshake wait", { origin, initialTimeoutMs, firstDeadlineIn: initialTimeoutMs });

		const promise = new Promise((resolve, reject) => {
			rejectFn = reject;

			handler = function (e) {
				if (e.origin !== origin) return;
				if (e.data === "pp:ready") {
					dbg("postMessage pp:ready", { origin, sinceHandshakeMs: Date.now() - hsStarted });
					cleanup();
					resolve();
				}
			};

			window.addEventListener("message", handler);
			scheduleTimer();
		});

		return {
			promise,
			ensureMinWait,
			cancel: () => {
				if (done) return;
				dbg("handshake cancel", { origin, sinceHandshakeMs: Date.now() - hsStarted });
				cleanup();
				rejectFn(new Error("Handshake aborted"));
			},
		};
	}

	async function tryEndpoint(endpoint, pingPromise) {
		const url = `${endpoint}${path}?token=${token}&lang=${lang}&read_only=${moduleReadOnly ? 1 : 0}`;
		const origin = new URL(url).origin;
		const tryStarted = Date.now();

		dbg("tryEndpoint", { endpoint, origin, hasPing: !!pingPromise });

		const iframe = createIframe(url);

		// Надпись «Загрузка...» над iframe (после loadingLabelDelayMs), скрывается после pp:ready
		const loadingWrap = loadingHtml
			? (function () {
				const w = document.createElement("div");
				w.className = "pp-iframe-loading-indicator";
				w.textContent = loadingHtml;
				return w;
			})()
			: null;

		let endpointFinished = false;
		let loadingLabelTimer = null;
		if (loadingWrap) {
			if (loadingLabelDelayMs > 0) {
				loadingWrap.style.display = "none";
			}
			el.replaceChildren(loadingWrap, iframe);
			if (loadingLabelDelayMs > 0) {
				loadingLabelTimer = setTimeout(() => {
					loadingLabelTimer = null;
					if (!endpointFinished && loadingWrap.isConnected) {
						loadingWrap.style.display = "";
					}
				}, loadingLabelDelayMs);
			}
		} else {
			el.replaceChildren(iframe);
		}

		const initialHandshakeMs = pingPromise
			? Math.max(timeout, pingTimeout + 2000)
			: timeout;
		const { promise: hsPromise, cancel: cancelHandshake, ensureMinWait } = waitForHandshake(origin, initialHandshakeMs);

		if (pingPromise) {
			pingPromise.then((p) => {
				dbg("ping settled for try", { endpoint, result: p });
				if (endpointFinished || p !== true) {
					return;
				}
				dbg("ping ok → extend handshake", { endpoint });
				ensureMinWait(extendedHandshakeTimeout);
			});
		}

		try {
			if (pingPromise) {
				await Promise.race([
					hsPromise,
					pingPromise.then((p) => {
						if (p === false) throw new Error("Ping failed");
						return new Promise(() => { });
					}),
				]);
			} else {
				await hsPromise;
			}
			if (loadingLabelTimer) {
				clearTimeout(loadingLabelTimer);
				loadingLabelTimer = null;
			}
			endpointFinished = true;
			if (loadingWrap) {
				loadingWrap.remove();
			}
			dbg("tryEndpoint ok", { endpoint, sinceTryMs: Date.now() - tryStarted });
			return endpoint;
		} catch (e) {
			if (loadingLabelTimer) {
				clearTimeout(loadingLabelTimer);
				loadingLabelTimer = null;
			}
			endpointFinished = true;
			dbg("tryEndpoint fail", {
				endpoint,
				sinceTryMs: Date.now() - tryStarted,
				error: e && e.message ? e.message : String(e),
			});
			cancelHandshake();
			hsPromise.catch(() => { });
			iframe.remove();
			if (loadingWrap) {
				loadingWrap.remove();
			}
			throw e;
		}
	}

	// ================================
	// Core logic
	// ================================

	async function resolveEndpoint() {
		const cached = loadCache();
		if (cached) {
			dbg("localStorage cache hit", { cached });
		}

		// ping всех доменов в фоне (не блокирует первый iframe)
		const pingByEndpoint = startBackgroundPings();

		// приоритет: кеш → остальные
		const ordered = cached
			? [cached, ...endpoints.filter((e) => e !== cached)]
			: endpoints.slice();

		dbg("resolve order", ordered);

		let lastError = null;

		for (const endpoint of ordered) {
			try {
				const pingPromise = pingByEndpoint ? pingByEndpoint[endpoint] : null;
				const ok = await tryEndpoint(endpoint, pingPromise);
				saveCache(ok);
				dbg("endpoint chosen", { endpoint: ok });
				return ok;
			} catch (e) {
				lastError = e;
				dbg("endpoint skipped", { endpoint, error: e && e.message ? e.message : String(e) });

				// если это был кеш — инвалидируем
				if (endpoint === cached) {
					clearCache();
					dbg("cache cleared (failed cached endpoint)");
				}
			}
		}

		dbg("all endpoints failed", { lastError: lastError && lastError.message });
		throw lastError || new Error("All endpoints failed");
	}

	// ================================
	// Run
	// ================================

	const autoRetries = Math.max(0, Math.floor(Number(iframeAutoRetries)) || 0);
	const autoRetryDelay = Math.max(0, Math.floor(Number(iframeAutoRetryDelayMs)) || 0);
	const totalLoadAttempts = 1 + autoRetries;

	(async function runEmbedWithAutoRetries() {
		let lastError = null;
		for (let i = 0; i < totalLoadAttempts; i++) {
			try {
				await resolveEndpoint();
				document.removeEventListener("securitypolicyviolation", onCspViolation);
				return;
			} catch (e) {
				lastError = e;
				dbgErr("resolveEndpoint failed", e, {
					attempt: i + 1,
					totalLoadAttempts,
				});
				const hasMore = i < totalLoadAttempts - 1;
				if (hasMore) {
					showReconnectingState(i + 2, totalLoadAttempts);
					if (autoRetryDelay > 0) {
						await sleep(autoRetryDelay);
					}
				}
			}
		}
		dbgErr("resolveEndpoint fatal (all attempts exhausted)", lastError);
		const errHtml = cspBlockedOurService
			? VettichSP3.m("IFRAME_LOAD_ERROR_CSP_HTML")
			: VettichSP3.m("IFRAME_LOAD_ERROR_HTML");
		showIframeLoadError(errHtml);
		document.removeEventListener("securitypolicyviolation", onCspViolation);
	})();
};
