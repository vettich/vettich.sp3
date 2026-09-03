<?php
namespace vettich\sp3;

class Tools
{
	public static function filterByKeys($arr, $keys)
	{
		if (empty($keys) || !$arr) {
			return [];
		}

		$ret = array_filter($arr, function ($k) use ($keys) {
			return in_array($k, $keys);
		}, ARRAY_FILTER_USE_KEY);
		return $ret;
	}

	public static function filterByUnKeys($arr, $keys)
	{
		if (empty($keys) || !$arr) {
			return $arr;
		}

		return array_filter($arr, function ($k) use ($keys) {
			return !in_array($k, $keys);
		}, ARRAY_FILTER_USE_KEY);
	}

	public static function array_in_array($needle, $haystack) 
	{
		if(!is_array($needle)) {
			return false;
		}
		foreach($needle as $v) {
			if (in_array($v, $haystack)) {
				return true;
			}
		}
		return false;
	}

	public static function timezoneFromUtcOffset($offset)
	{
		list($hours, $minutes) = explode(':', $offset);

		$seconds = $hours * 60 * 60 + $minutes * 60;
		$tz      = timezone_name_from_abbr('', $seconds, false);
		return $tz;
	}

	public static $weekdays = ['MON', 'TUE', 'WEN', 'THU', 'FRI', 'SAT', 'SUN'];

	public static function getCurrentWeekday()
	{
		return self::$weekdays[date('N')-1];
	}

	// https://www.php.net/manual/ru/dateinterval.format.php#121237
	public static function getTotalInterval($interval, $type)
	{
		switch ($type) {
		case 'years':
			return $interval->format('%Y');
			break;

		case 'months':
			$years  = $interval->format('%Y');
			$months = 0;
			if ($years) {
				$months += $years*12;
			}
			$months += $interval->format('%m');
			return $months;
			break;

		case 'days':
			return $interval->format('%a');
			break;

		case 'hours':
			$days  = $interval->format('%a');
			$hours = 0;
			if ($days) {
				$hours += 24 * $days;
			}
			$hours += $interval->format('%H');
			return $hours;
			break;

		case 'minutes':
			$days    = $interval->format('%a');
			$minutes = 0;
			if ($days) {
				$minutes += 24 * 60 * $days;
			}
			$hours = $interval->format('%H');
			if ($hours) {
				$minutes += 60 * $hours;
			}
			$minutes += $interval->format('%i');
			return $minutes;
			break;

		case 'seconds':
			$days    = $interval->format('%a');
			$seconds = 0;
			if ($days) {
				$seconds += 24 * 60 * 60 * $days;
			}
			$hours = $interval->format('%H');
			if ($hours) {
				$seconds += 60 * 60 * $hours;
			}
			$minutes = $interval->format('%i');
			if ($minutes) {
				$seconds += 60 * $minutes;
			}
			$seconds += $interval->format('%s');
			return $seconds;
			break;

		case 'milliseconds':
			$days    = $interval->format('%a');
			$seconds = 0;
			if ($days) {
				$seconds += 24 * 60 * 60 * $days;
			}
			$hours = $interval->format('%H');
			if ($hours) {
				$seconds += 60 * 60 * $hours;
			}
			$minutes = $interval->format('%i');
			if ($minutes) {
				$seconds += 60 * $minutes;
			}
			$seconds += $interval->format('%s');
			$milliseconds = $seconds * 1000;
			return $milliseconds;
			break;

		default:
			return null;
		}
	}

	/**
	 * Encode array for hidden form field (JSON + htmlspecialcharsEx).
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function encodeHiddenArray($value)
	{
		if (!is_array($value)) {
			$value = [];
		}
		return htmlspecialcharsEx(\Bitrix\Main\Web\Json::encode($value));
	}

	/**
	 * Decode hidden form field: JSON first, then safe PHP serialize legacy.
	 *
	 * @param mixed $raw
	 * @return array
	 */
	public static function decodeHiddenArray($raw)
	{
		if (is_array($raw)) {
			return $raw;
		}
		$s = htmlspecialcharsBack((string)$raw);
		if ($s === '' || $s === 'null') {
			return [];
		}
		try {
			$decoded = \Bitrix\Main\Web\Json::decode($s);
			if (is_array($decoded)) {
				return $decoded;
			}
		} catch (\Exception $ex) {
			// fall through to legacy PHP serialize
		}
		return self::unserializeArray($s);
	}

	/**
	 * Safe PHP unserialize into array only (no object instantiation).
	 *
	 * @param mixed $value
	 * @return array
	 */
	public static function unserializeArray($value)
	{
		if (!is_string($value) || $value === '') {
			return [];
		}
		if (function_exists('CheckSerializedData') && !CheckSerializedData($value)) {
			return [];
		}
		$data = @unserialize($value, ['allowed_classes' => false]);
		return is_array($data) ? $data : [];
	}

	/**
	 * MIME-тип файла без обязательного расширения fileinfo.
	 *
	 * @return string|null
	 */
	public static function getFileMime($path)
	{
		if (empty($path) || !\is_readable($path)) {
			return null;
		}
		if (\class_exists('\finfo', false)) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mime  = $finfo->file($path);
			if ($mime !== false && $mime !== '') {
				return $mime;
			}
		}
		if (\function_exists('mime_content_type')) {
			$mime = \mime_content_type($path);
			if ($mime !== false) {
				return $mime;
			}
		}
		if (\function_exists('getimagesize')) {
			$info = @\getimagesize($path);
			if ($info !== false && !empty($info['mime'])) {
				return $info['mime'];
			}
		}
		return null;
	}
}
