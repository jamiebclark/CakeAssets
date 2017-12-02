<?php
App::uses('CakeAssetsAppHelper', 'CakeAssets.View/Helper');
App::uses('AssetMinify', 'CakeAssets.Utility');

App::uses('PluginConfig', 'CakeAssets.Utility');
PluginConfig::init('CakeAssets');

class AssetHelper extends CakeAssetsAppHelper {
	public $name = 'Asset';
	public $helpers = ['Html'];
	
	//Assets to be loaded whenever helper is called, broken down by category
	public $defaultAssets = [
		'jquery' => [
			'css' => [],
			'js' => [],
		],
		'bootstrap' => [
			'css' => [],
			'js' => [],
		],
		'default' => [
			'css' => [],
			'js' => []
		]
	];
	
	public $minify = false;
	
	//After constructor, all assets will be stored here
	private $_defaultAssets = [];

	private $_assetTypes = ['css', 'js'];
	private $_assetTypesComplete = ['css', 'js', 'block', 'jsAfterBlock'];

	private $_assets = [];
	private $_usedAssets = [];
	private $_blocked = [];

	private $_minifyableTypes = ['css', 'js', 'jsAfterBlock'];

	private $_assetBlocks = [
		'css' => ['cssFirst', 'css', 'cssLast'],
		'js' => ['jsFirst', 'js', 'script', 'jsLast'],
	];
	
	public function __construct(View $view, $settings = []) {

		parent::__construct($view, $settings);
		
		foreach ($this->defaultAssets as $assetGroupKey => $assetGroup) {
			if (isset($settings[$assetGroupKey])) {
				foreach ($this->_assetTypes as $type) {
					if (isset($settings[$assetGroupKey][$type])) {
						$this->defaultAssets[$assetGroupKey][$type] = $settings[$assetGroupKey][$type];
					}
				}
				unset($settings[$assetGroupKey]);
			}
		}

		$this->minify = Configure::read('CakeAssets.minify');
		if (isset($_GET['minify'])) {
			$this->minify = round($_GET['minify']);
		}

		$this->_set($settings);
		$this->setDefaultAssets();

		foreach ($this->_assetTypes as $type) {
			if (!empty($this->_defaultAssets[$type])) {
				$this->$type($this->_defaultAssets[$type]);
			}
			if (!empty($options[$type])) {
				$this->$type($options[$type]);
			}
		}
	}
	
	public function reset($type = null) {
		foreach ($this->_assetTypes as $assetType) {
			if (empty($type) || $type == $assetType) {
				foreach ($this->_assetBlocks[$assetType] as $blockName) {
					$this->clearBlock($blockName);
				}
				$this->_assets[$assetType] = [];
			}
		}
	}

/**
 * Outputs all stored assets
 *
 * @param bool $inline If the output should be outputted right away or wait until fetch
 * @param bool $repeat If false, skips any assets that have already been outputted
 * @param array $types Optionally specify type of asset to output
 * @return A string of all assets
 **/
	public function output($inline = false, $repeat = false, $types = []) {
		App::uses('AssetMinify', 'CakeAssets.Utility');

		$AssetMinify = new AssetMinify();
		$eol = "\n\t";
		$out = $eol . '<!--- ASSETS -->'. $eol;
		$base = Router::url('/');

		if (empty($types)) {
			$types = $this->_assetTypesComplete;
		} else if (!is_array($types)) {
			$types = [$types];
		}
		foreach ($types as $type) {
			// Cut and paste those added with HtmlHelper
			if (in_array($type, $this->_minifyableTypes)) {
				if (!empty($this->_assetBlocks[$type])) {
					$blocks = $this->_assetBlocks[$type];
				} else {
					$blocks = [$type];
				}
				$this->getBlockAssets($type, $blocks);
			}

			if (!empty($this->_assets[$type])) {
				$files = $this->_assets[$type];
				if ($this->minify && in_array($type, $this->_minifyableTypes)) {
					$files = $AssetMinify->minify($files, $type);
				}
				foreach ($files as $file => $config) {
					if (is_numeric($file)) {
						$file = $config;
						$config = [];
					}

					// Strips the base
					if ($base != '/' && strpos($file, $base) === 0) {
						$file = substr($file, strlen($base) -1);
					}

					if ($this->isAssetUsed($type, $file) && !$repeat) {
						continue;
					}
					$out .= $this->_output($type, $file, $config, $inline) . $eol;
					$this->setAssetUsed($type, $file);
				}
				/*				
				if ($htmlType = $this->getHtmlType($type)) {
					$out .= $this->_View->fetch($htmlType);
					$this->clearBlock($htmlType);
				}
				*/
			}
		}
		$out .= '<!--- END ASSETS -->'. $eol;
		return $out;
	}

/**
 * Empties a block in the View element
 *
 * @param string $blockName The name of the block
 * @return void;
 **/
	protected function clearBlock($blockName) {
		return $this->_View->set($blockName, '');
	}

