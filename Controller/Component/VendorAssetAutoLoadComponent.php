<?php
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('AssetFile', 'CakeAssets.Utility');

class VendorAssetAutoLoadComponent extends Component {
	public $controller;
	public $settings = [];

	private $_types = ['css', 'js', 'files'];

	public function __construct(ComponentCollection $collection, $settings = []) {
		$default = [
			'css' => [],
			'js' => [],
			'files' => [],

			'src' => [
				ROOT . DS . 'node_modules' . DS,
				APP . 'Vendor' . DS,
			],
			'dst' => APP .  WEBROOT_DIR,
		];
		$this->settings = array_merge($default, (array) $settings);
	}

	public function initialize(Controller $controller) {
		parent::initialize($controller);
		$this->autoload();
	}

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

	private function copyFile($srcFile, $dstFile, $force = false) {
		if ($force || !is_file($dstFile) || filesize($srcFile) != filesize($dstFile)) {
			return AssetFile::copy($srcFile, $dstFile);
		} else {
			return null;
		}
	}

	private function getDstDir($assetType, $filePath = null) {
		return AssetFile::getPath([
			$this->settings['dst'],
			$assetType,
			'vendor',
			$filePath
		]);
	}
}