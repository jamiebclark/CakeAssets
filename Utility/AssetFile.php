<?php
/**
 * Utility to extends Folder and File utilities to assist with locating asset files within directories
 *
 **/

App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class AssetFile {
/**
 * Returns a path based on a string or an array of items
 *
 * @param mixed A list of elements to be combined into one path string
 * 		Arrays will be concatenated. Nulls will be skipped
 * @return string A path
 **/
	public static function getPath() {
		if ($args = func_get_args()) {
			$path = self::_getPath($args);
		} else {
			$path = '';
		}
		return self::fixSlash($path);
	}

/**
 * Recursive inner function to assist with getPath()
 *
 * @param mixed A list of elements to be combined into one path string
 * 		Arrays will be concatenated. Nulls will be skipped
 * @return string A path
 **/
	public static function _getPath($args, $path = '') {
		if (is_array($args)) {
			foreach ($args as $arg) {
				$path = self::_getPath($arg, $path);
			}
			return $path;
		} else {
			return self::folderElement($path) . $args;
		}
	}

/**
 * Ensures that all slashes are the correct direction for the given OS
 * 
 * @param string The current path
 * @return string An updated version of the path with corrected slashes
 **/
	public static function fixSlash($path) {
		$replaceSlash = DS;
		$findSlash = $replaceSlash == '/' ? '\\' : '/';
		return str_replace($findSlash, $replaceSlash, $path);
	}

/**
 * Returns a single folder element in a path
 *
 * @param string|null $element The part of the folder path
 * @return If it's not empty, returns the string, otherwise, return null
 **/
	public static function folderElement($element) {
		return !empty($element) ? Folder::slashTerm($element) : $element;
	}

/**
 * Copies a file to a new destination
 * 
 * @param string $srcFile The source file
 * @param string $dstFile The destination location
 * @return bool True if success, false if not
 **/
	public static function copy($srcFile, $dstFile) {
		if (!is_file($dstFile)) {
			new File($dstFile, true);
		}
		$file = new File($srcFile);
		return $file->copy($dstFile, true);
	}
}