<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

class View
{
	/**
	 * Origin'ы фронта PP для проверки postMessage (scheme + host [+ port]).
	 *
	 * @return string[]
	 */
	private static function allowed_iframe_origins(): array
	{
		$seen = [];
		foreach (Config::domains() as $d) {
			if (!is_string($d) || $d === '') {
				continue;
			}
			$p = @parse_url(rtrim($d, '/'));
			if (empty($p['scheme']) || empty($p['host'])) {
				continue;
			}
			$origin = $p['scheme'].'://'.$p['host'];
			if (!empty($p['port'])) {
				$origin .= ':'.$p['port'];
			}
			$seen[$origin] = true;
		}

		return array_keys($seen);
	}

	private static function iframe_config($path) {
		$session = Api::issueSessionKey();
		$token   = '';
		if (empty($session['error']) && !empty($session['token'])) {
			$token = $session['token'];
		}

		return json_encode(array(
			'container' => '.pp-iframe-container',
			'endpoints' => Config::domains(),
			'path' => Config::frontBaseUri() .'/'. $path,
			'pingPath' => Config::availableCheckUri(),
			'token' => $token,
			'lang' => LANGUAGE_ID,
			'session_result' => $session,
			'moduleReadOnly' => !Module::hasGroupWrite() ? 1 : 0,
		), JSON_UNESCAPED_UNICODE);
	}

	private static function menu_list() {
		return array(
			'tariffs' => '/bitrix/admin/vettich.sp3.tariffs.php'
		);
	}

	public static function embed_front($path)
	{
		\CJSCore::Init(['vettich_sp3_script']);
?>
		<script>
			const PP_ALLOWED_ORIGINS = <?php echo json_encode(self::allowed_iframe_origins(), JSON_UNESCAPED_SLASHES) ?>;

			function ppOriginAllowed(origin) {
				return PP_ALLOWED_ORIGINS.indexOf(origin) !== -1;
			}

			window.addEventListener('message', (event) => {
				if (!ppOriginAllowed(event.origin)) {
					return;
				}
				if (!event.data || typeof event.data.type !== 'string') {
					return;
				}
				const fn = pp_message_commands[event.data.type];
				fn && fn(event.data);
			});

			const pp_message_commands = {
				resize(data) {
					const iframe = document.getElementById('pp-iframe');
					iframe.style.height = `${data.height}px`;
				},
				prepare_callback() {
					pp_send_message('prepare_callback_result', {
						url: location.href
					});
				},
				goto(data) {
					const items = <?php echo json_encode(self::menu_list()) ?>;
					const url = items[data.url] ?? data.url;
					location.href = url
				},
				login(data) {
					const url = '/bitrix/tools/vettich.sp3.ajax.php';
					const body = new URLSearchParams({
						method: 'auth',
						token: String(data.token ?? ''),
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
						.then((resp) => {
							console.log(resp);
							resp.text()
								.then((data) => {
									if (data == 'ok') {
										location.href = '/bitrix/admin/vettich.sp3.user.php';
									} else {
										pp_send_message('login_result', {error: data});
									}
								})
								.catch(() => pp_send_message('login_result', {error: true}))
						})
						.catch(() => pp_send_message('login_result', {error: true}))
				},
			};

			function pp_send_message(type, data) {
				const msg = {
					type,
					...(data || {})
				};
				const iframe = document.getElementById('pp-iframe');
				if (!iframe || !iframe.contentWindow || !iframe.src) {
					return;
				}
				let targetOrigin;
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
		</script>
		<div class="pp-iframe-container"><?php echo Module::m('IFRAME_LOADING') ?></div>
		<script>
			window.addEventListener('load', () => {
				VettichSP3.initIframe(<?php echo self::iframe_config($path) ?>)
			})
		</script>
		<style>
			#pp-iframe {
				width: calc(100% + 16px);
				height: 76vh;
				margin-left: -16px;
			}
			@media screen and (max-width: 782px) {
				#pp-iframe {
					width: calc(100% + 10px);
					margin-left: -10px;
				}
			}
			.vettich-sp3-iframe-load-error {
				max-width: 48rem;
				line-height: 1.45;
			}
			.vettich-sp3-iframe-load-error code {
				font-size: 0.9em;
			}
			.vettich-sp3-csp-hosts {
				margin: 0.5em 0 1em 1.25em;
			}
		</style>
<?php
	}
}
