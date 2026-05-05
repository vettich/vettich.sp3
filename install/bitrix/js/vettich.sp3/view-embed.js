(function (window) {
	'use strict';

	/**
	 * @param {object} config
	 * @param {string[]} [config.allowedOrigins]
	 * @param {Object.<string,string>} [config.menuItems]
	 * @param {object} config.iframeConfig — аргумент для VettichSP3.initIframe
	 */
	function init(config) {
		if (!config || typeof config !== 'object') {
			return;
		}
		var allowedOrigins = config.allowedOrigins || [];
		var menuItems = config.menuItems || {};
		var iframeConfig = config.iframeConfig;

		function ppOriginAllowed(origin) {
			return allowedOrigins.indexOf(origin) !== -1;
		}

		var ppParentBackdropActive = false;

		function pp_update_parent_backdrop_segments() {
			var root = document.getElementById('pp-parent-modal-backdrop');
			if (!root || !ppParentBackdropActive) {
				return;
			}
			var iframe = document.getElementById('pp-iframe');
			var top = root.querySelector('[data-pp-segment="top"]');
			var bottom = root.querySelector('[data-pp-segment="bottom"]');
			var left = root.querySelector('[data-pp-segment="left"]');
			var right = root.querySelector('[data-pp-segment="right"]');
			if (!iframe || !top || !bottom || !left || !right) {
				return;
			}
			var rect = iframe.getBoundingClientRect();
			var vw = window.innerWidth;
			var vh = window.innerHeight;
			var x1 = Math.min(Math.max(0, rect.left), vw);
			var y1 = Math.min(Math.max(0, rect.top), vh);
			var x2 = Math.min(Math.max(0, rect.right), vw);
			var y2 = Math.min(Math.max(0, rect.bottom), vh);

			top.style.top = '0px';
			top.style.left = '0px';
			top.style.right = '0px';
			top.style.width = '100%';
			top.style.height = y1 + 'px';
			top.style.bottom = 'auto';

			bottom.style.top = y2 + 'px';
			bottom.style.left = '0px';
			bottom.style.right = '0px';
			bottom.style.bottom = '0px';
			bottom.style.width = '100%';
			bottom.style.height = 'auto';

			var midH = Math.max(0, y2 - y1);
			left.style.top = y1 + 'px';
			left.style.left = '0px';
			left.style.width = x1 + 'px';
			left.style.height = midH + 'px';
			left.style.right = 'auto';
			left.style.bottom = 'auto';

			right.style.top = y1 + 'px';
			right.style.left = x2 + 'px';
			right.style.right = '0px';
			right.style.height = midH + 'px';
			right.style.bottom = 'auto';
			right.style.width = 'auto';
		}

		function pp_show_parent_backdrop() {
			var root = document.getElementById('pp-parent-modal-backdrop');
			if (!root) {
				root = document.createElement('div');
				root.id = 'pp-parent-modal-backdrop';
				root.className = 'pp-parent-modal-backdrop';
				root.setAttribute('aria-hidden', 'true');
				root.addEventListener('click', function (e) {
					var seg = e.target.closest('.pp-parent-modal-backdrop__segment');
					if (!seg) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					pp_send_message('parent_backdrop_click', {});
				});
				['top', 'bottom', 'left', 'right'].forEach(function (name) {
					var seg = document.createElement('div');
					seg.className =
						'pp-parent-modal-backdrop__segment pp-parent-modal-backdrop__segment--' + name;
					seg.setAttribute('data-pp-segment', name);
					root.appendChild(seg);
				});
				document.body.appendChild(root);
			}
			ppParentBackdropActive = true;
			pp_update_parent_backdrop_segments();
			var container = document.querySelector('.pp-iframe-container');
			if (container) {
				container.classList.add('pp-iframe-container--modal-open');
			}
		}

		function pp_hide_parent_backdrop() {
			ppParentBackdropActive = false;
			var el = document.getElementById('pp-parent-modal-backdrop');
			if (el) {
				el.remove();
			}
			var container = document.querySelector('.pp-iframe-container');
			if (container) {
				container.classList.remove('pp-iframe-container--modal-open');
			}
		}

		var ppViewportBroadcastOn = false;
		var ppViewportTimer = null;
		var ppViewportRaf = 0;

		function pp_collect_viewport_for_iframe() {
			var iframe = document.getElementById('pp-iframe');
			if (!iframe) {
				return null;
			}
			var rect = iframe.getBoundingClientRect();
			var iframeAbsTop = rect.top + window.scrollY;
			var bxPanel = document.getElementById('bx-panel') || document.querySelector('.bx-panel');
			var adminBarHeight = bxPanel ? bxPanel.offsetHeight : 0;
			return {
				scrollY: window.scrollY,
				viewportHeight: window.innerHeight,
				viewportWidth: window.innerWidth,
				iframeAbsTop: iframeAbsTop,
				iframeLeft: rect.left,
				adminBarHeight: adminBarHeight,
			};
		}

		function pp_send_viewport_to_iframe() {
			var payload = pp_collect_viewport_for_iframe();
			if (!payload) {
				return;
			}
			pp_send_message('viewport_update', payload);
			if (ppParentBackdropActive) {
				pp_update_parent_backdrop_segments();
			}
		}

		function pp_send_viewport_to_iframe_scheduled() {
			if (ppViewportRaf) {
				return;
			}
			ppViewportRaf = requestAnimationFrame(function () {
				ppViewportRaf = 0;
				pp_send_viewport_to_iframe();
			});
		}

		function pp_start_viewport_broadcast() {
			if (ppViewportBroadcastOn) {
				return;
			}
			ppViewportBroadcastOn = true;
			pp_send_viewport_to_iframe();
			ppViewportTimer = window.setInterval(pp_send_viewport_to_iframe, 500);
			window.addEventListener('scroll', pp_send_viewport_to_iframe_scheduled, { passive: true });
			window.addEventListener('resize', pp_send_viewport_to_iframe_scheduled, { passive: true });
		}

		function pp_send_message(type, data) {
			var msg = Object.assign({ type: type }, data || {});
			var iframe = document.getElementById('pp-iframe');
			if (!iframe || !iframe.contentWindow || !iframe.src) {
				return;
			}
			var targetOrigin;
			try {
				targetOrigin = new URL(iframe.src, window.location.href).origin;
			} catch (e) {
				return;
			}
			if (!ppOriginAllowed(targetOrigin)) {
				return;
			}
			iframe.contentWindow.postMessage(msg, targetOrigin);
		}

		window.addEventListener('message', function (event) {
			if (!ppOriginAllowed(event.origin)) {
				return;
			}
			if (!event.data || typeof event.data.type !== 'string') {
				return;
			}
			var fn = pp_message_commands[event.data.type];
			if (fn) {
				fn(event.data);
			}
		});

		var pp_message_commands = {
			request_token_refresh: function () {
				var url = '/bitrix/tools/vettich.sp3.ajax.php';
				var body = new URLSearchParams({
					method: 'refreshSessionToken',
				});
				if (typeof BX !== 'undefined' && BX.bitrix_sessid) {
					body.set('sessid', BX.bitrix_sessid());
				}
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
					credentials: 'same-origin',
				})
					.then(function (resp) {
						return resp.json();
					})
					.then(function (res) {
						pp_send_message('token_refresh_result', {
							token: res && res.token ? String(res.token) : '',
							error: res && res.error ? res.error : null,
						});
					})
					.catch(function () {
						pp_send_message('token_refresh_result', {
							token: '',
							error: 'network',
						});
					});
			},
			resize: function (data) {
				var iframe = document.getElementById('pp-iframe');
				if (iframe) {
					iframe.style.height = data.height + 'px';
				}
				pp_send_message('resize_result', {});
				pp_start_viewport_broadcast();
			},
			modal_open: function () {
				pp_show_parent_backdrop();
				pp_start_viewport_broadcast();
				document.documentElement.style.overflow = 'hidden';
				var payload = pp_collect_viewport_for_iframe();
				if (!payload) {
					pp_send_message('modal_open_result', {
						scrollY: window.scrollY,
						viewportHeight: window.innerHeight,
						viewportWidth: window.innerWidth,
						iframeAbsTop: window.scrollY,
						iframeLeft: 0,
						adminBarHeight: 0,
					});
					return;
				}
				pp_send_message('modal_open_result', payload);
			},
			modal_close: function () {
				pp_hide_parent_backdrop();
				document.documentElement.style.overflow = '';
				pp_send_message('modal_close_result', {});
			},
			prepare_callback: function () {
				pp_send_message('prepare_callback_result', {
					url: location.href,
				});
			},
			goto: function (data) {
				var items = menuItems;
				var url = items[data.url] !== undefined ? items[data.url] : data.url;
				location.href = url;
			},
			login: function (data) {
				var url = '/bitrix/tools/vettich.sp3.ajax.php';
				var body = new URLSearchParams({
					method: 'auth',
					token: String(data.token !== undefined && data.token !== null ? data.token : ''),
				});
				if (typeof BX !== 'undefined' && BX.bitrix_sessid) {
					body.set('sessid', BX.bitrix_sessid());
				}
				fetch(url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
					credentials: 'same-origin',
				})
					.then(function (resp) {
						console.log(resp);
						return resp.text();
					})
					.then(function (text) {
						if (text == 'ok') {
							location.href = '/bitrix/admin/vettich.sp3.user.php';
						} else {
							pp_send_message('login_result', { error: text });
						}
					})
					.catch(function () {
						pp_send_message('login_result', { error: true });
					});
			},
		};

		window.addEventListener('load', function () {
			if (typeof VettichSP3 !== 'undefined' && typeof VettichSP3.initIframe === 'function') {
				VettichSP3.initIframe(iframeConfig);
			}
		});
	}

	window.VettichSP3ViewEmbed = {
		init: init,
	};
})(window);
