<?php
namespace vettich\sp3;

use vettich\sp3\devform;

class TextProcessor
{
	public static function replace($text, $fields, $isEmptyReplace=true)
	{
		$result = $text;
		$macros = self::parse($text);

		if (isset($macros['square'])) {
			foreach ((array)$macros['square'] as $k => $v) {
				$t = self::blockReplace($k, $fields);
				$macros['square'][$k] = $t;
			}
			$result = str_replace(array_keys((array)$macros['square']), array_values((array)$macros['square']), $result);
		}

		if (isset($macros['figure'])) {
			foreach ((array)$macros['figure'] as $k => $v) {
				$t = self::replace(devform\Module::mb_substr($k, 1, -1), $fields, false);
				if (strpos($t, '#') === false) {
					$macros['figure'][$k] = str_replace("\n", '#BR#', $t);
				}
			}
			$result = str_replace(array_keys((array)$macros['figure']), array_values((array)$macros['figure']), $result);
		}

		foreach ((array)$macros['simple'] as $key => $value) {
			$macro = self::macroExplode($key);
			if (empty($macro[0])) {
				$macro[0] = 'THIS';
			}
			$macros['simple'][$key] = self::macroValue($macro, $fields);
			if (!$isEmptyReplace && empty($macros['simple'][$key])) {
				$macros['simple'][$key] = $key;
			}
		}
		if (isset($macros['simple']['#BR#'])) {
			$macros['simple']['#BR#'] = '#BR#';
		}

		$result = str_replace("\n", '', $result);
		$result = str_replace(array_keys((array)$macros['simple']), array_values((array)$macros['simple']), $result);
		$result = str_replace('#BR#', "\n", $result);
		return $result;
	}

	protected static function mbStringToArray($string)
	{
		$strlen = devform\Module::mb_strlen($string);
		while ($strlen) {
			$array[] = devform\Module::mb_substr($string, 0, 1, "UTF-8");
			$string = devform\Module::mb_substr($string, 1, $strlen, "UTF-8");
			$strlen = devform\Module::mb_strlen($string);
		}
		return $array;
	}

	protected static function parse($text)
	{
		$startStack = [];
		$macros_start = -1;
		$macros = [];
		$statusStack = [];
		$status = 'simple';
		$stext = self::mbStringToArray($text);
		$i = 0;
		foreach ((array)$stext as $ch) {
			if ($status == 'simple') {
				if ($ch == '#') {
					if ($macros_start < 0) {
						array_push($startStack, $macros_start);
						$macros_start = $i;
					} else {
						$macro = devform\Module::mb_substr($text, $macros_start, $i - $macros_start + 1);
						$macros['simple'][$macro] = '';
						$macros_start = array_pop($startStack) ?: -1;
						$status = array_pop($statusStack) ?: 'simple';
					}
				} elseif ($ch == '{') { // begin figure block
					array_push($statusStack, $status);
					$status = 'figureblock';
					array_push($startStack, $macros_start);
					$macros_start = $i;
				} elseif ($ch == '[') { // begin square block
					array_push($statusStack, $status);
					$status = 'squareblock';
					array_push($startStack, $macros_start);
					$macros_start = $i;
				} elseif (ctype_space($ch) && $macros_start >= 0) {
					$macros_start = array_pop($startStack) ?: -1;
				}
			} elseif ($status == 'figureblock') {
				if ($ch == '}') { // end figure block
					$macro = devform\Module::mb_substr($text, $macros_start, $i - $macros_start + 1);
					$macros['figure'][$macro] = '';
					$macros_start = array_pop($startStack) ?: -1;
					$status = array_pop($statusStack) ?: 'simple';
				}
			} elseif ($status == 'squareblock') {
				if ($ch == ']') {
					$status = 'squareblock2';
				}
			} elseif ($status == 'squareblock2') {
				if ($ch == ']') {
					$macro = devform\Module::mb_substr($text, $macros_start, $i - $macros_start + 1);
					$macros['square'][$macro] = '';
					$macros_start = array_pop($startStack) ?: -1;
					$status = array_pop($statusStack) ?: 'simple';
				}
			}
			$i++;
		}
		return $macros;
	}

	protected static function macroExplode($macro)
	{
		$tmp = devform\Module::mb_substr($macro, 1, -1);
		return explode('.', $tmp);
	}

