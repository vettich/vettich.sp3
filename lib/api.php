<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

/**
 * api for service
 */
class Api
{
	const FROM               = 'bitrix';
	const SERVER_UNAVAILABLE = -11;
	/** Endpoint для функционала выгрузки */
	const UNLOAD_ENDPOINT    = '/bitrix/tools/vettich.sp3.ajax.php?method=unload';
	/** Endpoint для выгрузки из очереди */
	const POST_FROM_QUEUE_ENDPOINT = '/bitrix/tools/vettich.sp3.ajax.php?method=postFromQueue';
	/** Заголовок с одноразовым/короткоживущим hook-токеном (выставляет ParrotPoster при HTTP-вызове Bitrix). */
	public const PARROTPOSTER_HOOK_TOKEN_HEADER = 'X-ParrotPoster-HookToken';
	/** Формат даты в RFC3339. */
	const RFC3339_EXTENDED   = 'Y-m-d\TH:i:s.uP';
	/** Коды ошибок, связанных с сетевыми проблемами. */
	private const CURL_NETWORK_ERRORS = [
		CURLE_COULDNT_RESOLVE_HOST,
		CURLE_COULDNT_CONNECT,
		CURLE_OPERATION_TIMEDOUT,
		CURLE_GOT_NOTHING,
		CURLE_SEND_ERROR,
		CURLE_RECV_ERROR,
		CURLE_SSL_CONNECT_ERROR,
	];

	public static function token()
	{
		return Config::getToken();
	}

	public static function toTime($strtime)
	{
		$nowtime = strtotime('now');
		if (empty($strtime)) {
			$strtime = $nowtime;
		} else {
			$strtime = strtotime($strtime);
			if ($strtime < $nowtime) {
				$strtime = $nowtime;
			}
		}
		return date(self::RFC3339_EXTENDED, $strtime);
	}

	private static function errorMsg($key, $msg)
	{
		Log::debug("$key.$msg");
		$langMess = Module::m("$key.$msg");
		if (empty($langMess)) {
			return $msg;
		}
		return $langMess;
	}

	private static function buildEndpoint($endpoint, $queries = [], ?string $domain = null)
	{
		$domain = $domain ?: DomainSelector::getBestDomain();
		if (empty($domain)) {
			return false;
		}
		$url = $domain . Config::apiUri() . '/' . $endpoint;
		if (!is_array($queries)) {
			$queries = [];
		}
		$queries['lang'] = LANGUAGE_ID == "ru" ? "ru" : "en";
		return $url.'?'.http_build_query($queries);
	}

	private static function buildQuery($data)
	{
		if (empty($data)) {
			return '';
		}
		$d = json_encode($data);
		$q = urlencode($d);
		return ['query' => $q];
	}

	private static function decodeResult($res)
	{
		$newRes = json_decode($res, true);
		// Log::debug($res);
		if ($newRes !== null) {
			return $newRes;
		}
		return [
			'error' => [
				'msg'  => 'server is unavailable',
				'code' => self::SERVER_UNAVAILABLE,
			],
		];
	}

	private static function buildCurl($url, $needAuth, $headers=[])
	{
		if ($needAuth == true) {
			$token = self::token();
			if (empty($token)) {
				return false;
			}
			$headers[] = 'Token: '.$token;
		}
		$headers[] = 'X-PP-Bitrix-Version: '.Module::version();
		if (!function_exists('curl_init')) {
			return false;
		}
		$c = curl_init();
		if (!$c) {
			return false;
		}
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		if (!empty($headers)) {
			curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		}
		return self::applyProxy($c);
	}

	private static function isNetworkCurlError(int $errno): bool
	{
		return $errno !== 0 && in_array($errno, self::CURL_NETWORK_ERRORS, true);
	}

