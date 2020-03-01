<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

/**
 * api for service
 */
class Api
{
	const FROM = 'bitrix';
	const SERVER_UNAVAILABLE = -11;

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
		if (empty($strtime)) {
			$strtime = 'now';
		}
		return date(\DateTime::RFC3339_EXTENDED, strtotime($strtime));
	}

	private static function setUserData($userId, $token)
	{
		Config::setConfig([
			'user_id' => $userId,
			'token' => $token,
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

	private static function buildEndpoint($endpoint)
	{
		$url = Config::get('api_uri');
		$url .= '/'.Config::get('api_version');
		$url .= '/'.$endpoint;
		return $url;
	}

	private static function buildQuery($data)
	{
		if (empty($data)) {
			return '';
		}
		$d = json_encode($data);
		$q = urlencode($d);
		return 'query='.$q;
	}

	private static function decodeResult($res)
	{
		$newRes = json_decode($res, true);
		Module::log([$res, $newRes]);
		if ($newRes !== null) {
			return $newRes;
		}
		return [
			'error' => [
				'msg' => 'server is unavailable',
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
		#curl_setopt($c, CURLOPT_VERBOSE, true);
		#curl_setopt($c, CURLOPT_STDERR, VETTICH_SP3_DIR.'/logerr.txt');
		#curl_setopt($c, CURLINFO_HEADER_OUT, true);
		if ($needAuth == true) {
			$headers[] = 'Token: '.self::token();
		}
		if (!empty($headers)) {
			curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		}
		return $c;
	}

	private static function callGet($endpoint, $queries=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'queries' => $queries], ['trace_n' => 3]);
		$url = self::buildEndpoint($endpoint);
		$q = self::buildQuery($queries);
		if (!empty($q)) {
			$url .= '?'.$q;
		}
		$c = self::buildCurl($url, $needAuth);
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
		$c = self::buildCurl($url, $needAuth, ['Content-Type: application/json']);
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
		$url = self::buildEndpoint($endpoint);
		$q = self::buildQuery($queries);
		if (!empty($q)) {
			$url .= '?'.$q;
		}
		$c = self::buildCurl($url, $needAuth);
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
		];
		$result = self::callPost('users', $queries, false);
		Module::log($result);
		if (!empty($result['error'])) {
			return $result;
		}
		self::setUserData($result['response']['user_id'], $result['response']['token']);
		return []; // success
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
			'type' => $accType,
			'callback' => $callback,
		];
		$res = self::callGet('connect_url', $queries);
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
		$res = self::callGet('posts', $queries);
		return self::resultWrapper($res);
	}

	public static function createPost($post)
	{
		$post['from'] = self::FROM;
		$res = self::callPost('posts', $post);
		return self::resultWrapper($res);
	}

	public static function updatePost($post)
	{
		$post['from'] = self::FROM;
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
		Module::log([$filepath, $filename]);
		$res = self::callGet('file_upload_url', ['type' => 'image', 'filename' => $filename]);
		Module::log($res);
		if (!empty($res['error'])) {
			return $res;
		}

		$fileID = $res['response']['file_id'];
		$uploadUrl = $res['response']['url'];
		$data = ['file' => self::filenameWrapper($filepath, $filename)];
		$c = self::buildCurl($uploadUrl, $needAuth=true, ['Content-Type:multipart/form-data']);
		if (!$c) {
			return ['error' => ['msg' => 'failed file upload']];
		}
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($c);
		curl_close($c);

		$res = json_decode($result, true);
		Module::log([$result, $res, empty($res['error']), $fileID]);
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
		$res = self::callGet('files_url', $queries);
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
		$res = self::callPost('me/set-tariff', $params);
		return self::resultWrapper($res);
	}

	public static function createTransaction($params)
	{
		$res = self::callPost('transactions', $params);
		return self::resultWrapper($res);
	}
}