	public function js($file, $config = []) {
		$type = 'js';
		if (!empty($config['afterBlock'])) {
			$type = 'jsAfterBlock';
			unset($config['afterBlock']);
			return $this->_addFileToCache($type, $file, $config);
		} else {
			$config['inline'] = false;
			return $this->Html->script($file, $config);
		}
	}
	
	public function css($file, $config = []) {
		return $this->_addFileToCache('css', $file, $config);
	}	
	
	public function block($script, $config = []) {
		return $this->_addFileToCache('block', $script, $config);
	}
	
	public function blockStart($options = []) {
		$this->_blockOptions = [];
		ob_start();
	}
	
	public function blockEnd() {
		$buffer = ob_get_clean();
		$options = $this->_blockOptions;
		$this->_blockOptions = [];
		return $this->block($buffer, $options);
	}
	
	public function removeCss($file) {
		return $this->_removeFile('css', $file);
	}
	
	public function removeJs($file) {
		return $this->_removeFile('js', $file);
	}

	public function setAssetBlocks($type, $blocks) {
		$this->_assetBlocks[$type] = $blocks;
	}

	public function addAssetBlock($type, $blockName) {
		$this->_assetBlocks[$type][] = $blockName;
	}
	
/**
 * Checks a View block for posted assets and adds them to minify
 *
 * @param String $type The asset type (css|js)
 * @param string|array $blockName Optional alternate name of the block. Otherwise type will be used
 * @return bool True on success
 **/
	private function getBlockAssets($type, $blockName = null) {
		$AssetMinify = new AssetMinify();

		if (empty($blockName)) {
			$blockName = $type;
		}

		$blocks = is_array($blockName) ? $blockName : [$blockName];

		$cache = [];
		foreach ($blocks as $blockName):
			$block = $this->_View->fetch($blockName);
			if (!empty($block)) {
				switch ($type) {
					case 'css':
						$selfClosing = true;
						$tag = 'link';
						$attr = 'href';
						break;
					case 'js':
						$selfClosing = false;
						$tag = 'script';
						$attr = 'src';
						break;
				}
				if ($selfClosing) {
					// Makes CSS calls self-closing
					$block = preg_replace('#([^/])>#', '$1/>', $block);
				}
				$block = '<xml>' . $block . '</xml>';

				$xml = new SimpleXMLElement($block);
				foreach ($xml->{$tag} as $k => $row) {
					$attributes = current($row->attributes());
					if (!empty($attributes[$attr])) {
						$key = $type;
						$val = $attributes[$attr];
					} else {
						$key = 'block';
						$val = (string) $row;
					}
					$cache[$key][] = $val;
				}
			}
			$this->clearBlock($blockName);		// Clear existing block
		endforeach;

		foreach ($cache as $type => $vals) {
			$this->_addFileToCache($type, $vals, ['prepend' => true]);
		}

		return true;
	}
	
