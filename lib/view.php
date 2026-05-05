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
		$ppUnavailable = !empty($session['error']['code'])
			&& (int)$session['error']['code'] === Api::SERVER_UNAVAILABLE;

		return array(
			'container' => '.pp-iframe-container',
			'endpoints' => Config::domains(),
			'path' => Config::frontBaseUri() .'/'. $path,
			'pingPath' => Config::availableCheckUri(),
			'token' => $token,
			'lang' => LANGUAGE_ID,
			'session_result' => $session,
			'pp_unavailable' => $ppUnavailable,
			'moduleReadOnly' => !Module::hasGroupWrite() ? 1 : 0,
			'debug' => Config::iframeEmbedDebug(),
		);
	}

	private static function menu_list() {
		return array(
			'tariffs' => '/bitrix/admin/vettich.sp3.tariffs.php'
		);
	}

	public static function embed_front($path)
	{
		\CJSCore::Init(['vettich_sp3_view_embed']);
		$embedInit = json_encode(array(
			'allowedOrigins' => self::allowed_iframe_origins(),
			'menuItems' => self::menu_list(),
			'iframeConfig' => self::iframe_config($path),
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
		<script>
			BX.ready(function () {
				if (window.VettichSP3ViewEmbed && typeof window.VettichSP3ViewEmbed.init === 'function') {
					window.VettichSP3ViewEmbed.init(<?php echo $embedInit ?>);
				}
			});
		</script>
		<div class="pp-iframe-container"></div>
<?php
	}
}