	public static function macroValue($macro, $fields, $isCreateLink=true, $raw=false)
	{
		if (!is_array($macro)) {
			$macro = [$macro];
		}
		if (count($macro) == 0) {
			return 0;
		}

		if ($macro[0] == 'BR') {
			return '#BR#';
		}
		if ($macro[0] == 'DETAIL_PAGE_URL' or $macro[0] == 'LIST_PAGE_URL') {
			return self::createLink($fields[$macro[0]], $fields);
		}
		if ($macro[0] == 'DETAIL_PICTURE' or $macro[0] == 'PREVIEW_PICTURE') {
			$files = self::getFileNames($fields[$macro[0]], false);
			if (!empty($files)) {
				if ($isCreateLink) {
					return self::createLink($files[0], $fields);
				} else {
					return $files[0];
				}
			}
			return $fields[$macro[0]];
		}

		if (strpos($macro[0], 'PROPERTY_') === 0) {
			$value = self::propertyValue($macro, $fields, $isCreateLink, $raw);
			if ($value !== null) {
				return $value;
			}
		}

		$value = devform\Module::arrayChain($fields, $macro);
		if (is_array($value)) {
			$macro[] = 'VALUES';
			$value = devform\Module::arrayChain($fields, $macro);
			if ($value === null) {
				array_pop($macro);
				$macro[] = 'VALUE';
				$value = devform\Module::arrayChain($fields, $macro);
			}
		}
		if (!$raw && is_array($value)) {
			if (count($value) == 0) {
				return "";
			}
			// if first item is array then $value is array of array
			// and we don't parse it
			if (is_array(reset($value))) {
				return "";
			}
			return implode(', ', $value);
		}
		return $value;
	}

	protected static function propertyValue($macro, $fields, $isCreateLink=true, $raw=false)
	{
		$type = $fields[$macro[0]]['PROPERTY_TYPE'];
		if (empty($type)) {
			return null;
		}

		$isMulti = $fields[$macro[0]]['MULTIPLE'] == 'Y';
		if ($type == 'L') {
			if ($isMulti) {
				return implode(', ', $fields[$macro[0]]['VALUES_XML_ID']);
			}
			return $fields[$macro[0]]['VALUE_ENUM'];
		}

		if ($type == 'E' or $type == 'G') {
			if (!empty($macro[1]) && $macro[1] == 'REF') {
				$id = $fields[$macro[0]]['VALUES'] ?: $fields[$macro[0]]['VALUE'];
				$iblockId = $fields[$macro[0]]['LINK_IBLOCK_ID'];
				if ($fields[$macro[0]]['PROPERTY_TYPE'] == 'E') {
					$elems = IBlockHelpers::iblockElemId($id, $iblockId);
				} else {
					$elems = IBlockHelpers::iblockSection($id, $iblockId);
				}
				if ($raw) {
					return $elems;
				}
				if ($isMulti) {
					$result = [];
					foreach ((array)$elems as $ar) {
						$result[] = self::macroValue(array_slice($macro, 2), $ar, $isCreateLink, $raw);
					}
					if ($raw) {
						return $result;
					} else {
						return implode(', ', $result);
					}
				} else {
					return self::macroValue(array_slice($macro, 2), $elems, $isCreateLink, $raw);
				}
			} elseif (!empty($macro[1]) && isset($fields[$macro[0]][$macro[1]])) {
				return $fields[$macro[0]][$macro[1]];
			} else {
				$res = $fields[$macro[0]]['VALUES'] ? $fields[$macro[0]]['VALUES'] : $fields[$macro[0]]['VALUE'];
				if ($raw) {
					return $res;
				} else {
					return is_array($res) ? implode(', ', $res) : $res;
				}
			}
		}

		if ($type == 'F') {
			$k = isset($macro[1]) ? $macro[1] : 'VALUE';
			if ($k == 'VALUE' || $k == 'VALUES') {
				if ($isMulti) {
					$res = [];
					foreach ((array)$fields[$macro[0]]['VALUES'] as $val) {
						if ($isCreateLink) {
							$res[] = self::createLink(\CFile::GetPath($val), $fields);
						} else {
							$res[] = \CFile::GetPath($val);
						}
					}
					if ($raw) {
						return $res;
					} else {
						return implode(' ', $res);
					}
				}
				if ($isCreateLink) {
					return self::createLink(\CFile::GetPath($fields[$macro[0]]['VALUE']), $fields);
				} else {
					return \CFile::GetPath($fields[$macro[0]]['VALUE']);
				}
			}
		}
		return null;
	}