	//Converts type to the corresponding Html helper type
	private function getHtmlType($type) {
		$return = null;
		if (in_array($type, ['css', 'script'])) {
			$return = $type;
		} else if (in_array($type, ['block'])) {
			$return = 'block';
		} else if (in_array($type, ['js'])) {
			$return = 'script';
		}
		return $return;
	}
	
/**
 * Adds a file to the asset cache
 *
 * @param string $type  The type of asset (css or js)
 * @param array|string $files The path to the file or files to be added
 * @param array $configAll Settings to be passed to all file
 * @return boolean On success
 **/
	protected function _addFileToCache($type, $files, $configAll = []) {
		if (!is_array($files)) {
			$files = [$files];
		}
		if (!isset($this->_assets[$type])) {
			$this->_assets[$type] = [];
		}
		$typeFiles =& $this->_assets[$type];
		$prependCount = 0;
		foreach ($files as $file => $config) {
			if (is_numeric($file)) {
				$file = $config;
				$config = [];
			}
			if ($file === false) {
				continue;
			}
			if (isset($this->_blocked[$type][$file])) {
				continue;
			}
			
			$config = array_merge($configAll, $config);

			if (!empty($config['prepend'])) {
				unset($config['prepend']);
				$insert = [$file => $config];
				if (false && empty($typeFiles)) {
					$this->_assets[$type] += $insert;
				} else if (false && empty($prependCount)) {
					$this->_assets[$type] = $insert + $typeFiles;
					$prependCount++;
				} else {
					$before = array_slice($typeFiles, 0, $prependCount);
					$after = array_slice($typeFiles, $prependCount);
					$this->_assets[$type] = $before + $insert + $after;
					$prependCount++;
				}
			} else {
				$typeFiles[$file] = $config;
			}
		}
		return true;
	}
	
	protected function _removeFile($type, $files) {
		if (!is_array($files)) {
			$files = [$files];
		}
		foreach ($files as $file) {
			if (isset($this->_assets[$type][$file])) {
				unset($this->_assets[$type][$file]);
			}
			$this->_blocked[$type][$file] = $file;
		}
	}
	
	protected function _output($type, $file, $config = [], $inline = false) {
		if (empty($file)) {
			return '';
		}
		$options = compact('inline') + ['once' => false];
		if (!empty($config['plugin'])) {
			$options['plugin'] = $config['plugin'];
			unset($config['plugin']);
		}
		if ($type == 'css') {
			$keys = ['media'];
			foreach ($keys as $key) {
				if (!empty($config[$key])) {
					$options[$key] = $config[$key];
				}
			}
			$out = $this->Html->css($file, null, $options);
			if (!empty($config['if'])) {
				$out = sprintf('<!--[if %s]>%s<![endif]-->', $config['if'], $out);
			}
		} else if ($type == 'js' || $type == 'jsAfterBlock') {
			$out = $this->Html->script($file, $options);
		} else if ($type == 'block') {
			$out = $this->Html->scriptBlock($file, $options);
		}
		return $out;
	}

	private function setDefaultAssets() {
		$default = ['css' => [], 'js' => []];
		foreach ($this->defaultAssets as $assetGroup) {
			foreach ($assetGroup as $type => $assets) {
				if (is_array($assets)) {
					$default[$type] = array_merge($default[$type], $assets);
				} else {
					$default[$type][] = $assets;
				}
			}
		}
		$this->_defaultAssets = $default;
	}
	
	private function isAssetUsed($type, $file) {
		return isset($this->_usedAssets[$type][$file]);
	}
	
	private function setAssetUsed($type, $file) {
		if (is_array($file)) {
			foreach ($file as $f) {
				$this->setAssetUsed($type, $f);
			}
		} else {
			$this->_usedAssets[$type][$file] = true;
		}
	}
}
