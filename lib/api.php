<?php
namespace vettich\sp3;

IncludeModuleLangFile(__FILE__);

/**
 * api for service
 */
class Api
{
	const FROM = 'bitrix';
	private $configFile = '';
	private $config = [];

	public function __construct($configFile)
	{
		$this->configFile = $configFile;
		$this->readConfig();
	}

	public function readConfig()
	{
		$data = file_get_contents($this->configFile);
		$conf = json_decode($data, true);
		if (!empty($conf)) {
			$this->config = $conf;
		}
	}

	public function saveConfig()
	{
		$data = json_encode($this->config, JSON_PRETTY_PRINT);
		file_put_contents($this->configFile, $data);
	}

	public function userId()
	{
		return $this->config['user_id'];
	}

	public function token()
	{
		return $this->config['token'];
	}

	public static function toTime($strtime)
	{
		if (empty($strtime)) {
			$strtime = 'now';
		}
		return date(\DateTime::RFC3339_EXTENDED, strtotime($strtime));
	}

	private function setUserData($userId, $token)
	{
		$this->config['user_id'] = $userId;
		$this->config['token'] = $token;
		$this->saveConfig();
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

	private function buildEndpoint($endpoint)
	{
		$url = $this->config['api_uri'];
		$url .= '/'.$this->config['api_version'];
		$url .= '/'.$endpoint;
		return $url;
	}

	private function buildQuery($data)
	{
		if (empty($data)) {
			return '';
		}
		$d = json_encode($data);
		$q = urlencode($d);
		return 'query='.$q;
	}

	private function buildCurl($url, $needAuth, $headers=[])
	{
		if ($needAuth == true) {
			$token = $this->token();
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
			$headers[] = 'Token: '.$this->token();
		}
		if (!empty($headers)) {
			curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
		}
		return $c;
	}

	private function callGet($endpoint, $queries=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'queries' => $queries], ['trace_n' => 3]);
		$url = $this->buildEndpoint($endpoint);
		$q = $this->buildQuery($queries);
		if (!empty($q)) {
			$url .= '?'.$q;
		}
		$c = $this->buildCurl($url, $needAuth);
		if (!$c) {
			return false;
		}
		$result = curl_exec($c);
		curl_close($c);
		return json_decode($result, true);
	}

	private function callPost($endpoint, $data=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'data' => $data], ['trace_n' => 3]);
		$url = $this->buildEndpoint($endpoint);
		$c = $this->buildCurl($url, $needAuth, ['Content-Type: application/json']);
		if (!$c) {
			return false;
		}
		$dataEnc = json_encode($data);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $dataEnc);
		$result = curl_exec($c);
		curl_close($c);
		return json_decode($result, true);
	}

	private function callDelete($endpoint, $queries=[], $needAuth=true)
	{
		Module::log(['endpoint' => $endpoint, 'queries' => $queries], ['trace_n' => 3]);
		$url = $this->buildEndpoint($endpoint);
		$q = $this->buildQuery($queries);
		if (!empty($q)) {
			$url .= '?'.$q;
		}
		$c = $this->buildCurl($url, $needAuth);
		if (!$c) {
			return false;
		}
		curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
		$result = curl_exec($c);
		curl_close($c);
		return json_decode($result, true);
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

	public function login($username, $password)
	{
		$queries = [
			'username' => $username,
			'password' => $password,
		];
		$result = $this->callPost('tokens', $queries, false);
		Module::log($result);
		if (!empty($result['error'])) {
			return $result;
		}

		$this->setUserData($result['response']['user_id'], $result['response']['token']);
		return []; // success
	}

	public function signup($username, $password)
	{
		$queries = [
			'username' => $username,
			'password' => $password,
		];
		$result = $this->callPost('users', $queries, false);
		Module::log($result);
		if (!empty($result['error'])) {
			return $result;
		}
		$this->setUserData($result['response']['user_id'], $result['response']['token']);
		return []; // success
	}

	public function validateToken()
	{
		$token = $this->token();
		if (empty($token)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$result = $this->callGet("tokens/$token/valid", [], false);
		Module::log($result);
		return $result;
	}

	public function me()
	{
		$result = $this->callGet('me');
		return self::resultWrapper($result);
	}

	public function logout()
	{
		$token = $this->token();
		if (empty($token)) {
			return ['error' => ['msg' => 'token is empty']];
		}
		$res = $this->callDelete("tokens/$token", [], false);
		Module::log($res);
		if (empty($res['error'])) {
			$this->setUserData("", "");
		}
		return $res;
	}

	public function connectUrl($accType, $callback)
	{
		$queries = [
			'type' => $accType,
			'callback' => $callback,
		];
		$res = $this->callGet('connect_url', $queries);
		return self::resultWrapper($res);
	}

	public function accountsList()
	{
		$res = $this->callGet('accounts');
		return self::resultWrapper($res);
	}

	public function deleteAccount($id)
	{
		$res = $this->callDelete('accounts/'.$id);
		return self::resultWrapper($res);
	}

	public function postsList($queries=[])
	{
		$queries['filter']['from'] = self::FROM;
		$res = $this->callGet('posts', $queries);
		return self::resultWrapper($res);
	}

	public function createPost($post)
	{
		$post['from'] = self::FROM;
		$res = $this->callPost('posts', $post);
		return self::resultWrapper($res);
	}

	public function updatePost($post)
	{
		$post['from'] = self::FROM;
		$res = $this->callPost('posts/'.$post['id'], $post);
		return self::resultWrapper($res);
	}

	public function getPost($id)
	{
		$res = $this->callGet('posts/'.$id);
		return self::resultWrapper($res);
	}

	public function deletePost($id)
	{
		$res = $this->callDelete('posts/'.$id);
		return self::resultWrapper($res);
	}

	public function uploadFile($filepath, $filename)
	{
		$res = $this->callGet('file_upload_url', ['type' => 'image', 'filename' => $filename]);
		Module::log($res);
		if (!empty($res['error'])) {
			return $res;
		}

		$fileID = $res['response']['file_id'];
		$uploadUrl = $res['response']['url'];
		$data = ['file' => self::filenameWrapper($filepath, $filename)];
		$c = $this->buildCurl($uploadUrl, $needAuth=true, ['Content-Type:multipart/form-data']);
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

	public function getFilesURL($fileIDs)
	{
		if (empty($fileIDs)) {
			return [];
		}
		$queries = ['ids' => $fileIDs];
		$res = $this->callGet('files_url', $queries);
		return self::resultWrapper($res);
	}
}
