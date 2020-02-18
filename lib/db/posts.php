<?php
namespace vettich\sp3\db;

use vettich\sp3\Module;
use vettich\sp3\Api;

class Posts extends \vettich\devform\data\ArrayList
{
	private $filter = [];
	private $inited = false;

	public function __construct($args = [])
	{
		$args['on afterSave'] = [$this, 'afterSave'];
		$args['on afterFillValues'] = [$this, 'afterFillValues'];
		parent::__construct($args);
		if (isset($args['filter'])) {
			$this->filter = $args['filter'];
		} elseif (!empty($_GET['id'])) {
			$this->filter = ['id' => $_GET['id']];
		}
	}

	public function get($name, $default=null)
	{
		if (!$this->inited) {
			if (empty($this->filter['id'])) {
				return $default;
			}
			$res = Api::getPost($this->filter['id']);
			$res = Module::convertToSiteCharset($res);
			$this->values = $res['response'];
			$this->inited = true;
		}
		if (!$this->exists($name)) {
			return $default;
		}
		if ($this->trimPrefix) {
			$name = $this->trim($name);
		}
		return self::arrayChain($this->values, self::strToChain($name), $default);
	}

	public function getList($params=[])
	{
		$queries = ['sort' => []];
		if (!empty($params['order'])) {
			foreach ($params['order'] as $by => $order) {
				$queries['sort'][$by] = $order;
				/* $queries['sort.by'] = $by; */
				/* $queries['sort.order'] = strtoupper($order); */
			}
		}
		$res = Api::postsList($queries);
		$res = Module::convertToSiteCharset($res);
		$posts = $res['response']['posts'];
		return $posts;
	}

	public function afterFillValues($obj, $arValues)
	{
		if (empty($this->values['networks']['accounts'])) {
			return ['error' => Module::m('ERROR_ACCOUNTS_EMPTY')];
		}
		if (!empty($this->values['fields']['images'])) {
			$images = [];
			foreach ($this->values['fields']['images'] as $image) {
				$pathinfo = \Bitrix\Main\UI\Uploader\Uploader::getPaths($image["tmp_name"]);
				$res = Api::uploadFile($pathinfo['tmp_name'], $image['name']);
				if (empty($res['error'])) {
					$images[] = $res['response']['file_id'];
				}
				DeleteDirFilesEx(dirname($pathinfo['tmp_name']));
			}
			$this->values['fields']['images'] = $images;
		}
		$this->values['publish_at'] = Api::toTime($this->values['publish_at']);
	}

	public function afterSave($obj, $arValues)
	{
		Module::log($this->values);
		$utf8Values = Module::convertToUtf8($this->values);
		if (empty($utf8Values['id'])) {
			$res = Api::createPost($utf8Values);
		} else {
			$res = Api::updatePost($utf8Values);
		}
		if (empty($res['error'])) {
			return true;
		}
		return ['error' => $res['error']['msg']];
	}

	public function delete($name, $value)
	{
		Api::deletePost($id=$value);
	}
}
