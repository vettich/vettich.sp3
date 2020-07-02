<?php
namespace vettich\sp3;

class Tools
{
	public static function filterByKeys($arr, $keys)
	{
		if (empty($keys)) {
			return [];
		}

		$ret = array_filter($arr, function ($k) use ($keys) {
			return in_array($k, $keys);
		}, ARRAY_FILTER_USE_KEY);
		return $ret;
	}

	public static function filterByUnKeys($arr, $keys)
	{
		if (empty($keys)) {
			return $arr;
		}

		return array_filter($arr, function ($k) use ($keys) {
			return !in_array($k, $keys);
		}, ARRAY_FILTER_USE_KEY);
	}
}