	protected function blockReplace($text, $fields, $isCreateLink=true)
	{
		$result = '';
		if (!preg_match(
			'/\[(\w+)\s+([\w\#_\.]+)\](.*)\[\/(\w+)\]/is',
			$text,
			$matches
		)) {
			return '';
		}
		$macro = self::macroExplode($matches[2]);
		$field = self::macroValue($macro, $fields, $isCreateLink, $raw=true);
		switch (strtoupper($matches[1])) {
		case 'LIST':
			$macros = self::parse($matches[3]);
			// $result = array();
			$old = $fields['THIS'];
			foreach ((array)$field as $val) {
				$fields['THIS'] = $val;
				$rr = str_replace("\n", '#BR#', self::replace($matches[3], $fields, $isCreateLink));
				$result .= $rr;
				/* $result .= '#BR#'; */
				print_r([$rr]);
			}
			$fields['THIS'] = $old;
			/* $result = implode('#BR#', $result); */
			break;

		case 'TABLE':
			$txt = trim($matches[3]);
			$txt = explode("\n", $txt);
			if (!isset($txt[1])) {
				break;
			}
			$headers = explode('|', $txt[0]);
			$body = explode('|', $txt[1]);
			$columnsWidth = [];
			foreach ((array)$headers as $head) {
				$val = trim(self::replace($head, $fields, $isCreateLink));
				$columnsWidth[] = devform\Module::mb_strlen($val);
				$headerValues[] = $val;
			}
			$len = count($headerValues);
			$j = 0;
			$old = $fields['THIS'];
			foreach ((array)$field as $f) {
				$fields['THIS'] = $f;
				for ($i=0; $i < $len; $i++) {
					$val = trim(self::replace($body[$i], $fields, $isCreateLink));
					if ($columnsWidth[$i] < devform\Module::mb_strlen($val)) {
						$columnsWidth[$i] = devform\Module::mb_strlen($val);
					}
					$bodyValues[$j][$i] = $val;
				}
				$j++;
			}
			$fields['THIS'] = $old;
			$result = '';
			for ($i=0; $i < $len; $i++) {
				$result .= $headerValues[$i] . str_repeat(' ', $columnsWidth[$i] + 2 - devform\Module::mb_strlen($headerValues[$i]));
			}
			$result .= '#BR#';
			foreach ((array)$bodyValues as $body) {
				for ($i=0; $i < $len; $i++) {
					$result .= $body[$i] . str_repeat(' ', $columnsWidth[$i] + 2 - devform\Module::mb_strlen($body[$i]));
				}
				$result .= '#BR#';
			}
			break;
		}
		return $result;
	}

	public static function createLink($slink, $fields)
	{
		$link = $fields['DOMAIN'] ?: $_SERVER['HTTP_HOST'];
		$link .= SITE_DIR;
		if (strpos($link, 'http') !== 0) {
			if ($_SERVER['HTTPS']) {
				$link = 'https://'.$link;
			} else {
				$link = 'http://'.$link;
			}
		}
		if ($link[devform\Module::mb_strlen($link)-1] != '/') {
			$link .= '/';
		}
		if (strpos($slink, '/') === 0) {
			$link .= devform\Module::mb_substr($slink, 1);
		} else {
			$link .= $slink;
		}
		if (!empty($fields['URL_PARAMS'])) {
			$link .= (strpos($link, '?') !== false) ? '&' : '?';
			$link .= self::replace($fields['URL_PARAMS'], $fields);
		}
		return $link;
	}

	public static function getFileNames($arField, $withDocumentRoot=true)
	{
		$arResult = [];
		if (is_array($arField) && $arField['PROPERTY_TYPE'] == 'F') {
			if ($arField['MULTIPLE'] == 'Y') {
				foreach ((array)$arField['VALUES'] as $k=>$arValue) {
					if ($withDocumentRoot) {
						$arResult[] = $_SERVER['DOCUMENT_ROOT'].\CFile::GetPath($arValue);
					} else {
						$arResult[] = \CFile::GetPath($arValue);
					}
				}
			} elseif ($withDocumentRoot) {
				$arResult[] = $_SERVER['DOCUMENT_ROOT'].\CFile::GetPath($arField['VALUE']);
			} else {
				$arResult[] = \CFile::GetPath($arField['VALUE']);
			}
		} else {
			$img_path = \CFile::GetPath($arField);
			if ($img_path != '') {
				if ($withDocumentRoot) {
					$arResult[] = $_SERVER['DOCUMENT_ROOT'].$img_path;
				} else {
					$arResult[] = $img_path;
				}
			}
		}
		return $arResult;
	}
}
