<?php
/*
RealmEye API utility manager, manages loading configuration and app logger
*/
declare(strict_types=1);

require_once('classes/Logger.php');

class RealmEyeAPIUtils {
	private $config = null;
	public $logger = null;

	public function __construct(string $config_path = 'config.ini') {
		$this->config = $this->read_config($config_path);

		$logger_configs = [];
		if ($this->config) {
			$log_configs = [
				'log_level',
				'log_file_name',
			];
			foreach ($log_configs as $log_config) {
				if (array_key_exists($log_config, $this->config)) {
					$logger_configs[$log_config] = $this->config[$log_config];
				}
			}
		}
		$this->logger = new Logger($logger_configs);
	}

	// Allow retrieval of $config
	public function __get($name) {
		if ($name === 'config') {
			return $this->$name;
		} else {
			throw new Exception(
				'Undefined property via __get(): ' . $name,
				E_USER_NOTICE
			);
			return null;
		}
	}

	// Accepts a path to a configuration file to load
	// Returns null if path is not present, not readable, or fails parsing
	// Returns `parse_ini_file()` on path if present and valid
	private function read_config(string $config_file): ?array {
		$config = [];
		if (file_exists($config_file) && is_readable($config_file)) {
			$config = parse_ini_file($config_file) ?: null;
		} else {
			$config = null;
		}
		return $config;
	}

	// Static functions

	// Accepts an array
	// Returns an array with keys matching the filter in $_GET['filter']
	// That is, the returned array will have only keys matching the terms in
	// $_GET['filter'] if it is an intersecting filter, or only keys NOT
	// matching those in $_GET['filter'] if the first character is '-',
	// indicating a differential filter
	public static function create_filter(array $to_be_filtered): array {
		$filter = self::create_filter_base($to_be_filtered);
		if (!empty($_GET['filter'])) {
			$filter_terms = $_GET['filter'];
			if ($filter_terms[0] === '-') {
				$mode = 'diff';
				$filter_terms = substr($filter_terms, 1);
			} else {
				$mode = 'intersect';
			}
			// must be assoc. array; values don't matter, hence array_fill_keys
			$filter_terms = array_fill_keys(explode(' ', $filter_terms), null);
			$filter_method = 'array_'.$mode.'_key';
			$filter = $filter_method($filter, $filter_terms);
		}
		return $filter;
	}

	// Accepts an array
	// Returns a one-dimensional associative array of the argument array's keys
	// as keys and values (eg. ['a'] becomes ['a'=>'a'])
	// Note: This is performed recursively on passed argument, so
	// multidimensional arrays' keys will be included
	public static function create_filter_base(array $to_be_filtered): array {
		$filter_base = [];
		foreach ($to_be_filtered as $key => $value) {
			if (!is_int($key)) {
				$filter_base[$key] = $key;
			}
			if (is_array($value)) {
				$filter_base = array_merge(
					$filter_base,
					self::create_filter_base($value)
				);
			}
		}
		return $filter_base;
	}

	// Performs `array_intersect_key()` on $array1 and any arrays it contains
	// using $array2 as the compare base
	public static function array_intersect_key_recursive(
		array $array1, array $array2
	): array {
		$output = [];
		foreach ($array1 as $key => $value) {
			if (is_int($key) || in_array($key, $array2)) {
				if (is_array($value)) {
					$array_value = self::array_intersect_key_recursive(
						$value,
						$array2
					);
					if (!is_int($key) || !empty($array_value)) {
						$output[$key] = $array_value;
					}
				} else {
					$output[$key] = $value;
				}
			}
		}
		return $output;
	}

	// Accepts same arguments as `ksort()`
	// Returns a recursively-`ksort()`ed $array
	public static function ksort_recursive(
		array $array,
		int $sort_flags = SORT_REGULAR
	): array {
		foreach ($array as $key => $value) {
			if (is_array($array[$key])) {
				$array[$key] = self::ksort_recursive($array[$key], $sort_flags);
			}
		}
		ksort($array, $sort_flags);
		return $array;
	}

	// Accepts a libXMLError object
	// Returns a boolean indicating whether the passed libxml error object is an
	// invalid tag warning
	public static function libxml_error_is_tag_warning(
		libXMLError $error
	): bool {
		$words = explode(' ', $error->message);
		return (
			// error code is "invalid tag"
			$error->code === 801 &&
			// error message contains "Tag ... invalid"
			(
				array_search('invalid', $words, true) -
				array_search('Tag', $words, true) === 2
			)
		);
	}
}
