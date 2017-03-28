<?php
//require_once('../Vendor/PhpClosure.php');
App::uses('Folder', 'Utility');
App::uses('PhpClosure', 'CakeAssets.Vendor');
App::uses('PluginConfig', 'CakeAssets.Utility');
PluginConfig::init('CakeAssets');

class AssetMinify {
	public $forceOverwrite = false;
	
	const PLUGIN_NAME = 'CakeAssets';

/**
 * Tracks the asset files
 *
 * @var array
 **/
	private $fileCache = [];
	
	function __construct() {
		if (isset($_GET['clearCache'])) {
			$this->forceOverwrite = true;
			$this->clearCache('-1 week');
		}
	}
	
/**
 * Resets the cache file array
 *
 * @return void;
 **/
	public function resetCache() {
		$this->fileCache = [];
	}

	public function minify($files, $type = 'css') {
		$return = [];
		$minFiles = [];
		foreach ($files as $file => $config) {
			if (is_numeric($file)) {
				$file = $config;
				$config = [];
			}
			$filePath = $this->getPath($file, $type);
			if (isset($this->fileCache[$type][$filePath])) {
				continue;
			}
			$this->fileCache[$type][$filePath] = $filePath;

			if (strpos($filePath, '//') === false && is_file($filePath)) {
				$minFiles[] = $file;
			} else {
				if (!empty($minFiles)) {
					$return[] = $this->getCacheFile($minFiles, $type);
				}
				$return[] = $file;
				$minFiles = [];
			}
		}
		if (!empty($minFiles)) {
			$return[] = $this->getCacheFile($minFiles, $type);
			$minFiles = [];
		}
		return $return;
	}

	public function getCacheDir($type, $forWeb = true, $filename = null) {
		$dir = Configure::read('CakeAssets.dirs.' . $type);
		
		if (!$forWeb) {
			$root = Configure::read('CakeAssets.root');
			$dir = $root . $type . DS . str_replace('/', DS, $dir);
		}

		/*
		if ($forWeb) {
			//$dir = 'Layout.min/';
			$dir = self::PLUGIN_NAME . "./$type-min/";
		} else {
			$dir = $this->_getPluginDir() . 'webroot' . DS . $type . DS . 'min' . DS;
		}
		*/
		if (!empty($filename)) {
			$dir .= $filename;
		}
		return $dir;	
	}
	

