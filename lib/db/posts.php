<?php
namespace vettich\sp3\db;

use vettich\sp3\Module;
use vettich\sp3\Api;

class Posts extends \vettich\sp3\devform\data\ArrayList
{
	const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

	public function __construct($args = [])
	{
		$args['on afterSave']       = [$this, 'afterSave'];
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
			$res          = Api::getPost($this->filter['id']);
			$res          = Module::convertToSiteCharset($res);
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

	public function getList($params=[], $onlyPosts=true)
	{
		$queries = ['sort' => []];
		if (!empty($params['order'])) {
			foreach ($params['order'] as $by => $order) {
				$queries['sort'][$by] = $order;
				/* $queries['sort.by'] = $by; */
				/* $queries['sort.order'] = strtoupper($order); */
			}
		}
		if (!empty($params['paging'])) {
			$queries['paging'] = $params['paging'];
		}
		$res = Api::postsList($queries);
		$res = Module::convertToSiteCharset($res);
		if ($onlyPosts) {
			return $res['response']['posts'];
		}
		return $res['response'];
	}

	public function afterFillValues($obj, $arValues)
	{
		if (empty($this->values['networks']['accounts'])) {
			return ['error' => Module::m('ERROR_ACCOUNTS_EMPTY')];
		}
		if (!empty($this->values['fields']['images'])) {
			$errors = self::checkImages($this->values['fields']['images']);
			if (!empty($errors)) {
				return ['error' => $errors];
			}
			$this->values['fields']['images'] = self::uploadImages($this->values['fields']['images']);
		}
		if (empty($this->values['fields']['images']) &&
			empty($this->values['fields']['text']) &&
			empty($this->values['fields']['link']) &&
			empty($this->values['fields']['tags'])) {
			return ['error' => Module::m('ERROR_EMPTY_POST_FIELDS')];
		}
		$this->values['publish_at'] = Api::toTime($this->values['publish_at']);
	}

	public function afterSave($obj, $arValues)
	{
		$utf8Values = Module::convertToUtf8($this->values);
		if (empty($utf8Values['id'])) {
			$res = Api::createPost($utf8Values);
		} else {
			$res = Api::updatePost($utf8Values);
		}
		if (empty($res['error'])) {
			return true;
		}
		return ['error' => Module::convertToSiteCharset($res['error']['msg'])];
	}

	public function delete($name, $value)
	{
		Api::deletePost($id=$value);
	}

	private static function checkImages($imagesField)
	{
		$errors = [];
		foreach ($imagesField as $image) {
			$img_path = self::getImagePath($image);
			if (!isset($errors['format']) && !self::checkImageMime($img_path)) {
				$errors['format'] = Module::m('POST_PICTURE_ERROR');
			}
			if (!isset($errors['size']) && !self::checkImageSize($img_path)) {
				$errors['size'] = Module::m('POST_PICTURE_SIZE_ERR');
			}
			if (isset($errors['format']) && isset($errors['size'])) {
				break;
			}
		}
		return $errors;
	}

	public static function checkImageMime($imagePath)
	{
		$mime = mime_content_type($imagePath);
		return in_array($mime, ['image/png', 'image/jpeg', 'image/webp']);
	}

	public static function checkImageSize($imagePath)
	{
		return filesize($imagePath) <= self::MAX_IMAGE_SIZE;
	}

	private static function uploadImages($imagesField)
	{
		$images = [];
		foreach ($imagesField as $image) {
			$img_path = self::getImagePath($image);
			$res = Api::uploadFile($img_path, Module::convertToUtf8(basename($img_path)));
			if (empty($res['error'])) {
				$images[] = $res['response']['file_id'];
			}
			self::deleteTmpImage($image);
			if (count($images) >= 10) {
				break;
			}
		}
		return $images;
	}

	private static function getImagePath($image)
	{
		if (is_array($image) && isset($image['tmp_name'])) {
			$pathinfo = \Bitrix\Main\UI\Uploader\Uploader::getPaths($image['tmp_name']);
			$img_path = $pathinfo['tmp_name'];
		} elseif(is_string($image)) {
			$img_path = $_SERVER['DOCUMENT_ROOT'].$image;
		}
	}

	private static function deleteTmpImage($image)
	{
		if (is_array($image) && isset($image['tmp_name'])) {
			$pathinfo = \Bitrix\Main\UI\Uploader\Uploader::getPaths($image['tmp_name']);
			if (!empty($pathinfo)) {
				DeleteDirFilesEx(dirname($pathinfo['tmp_name']));
			}
		}
	}
}