	private static function doRequest(string $method, string $endpoint, array $params = [], bool $needAuth = true)
	{
		$queries = $params['queries'] ?? [];
		$data = $params['data'] ?? [];
		$headers = $params['headers'] ?? [];
		$flatQueryParams = !empty($params['flat_query_params']);

		$passes = [
			['forceRefresh' => false],
			['forceRefresh' => true],
		];

		$lastDecoded = null;

		foreach ($passes as $pass) {
			$domains = DomainSelector::getPriorityDomains($pass['forceRefresh']);
			if (empty($domains)) {
				$best = DomainSelector::getBestDomain();
				if (!empty($best)) {
					$domains = [$best];
				}
			}

			foreach ($domains as $domain) {
				$q = $flatQueryParams
					? (is_array($queries) ? $queries : [])
					: self::buildQuery($queries);
				$url = self::buildEndpoint($endpoint, $q, $domain);
				if (empty($url)) {
					continue;
				}

				$c = self::buildCurl($url, $needAuth, $headers);
				if (!$c) {
					return false;
				}

				$methodUpper = strtoupper($method);
				if ($methodUpper === 'POST') {
					$dataEnc = json_encode($data);
					curl_setopt($c, CURLOPT_POST, true);
					curl_setopt($c, CURLOPT_POSTFIELDS, $dataEnc);
				} elseif ($methodUpper === 'DELETE') {
					curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
				}

				$result   = curl_exec($c);
				$errno    = (int)curl_errno($c);
				$error    = curl_error($c);
				$httpCode = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
				curl_close($c);

				if ($result === false || self::isNetworkCurlError($errno)) {
					DomainSelector::markDomainError($domain);
					Log::debug(['api_network_error' => ['domain' => $domain, 'endpoint' => $endpoint, 'errno' => $errno, 'error' => $error]]);
					continue;
				}

				if (!empty($params['plain_text_ok'])) {
					if ($httpCode === 200 && strcasecmp(trim((string)$result), 'OK') === 0) {
						return ['response' => true];
					}
					$lastDecoded = self::decodeResult($result);
					return $lastDecoded;
				}

				$decoded = self::decodeResult($result);
				$lastDecoded = $decoded;
				return $decoded;
			}
		}

		if (is_array($lastDecoded)) {
			return $lastDecoded;
		}

		return [
			'error' => [
				'msg'  => 'server is unavailable',
				'code' => self::SERVER_UNAVAILABLE,
			],
		];
	}

	private static function applyProxy($c)
	{
		// $proxy = Config::get('proxy');
		// if (empty($proxy)) {
		// 	return $c;
		// }
		//
		// $proxyData = explode('@', $proxy);
		// if (count($proxyData) > 1) {
		// 	curl_setopt($c, CURLOPT_PROXY, $proxyData[1]);
		// 	curl_setopt($c, CURLOPT_PROXYUSERPWD, $proxyData[0]);
		// } else {
		// 	curl_setopt($c, CURLOPT_PROXY, $proxy);
		// }

		return $c;
	}

	private static function callGet($endpoint, $queries=[], $needAuth=true)
	{
		Log::debug(['endpoint' => $endpoint, 'queries' => $queries]);
		return self::doRequest('GET', $endpoint, ['queries' => $queries], $needAuth);
	}

	private static function callPost($endpoint, $data=[], $needAuth=true)
	{
		Log::debug(['endpoint' => $endpoint, 'data' => $data]);
		return self::doRequest('POST', $endpoint, ['data' => $data, 'headers' => ['Content-Type: application/json']], $needAuth);
	}

	private static function callDelete($endpoint, $queries=[], $needAuth=true)
	{
		Log::debug(['endpoint' => $endpoint, 'queries' => $queries]);
		return self::doRequest('DELETE', $endpoint, ['queries' => $queries], $needAuth);
	}

	private static function filenameWrapper($filepath, $filename)
	{
		if (version_compare(PHP_VERSION, '5.6.0', '<')) {
			return '@'.$filepath;
		}
		return new \CURLFile($filepath, mime_content_type($filepath), $filename);
	}

	private static function resultWrapper($res)
	{
		Log::debug($res);
		if (!is_array($res)) {
			return ['error' => ['msg' => 'result is empty']];
		}
		return $res;
	}

	public static function ping()
	{
		$result = self::callGet('ping', [], false);
		return $result;
	}

	/**
	 * Значение hook-токена из текущего HTTP-запроса (cron / очередь PP → Bitrix).
	 */
	public static function hookTokenFromIncomingRequest(): string
	{
		if (!empty($_SERVER['HTTP_X_PARROTPOSTER_HOOKTOKEN'])) {
			return trim((string)$_SERVER['HTTP_X_PARROTPOSTER_HOOKTOKEN']);
		}
		return '';
	}

