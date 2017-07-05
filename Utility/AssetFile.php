<?php
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class AssetFile {
	public static function getPath() {
		if ($args = func_get_args()) {
			$path = self::_getPath($args);
		} else {
			$path = '';
		}
		return self::fixSlash($path);
	}

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

	public static function fixSlash($path) {
		$replaceSlash = DS;
		$findSlash = $replaceSlash == '/' ? '\\' : '/';
		return str_replace($findSlash, $replaceSlash, $path);
	}

	public static function folderElement($element) {
		return !empty($element) ? Folder::slashTerm($element) : $element;
	}

	public static function copy($srcFile, $dstFile) {
		if (!is_file($dstFile)) {
			new File($dstFile, true);
		}
		$file = new File($srcFile);
		return $file->copy($dstFile, true);
	}
}