	public function clearCache($age = null) {
		$this->_clearCache($age, $this->getCacheDir('js', false));
		$this->_clearCache($age, $this->getCacheDir('css', false));
	}

/**
 * Auto-deletes older files in cache folder
 *
 * @include int|null $age the expire time
 * @include string|null $dir the directory where to look. Defaults to root cache directory
 * @include bool $deleteOnEmpty If directory is empty and set to true, deletes the directory
 * @return int Remaining file count
 **/	
	private function _clearCache($age = null, $dir = null, $deleteOnEmpty = false) {
		$cutoff = !empty($age) ? strtotime($age) : true;
		$handle = opendir($dir);
		$fileCount = 0;
		while (false !== ($entry = readdir($handle))) {
			if ($entry == '.' || $entry == '..' || $entry == 'empty') {
				continue;
			}
			$path = $dir . $entry;
			if (is_dir($path)) {
				$fileCount += $this->clearCache($age, $path . DS, true);
			} else {
				if ($cutoff === true || filemtime($path) < $cutoff) {
					unlink($path);
				} else {
					$fileCount++;
				}
			}
		}
		if ($deleteOnEmpty && $fileCount == 0) {
			rmdir($dir);
		}
		closedir($handle);
		return $fileCount;
	}
	
/**
 * Finds the full path of the cached minified file
 *
 * @param array $files The list of files involved in the cache file
 * @param string $type The asset type
 * @return string The filepath
 **/
	private function getCacheFile($files, $type) {
		$cacheFilepath = $this->getCacheFilepath($files, $type);
		$lastModified = $this->getLastModified($files, $type);
		if ($this->forceOverwrite || !is_file($cacheFilepath) || filemtime($cacheFilepath) < $lastModified) {
			$this->buildCacheFile($cacheFilepath, $files, $type);
		}
		$filepath = $this->getCacheFilepath($files, $type, true) . '?m=' . $lastModified;
		return $filepath;
	}
	
/**
 * Finds full path of a Cake asset
 *
 * @param string $file The file name from AssetHelper
 * @param string $type The type of asset (JS or CSS)
 * @param bool $dirOnly If true, returns only the path to the directory of the file
 * @param bool $forWeb If true, returns the path formatted for using in a web URL
 *
 * @return string The path to the file
 **/
	private function getPath($file, $type, $dirOnly = false, $forWeb = false) {
		if (strpos($file, '//') !== false) {
			return $file;
		}

		$ds = $forWeb ? '/' : DS;
		$notDs = $forWeb ? DS : '/';
		
		/*
		$base = Router::url('/');
		if ($base != '/' && strpos($file, $base) === 0) {
			$file = substr($file, strlen($base));
		}
		*/

		$oFile = $file;
		$plugin = null;
		if (preg_match('/^[A-Z]/', $file)) {
			list($plugin, $file) = pluginSplit($oFile);
		}
		
		// Finds absolute file names
		if (substr($file, 0, 1) == '/') {
			$base = explode('/', substr(Router::url('/'), 1, -1));
			$parts = explode('/', $file);
			$slice = false;

			// If Plugin is not found but the files exists in a Plugin directory
			if (empty($plugin) && $parts[2] == $type && (empty($base) || $base[0] != $parts[1])) {
				$plugin = Inflector::camelize($parts[1]);
				$slice = 3;
			}

			// Checks if the file has the same CakePhp base
			$baseMatch = true;
			foreach ($base as $k => $basePart) {
				if ($basePart != $parts[$k+1]) {
					$baseMatch = false;
					break;
				}
			}

			// Checks to make sure absolute path fits into the Cake infrastructure
			if ($baseMatch) {
				$keyOffset = count($base); 	// Offsets by the amount of base folders
				if ($parts[$keyOffset + 1] == $type || $parts[$keyOffset + 2] == $type) {
					if ($parts[$keyOffset + 2] == $type) {
						$plugin = Inflector::camelize($parts[$keyOffset + 1]);
						$slice = $keyOffset + 3;
					} else {
						$slice = $keyOffset + 2;
					}
				}
			} else if ($parts[1] == $type) {
				// /css/style.css (no Base)
				$slice = 2;
			}

			if ($slice) {
				$file = implode('/', array_slice($parts, $slice));
			}
		}

		if ($forWeb) {
			$root = Router::url('/');
			if (!empty($plugin)) {
				$root .= sprintf('%s/', Inflector::underscore($plugin));
			}
		} else {
			$root = empty($plugin) ? WWW_ROOT : $this->_getPluginDir($plugin) . 'webroot' . $ds;
		}
			
		$path = $root . $type . $ds . $file;

		if (substr($path, -1 * strlen($type)) != $type) {
			$path .= ".$type";
		}
		if ($dirOnly) {
			$path = explode($ds, $path);
			array_pop($path);
			$path = implode($ds, $path) . $ds;
		}

		return str_replace($notDs, $ds, $path);
	}
	
	// Finds the full path of where the cached file will be stored
	private function getCacheFilepath($files, $type, $forWeb = false) {
		return $this->getCacheDir($type, $forWeb, $this->getFilename($files, $type));
	}

	
	// Finds a hashed value for the cached file
	private function getFilename($files, $type) {
		return md5(implode('|', $files)) . '.' . $type;
	}	
	
	// Finds the most recent last time a group of files was modified
	private function getLastModified($files, $type) {
		$lastModified = 0;
		foreach ($files as $file) {
			$path = $this->getPath($file, $type);
			if (is_file($path)) {
				if (($filemtime = filemtime($path)) > $lastModified) {
					$lastModified = $filemtime;
				}
			}
		}
		return $lastModified;
	}
	