	/**
	 * Проверка hook-токена запросом к API ParrotPoster (тот же Token из config + заголовок hook-токена).
	 * При ошибке сети или API — false (не выполняем удалённый хук).
	 *
	 * @param string|null $hookToken если null — из заголовка {@see PARROTPOSTER_HOOK_TOKEN_HEADER}
	 */
	public static function checkHookToken(?string $hookToken = null): bool
	{
		$hookToken = $hookToken ?? self::hookTokenFromIncomingRequest();
		if ($hookToken === '' || strlen($hookToken) > 4096) {
			return false;
		}
		$siteToken = self::token();
		if ($siteToken === null || $siteToken === '') {
			return false;
		}
		$endpoint = 'check-hook-token/'.rawurlencode($hookToken);
		$res      = self::doRequest('GET', $endpoint, ['queries' => [], 'plain_text_ok' => true], false);
		if ($res === false || !is_array($res) || !empty($res['error'])) {
			return false;
		}
		if (isset($res['response']) && $res['response'] === true) {
			return true;
		}
		if (isset($res['response']) && is_array($res['response']) && !empty($res['response']['valid'])) {
			return true;
		}
		return false;
	}

	public static function validateToken()
	{
		$token = self::token();
		if (empty($token)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$result = self::callGet("tokens/$token/valid", [], false);
		Log::debug($result);
		return $result;
	}

	public static function me()
	{
		$result = self::callGet('me');
		return self::resultWrapper($result);
	}

	public static function logout()
	{
		$token = self::token();
		if (empty($token)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$res = self::callDelete("tokens/$token", [], false);
		Log::debug($res);
		if (empty($res['error'])) {
			Config::purgeTokenFromAllStorages();
		}
		return $res;
	}

	public static function connectUrl($accType, $callback)
	{
		$queries = [
			'type'     => $accType,
			'callback' => $callback,
		];
		$res = self::callGet('connect_url', $queries);
		return self::resultWrapper($res);
	}

	public static function connect($accType, $fields)
	{
		$queries = [
			'type'   => $accType,
			'fields' => $fields,
		];
		$res = self::callPost('connect', $queries);
		return self::resultWrapper($res);
	}

	// not support yet
	public static function connectInsta($uname, $pass, $proxy, $code)
	{
		$q = [
			'username' => trim($uname),
			'password' => trim($pass),
			'proxy'    => trim($proxy),
			'code'     => trim($code),
		];
		$res = self::callPost('connect-insta', $q);
		return self::resultWrapper($res);
	}

	public static function accountsList($filter=[])
	{
		$res = self::callGet('accounts', ['filter' => $filter]);
		return self::resultWrapper($res);
	}

	public static function getAccount($id)
	{
		$res = self::callGet('accounts/'.$id);
		return self::resultWrapper($res);
	}

	public static function deleteAccount($id)
	{
		$res = self::callDelete('accounts/'.$id);
		return self::resultWrapper($res);
	}

	public static function postsList($queries=[])
	{
		$queries['filter']['from'] = self::FROM;
		$res                       = self::callGet('posts', $queries);
		return self::resultWrapper($res);
	}

	public static function createPost($post)
	{
		$res = self::callPost('posts', $post);
		return self::resultWrapper($res);
	}

	public static function updatePost($post)
	{
		$res = self::callPost('posts/'.$post['id'], $post);
		return self::resultWrapper($res);
	}

	public static function getPost($id)
	{
		$res = self::callGet('posts/'.$id);
		return self::resultWrapper($res);
	}

	public static function deletePost($id)
	{
		$res = self::callDelete('posts/'.$id);
		return self::resultWrapper($res);
	}

	public static function uploadFile($filepath, $filename)
	{
		if (substr($filepath, 0, 4) == 'http') {
			return self::uploadFileFromUrl($filepath);
		}

		// step 1: get upload url and new file id
		Log::debug([$filepath, $filename]);
		$res = self::callGet('file_upload_url', ['type' => 'image', 'filename' => $filename]);
		// Log::debug($res);
		if (!empty($res['error'])) {
			return $res;
		}

		// step 2: upload file
		$fileID    = $res['response']['file_id'];
		$uploadUrl = $res['response']['url'];
		$data      = ['file' => self::filenameWrapper($filepath, $filename)];
		$c         = self::buildCurl($uploadUrl, $needAuth=true, ['Content-Type:multipart/form-data']);
		if (!$c) {
			return ['error' => ['msg' => 'failed file upload']];
		}
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $data);
		// get more debug
		$verbose = fopen('php://temp', 'w+');
		curl_setopt($c, CURLOPT_VERBOSE, true);
		curl_setopt($c, CURLOPT_STDERR, $verbose);
		$result = curl_exec($c);
		curl_close($c);
		rewind($verbose);
		$verboseLog = stream_get_contents($verbose);

		// step 3: check file status, it must be 'uploaded'
		$res = self::callGet("files/$fileID/status");
		if ($res['response'] && $res['response']['status'] != 'uploaded') {
			Log::error([$result, $verboseLog]);
			return ['error' => ['msg' => 'failed file upload']];
		}

		// step 4: return result via uploaded file id
		$res = json_decode($result, true);
		if (empty($res['error'])) {
			return ['response' => ['file_id' => $fileID]];
		}
		return $res;
	}

	public static function uploadFileFromUrl($url)
	{
		$data = [
			'url' => $url,
		];
		$res = self::callPost('files/from_url', $data);
		return self::resultWrapper($res);
	}

	public static function getFilesURL($fileIDs)
	{
		if (empty($fileIDs)) {
			return [];
		}
		$queries = ['ids' => $fileIDs];
		$res     = self::callGet('files_url', $queries);
		return self::resultWrapper($res);
	}

	public static function getTariff($id)
	{
		if (empty($id)) {
			return [];
		}
		$res = self::callGet("tariffs/$id");
		return self::resultWrapper($res);
	}

	public static function tariffsList()
	{
		$res = self::callGet("tariffs");
		return self::resultWrapper($res);
	}

	public static function setUserTariff($tariffID)
	{
		$params = ['tariff_id' => $tariffID];
		$res    = self::callPost('me/set-tariff', $params);
		return self::resultWrapper($res);
	}

	public static function createTransaction($params)
	{
		$res = self::callPost('transactions', $params);
		return self::resultWrapper($res);
	}

	public static function sendLog($logData)
	{
		$logData['from'] = self::FROM;
		self::callPost('logs', $logData, false);
	}

	public static function createCron()
	{
		$params = ['url' => TextProcessor::createLink(self::UNLOAD_ENDPOINT, [])];
		$res    = self::callPost('cron', $params);
		return self::resultWrapper($res);
	}

	public static function deleteCron()
	{
		$params = ['url' => TextProcessor::createLink(self::UNLOAD_ENDPOINT, [])];
		$res    = self::callDelete('cron', $params);
		return self::resultWrapper($res);
	}

	/**
	 * Регистрация URL публикации элемента в очереди ParrotPoster (POST post-queue).
	 *
	 * @return bool true при ответе API без error
	 */
	public static function addPostToQueue($ID, $IBLOCK_ID)
	{
		$id       = (int)$ID;
		$iblockId = (int)$IBLOCK_ID;
		if ($id <= 0 || $iblockId <= 0) {
			Log::debug(['addPostToQueue' => 'invalid_args', 'ID' => $ID, 'IBLOCK_ID' => $IBLOCK_ID]);
			return false;
		}
		if (empty(self::token())) {
			Log::debug(['addPostToQueue' => 'empty_token', 'element_id' => $id]);
			return false;
		}

		$queuePath   = self::POST_FROM_QUEUE_ENDPOINT
			.'&ID='.rawurlencode((string)$id)
			.'&IBLOCK_ID='.rawurlencode((string)$iblockId);
		$callbackUrl = TextProcessor::createLink($queuePath, []);

		Log::debug(['endpoint' => 'post-queue', 'element_id' => $id, 'iblock_id' => $iblockId]);

		$res = self::doRequest('POST', 'post-queue', [
			'queries'           => ['url' => $callbackUrl],
			'flat_query_params' => true,
			'data'              => [],
			'headers'           => ['Content-Type: application/json'],
		], true);

		if ($res === false || !is_array($res) || !empty($res['error'])) {
			if (is_array($res) && !empty($res['error'])) {
				Log::debug(['addPostToQueue' => 'api_error', 'response' => $res]);
			}
			return false;
		}
		return true;
	}

	/**
	 * POST /api/graphql с перебором доменов.
	 *
	 * @return array{data: array}|array{error: array{msg: string, code?: int}}
	 */
	private static function doGraphqlRequest(string $query, array $variables = [], ?string $bearerToken = null, string $logLabel = 'graphql'): array
	{
		if (!function_exists('curl_init')) {
			return ['error' => ['msg' => 'curl is unavailable']];
		}

		$payload = ['query' => $query];
		if ($variables !== []) {
			$payload['variables'] = $variables;
		}
		$body = json_encode($payload, JSON_UNESCAPED_UNICODE);

		$headers = [
			'Content-Type: application/json',
			'X-PP-Bitrix-Version: ' . Module::version(),
		];
		if ($bearerToken !== null && $bearerToken !== '') {
			$headers[] = 'Authorization: Bearer ' . $bearerToken;
		}

		$passes = [
			['forceRefresh' => false],
			['forceRefresh' => true],
		];
		$lastDecoded = null;

		foreach ($passes as $pass) {
			$domains = DomainSelector::getPriorityDomains($pass['forceRefresh']);
			if (empty($domains)) {
				$best = DomainSelector::getBestDomain();
				if (!empty($best)) {
					$domains = [$best];
				}
			}
			foreach ($domains as $domain) {
				$url = rtrim($domain, '/') . Config::graphqlApiUri();
				$c   = curl_init();
				if (!$c) {
					continue;
				}
				curl_setopt($c, CURLOPT_URL, $url);
				curl_setopt($c, CURLOPT_POST, true);
				curl_setopt($c, CURLOPT_POSTFIELDS, $body);
				curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
				$c = self::applyProxy($c);

				$result = curl_exec($c);
				$errno  = (int)curl_errno($c);
				$error  = curl_error($c);
				curl_close($c);

				if ($result === false || self::isNetworkCurlError($errno)) {
					DomainSelector::markDomainError($domain);
					Log::debug([$logLabel.'_network' => ['domain' => $domain, 'errno' => $errno, 'error' => $error]]);
					continue;
				}

				$decoded = json_decode($result, true);
				if (!is_array($decoded)) {
					continue;
				}
				$lastDecoded = $decoded;

				if (!empty($decoded['errors'])) {
					$msg = $decoded['errors'][0]['message'] ?? 'graphql error';
					return ['error' => ['msg' => $msg]];
				}

				return ['data' => $decoded['data'] ?? []];
			}
		}

		if (is_array($lastDecoded) && !empty($lastDecoded['errors'])) {
			$msg = $lastDecoded['errors'][0]['message'] ?? 'graphql error';
			return ['error' => ['msg' => $msg]];
		}

		return [
			'error' => [
				'msg'  => 'server is unavailable',
				'code' => self::SERVER_UNAVAILABLE,
			],
		];
	}

	/**
	 * Сессионный ключ для iframe (GraphQL mutation issueSessionKey по сохранённому access token).
	 *
	 * @return array{token: string}|array{error: array{msg: string, code?: int}}
	 */
	public static function issueSessionKey()
	{
		$bearer = self::token();
		if (empty($bearer)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$readOnly = !Module::hasGroupWrite();
		$query    = 'mutation IssueSessionKey($readOnly: Boolean) { issueSessionKey(readOnly: $readOnly) { token } }';
		$res      = self::doGraphqlRequest($query, ['readOnly' => $readOnly], $bearer, 'issueSessionKey');
		if (!empty($res['error'])) {
			return $res;
		}
		$sessionToken = $res['data']['issueSessionKey']['token'] ?? null;
		if (empty($sessionToken)) {
			return ['error' => ['msg' => 'no session token in response']];
		}
		return ['token' => $sessionToken];
	}

	/**
	 * OAuth: обмен code из callback URL на токен (GraphQL mutation exchange_code, без Bearer).
	 *
	 * @return array{token: string}|array{error: array{msg: string, code?: int}}
	 */
	public static function exchangeAuthCode(string $code): array
	{
		$code = trim($code);
		if ($code === '') {
			return ['error' => ['msg' => 'code is empty']];
		}
		if (strlen($code) > 8192) {
			return ['error' => ['msg' => 'code is too long']];
		}

		$query = 'mutation ExchangeAuthCode($code: String!) { exchangeCode(code: $code) { token } }';
		$res   = self::doGraphqlRequest($query, ['code' => $code], null, 'exchangeAuthCode');
		if (!empty($res['error'])) {
			return $res;
		}
		$data    = $res['data'];
		$payload = $data['exchangeCode'] ?? null;
		if (!is_array($payload)) {
			return ['error' => ['msg' => 'invalid exchange response']];
		}
		$token = $payload['token'] ?? null;
		if ($token === null || $token === '') {
			return ['error' => ['msg' => 'no token in response']];
		}

		return [
			'token' => (string)$token,
		];
	}
}
