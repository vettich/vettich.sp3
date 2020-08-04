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
	const UNLOAD_ENDPOINT    = '/bitrix/tools/vettich.sp3.ajax.php?method=unload';

	public static function userId()
	{
		return Config::get('user_id');
	}

	public static function token()
	{
		return Config::get('token');
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
		return date(\DateTime::RFC3339_EXTENDED, $strtime);
	}

	private static function setUserData($userId, $token)
	{
		Config::setConfig([
			'user_id' => $userId,
			'token'   => $token,
		]);
	}

	private static function errorMsg($key, $msg)
	{
		Module::log("$key.$msg");
		$langMess = Module::m("$key.$msg");
		if (empty($langMess)) {
			return $msg;
		}
		return $langMess;
	}

	private static function buildEndpoint($endpoint, $queries = [])
	{
		$url = Config::get('api_uri');
		$url .= '/'.$endpoint;
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
		Module::log($res);
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
			$headers[] = 'Token: '.self::token();
		}
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
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		if (!empty($headers)) {
			curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		}
		return $c;
	}

	private static function callGet($endpoint, $queries=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'queries' => $queries], ['trace_n' => 3]);
		$q   = self::buildQuery($queries);
		$url = self::buildEndpoint($endpoint, $q);
		$c   = self::buildCurl($url, $needAuth);
		if (!$c) {
			return false;
		}
		$result = curl_exec($c);
		curl_close($c);
		return self::decodeResult($result);
	}

	private static function callPost($endpoint, $data=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'data' => $data], ['trace_n' => 3]);
		$url = self::buildEndpoint($endpoint);
		$c   = self::buildCurl($url, $needAuth, ['Content-Type: application/json']);
		if (!$c) {
			return false;
		}
		$dataEnc = json_encode($data);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $dataEnc);
		$result = curl_exec($c);
		curl_close($c);
		return self::decodeResult($result);
	}

	private static function callDelete($endpoint, $queries=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'queries' => $queries], ['trace_n' => 3]);
		$q   = self::buildQuery($queries);
		$url = self::buildEndpoint($endpoint, $q);
		$c   = self::buildCurl($url, $needAuth);
		if (!$c) {
			return false;
		}
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
		$result = curl_exec($c);
		curl_close($c);
		return self::decodeResult($result);
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
		Module::log($res, ['trace_n' => 3]);
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

	public static function login($username, $password)
	{
		$queries = [
			'username' => $username,
			'password' => $password,
			'from'     => self::FROM,
		];
		$result = self::callPost('tokens', $queries, false);
		Module::log($result);
		if (!empty($result['error'])) {
			return $result;
		}

		self::setUserData($result['response']['user_id'], $result['response']['token']);
		return []; // success
	}

	public static function signup($username, $password)
	{
		$queries = [
			'username' => $username,
			'password' => $password,
			'from'     => self::FROM,
		];
		$result = self::callPost('users', $queries, false);
		Module::log($result);
		if (!empty($result['error'])) {
			return $result;
		}
		self::setUserData($result['response']['user_id'], $result['response']['token']);
		return []; // success
	}

	public static function forgotPassword($username, $callback_url)
	{
		$q = [
			'username'     => $username,
			'callback_url' => $callback_url,
			'from'         => self::FROM,
		];
		$result = self::callPost('passwords/forgot', $q, false);
		return self::resultWrapper($result);
	}

	public static function resetPassword($token, $password)
	{
		$queries = [
			'token'    => $token,
			'password' => $password,
		];
		$result = self::callPost('passwords/new', $queries, false);
		return self::resultWrapper($result);
	}

	public static function validateToken()
	{
		$token = self::token();
		if (empty($token)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$result = self::callGet("tokens/$token/valid", [], false);
		Module::log($result);
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
		Module::log($res);
		if (empty($res['error'])) {
			self::setUserData("", "");
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
		// step 1: get upload url and new file id
		Module::log([$filepath, $filename]);
		$res = self::callGet('file_upload_url', ['type' => 'image', 'filename' => $filename]);
		Module::log($res);
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
		curl_setopt($handle, CURLOPT_VERBOSE, true);
		curl_setopt($handle, CURLOPT_STDERR, $verbose);
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
}