	// Creates a cached file including all files
	private function buildCacheFile($filename, $files, $type) {
		$filename = trim($filename);
		if (!($dirname = dirname($filename))) {
			throw new Exception("Invalid directory");
		}
		$file = substr($filename, strlen($dirname) + 1);

		if (!is_dir($dirname)) {
			if (!mkdir($dirname, 0755, true)) {
				throw new Exception("Could not create directory: $dirname");
			}
			$dir = Folder::slashTerm($dirname);
			file_put_contents($dir . 'empty', '');
			file_put_contents($dir . '.gitignore', "*\r\n!empty");
		}

		if (!($fp = fopen($filename, 'w'))) {
			throw new Exception("Could not open file: $filename");
		}

		if ($type == 'js') {
			$PhpClosure = new PhpClosure();
		}

		$fileHeader = '';
		$fileContent = '';
		foreach ($files as $file) {
			$path = $this->getPath($file, $type);

			if (!empty($PhpClosure)) {
				$PhpClosure->add($path);
			} else {
				if (is_file($path)) {
					$content = $this->_fileGetContents($path);

					//Strip comments
					$content = preg_replace('!/\*.*?\*/!s', '', $content);
					
					//Update CSS
					if ($type == 'css') {
						$content = $this->_replaceRelativeUrls($content, $this->getPath($file, $type, true, true));
					}
					if (!empty($fileContent)) {
						$fileContent .= "\n";
					}

					$fileContent .= "/*$file*/\n";
					$fileContent .= $content;
				}	
			}
		}
		if (!empty($PhpClosure)) {
			fwrite($fp, $PhpClosure->compile());
		} else {
			fwrite($fp, $fileHeader . $fileContent);
		}
		fclose($fp);
	}

	private function _fileGetContents($path) {
		$content = file_get_contents($path);
		$content = mb_convert_encoding($content, 'UTF-8', 
			mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
		$content = str_replace("\xEF\xBB\xBF",'',$content); // Remove Byte Order Mark
		return $content;
	}

	private function _replaceRelativeUrls($content, $webDir) {
		$replace = [];

		//Looks for relative url calls
		if (preg_match_all('#(url\([\'"]*)((\.\./)+)*([^/][^/\.:]*[/\.])([^\)]*\))#', $content, $matches)) {
			foreach ($matches[0] as $k => $match) {
				$dir = $webDir;
				if (!empty($matches[2][$k])) {	//Detects "../" and moves the root directory up those levels
					$up = substr_count($matches[2][$k], '../');
					$dir = $this->getParentDir($webDir, '/', $up);
				}
				$replace[$match] = $matches[1][$k] . $dir . $matches[4][$k] . $matches[5][$k];
			}
		}
		if (preg_match_all('/@import[^;]+;/', $content, $matches)) {
			foreach ($matches[0] as $match) {
				$fileHeader .= $match;
				$replace[$match] = '';
			}
		}
		if (!empty($replace)) {
			$content = str_replace(array_keys($replace), array_values($replace), $content, $count);
		}
		return $content;
	}
	
	private function getParentDir($dir, $ds = DS, $levels = 1) {
		$dir = explode($ds, substr($dir, 0, -1));
		$pre = '';
		for ($i = 0; $i < $levels; $i++) {
			if (count($dir) > 0) {
				array_pop($dir);
			} else {
				$pre .= '..' . $ds;
			}
		}
		return $pre . implode($ds, $dir) . $ds;
	}
	
	private function _getPluginDir($plugin = null) {
		if (empty($plugin)) {
			$plugin = self::PLUGIN_NAME;
		}
		return APP. 'Plugin' . DS . $plugin . DS;
	}

	public function debug($msg) {
		if (!empty($_GET['debug_output'])) {
			debug($msg);
		}
	}
}