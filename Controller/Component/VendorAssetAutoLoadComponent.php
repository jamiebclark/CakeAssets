<?php
/**
 * Copies assets from vendor folders into the webroot folder so they are publicly accessible
 *
 **/

App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('AssetFile', 'CakeAssets.Utility');

class VendorAssetAutoLoadComponent extends Component {
	public $controller;
	public $settings = [];

	private $_types = ['css', 'js', 'files'];

	public function __construct(ComponentCollection $collection, $settings = []) {
		// Configuration
		$default = [
			/**
			 * Include Asset files
			 *
			 * Use the format ['filename'] or ['src/sourceFileName' => 'dst/destinationFileName']
			 **/
			// Any CSS files
			'css' => [],
			// Any JS files
			'js' => [],
			// Any additional files
			'files' => [],

			/**
			 * Add the sources where to look
			 *
			 **/
			'src' => [
				ROOT . DS . 'node_modules' . DS,	// Node
				APP . 'Vendor' . DS,				// Composer
			],

			/**
			 * The root destionation
			 * It will also append $ASSET_TYPE/vendor to the destination directory
			 **/
			'dst' => APP .  WEBROOT_DIR,
		];
		$this->settings = array_merge($default, (array) $settings);
	}

	public function initialize(Controller $controller) {
		parent::initialize($controller);
		$this->autoload();
	}

/**
 * Looks through the source folders for changes and copies new files and folders
 *
 * @return null;
 **/
	public function autoload() {
		$files = [];
		foreach ($this->_types as $assetType) {
			if (!empty($this->settings[$assetType])) {
				foreach ($this->settings[$assetType] as $relativeSrcFilePath => $relativeDstFilePath) {
					if (is_numeric($relativeSrcFilePath)) {
						$relativeSrcFilePath = $relativeDstFilePath;
					}
					$srcPaths = $this->settings['src'];
					if (!is_array($srcPaths)) {
						$srcPaths = [$srcPaths];
					}
					foreach ($srcPaths as $srcPath) {
						$srcFilePath = AssetFile::getPath($srcPath, $relativeSrcFilePath);
						$dstFilePath = $this->getDstDir($assetType, $relativeDstFilePath);
						if (is_file($srcFilePath)) {
							$this->copyFile($srcFilePath, $dstFilePath);
							break;
						} else if (is_dir($srcFilePath)) {
							$this->copyDir($srcFilePath, $dstFilePath);
							break;
						}
					}
				}
			}
		}
	}

/**
 * Copies an entire directory into a destination directory
 * Only copies a file if it is newer
 *
 * @param string $srcFolder The source folder
 * @param string $dstFolder The destination folder
 * @param bool $force If true, then copy the files no matter what
 * @return null;
 **/
	private function copyDir($srcFolder, $dstFolder, $force = false) {
		$dir = new Folder($srcFolder);
		$files = $dir->read(false);
		if (!empty($files[0])) {
			foreach ($files[0] as $folder) {
				$this->copyDir(
					AssetFile::getPath([$srcFolder, $folder]), 
					AssetFile::getPath([$dstFolder, $folder]),
					$force
				);
			}
		}
		if (!empty($files[1])) {
			foreach ($files[1] as $file) {
				$this->copyFile(
					AssetFile::getPath([$srcFolder, $file]),
					AssetFile::getPath([$dstFolder, $file]),
					$force
				);
			}
		}
	}

/**
 * Copies an individual file to a new location
 *
 * @param string $srcFile The source path
 * @param string $dstFile The destination path
 * @param bool $force If true, copy no matter what
 * @return bool|null True if success, false if failure, null if no copy
 **/
	private function copyFile($srcFile, $dstFile, $force = false) {
		if ($force || !is_file($dstFile) || filesize($srcFile) != filesize($dstFile)) {
			return AssetFile::copy($srcFile, $dstFile);
		} else {
			return null;
		}
	}

/**
 * Returns the destination directory based on the asset type
 * 
 * @param string $assetType The type of asset being referenced
 * @param string $filePath An additional file path to append to the directory
 * @return string An updated destination directory path
 **/
	private function getDstDir($assetType, $filePath = null) {
		return AssetFile::getPath([
			$this->settings['dst'],
			$assetType,
			'vendor',
			$filePath
		]);
	}
}