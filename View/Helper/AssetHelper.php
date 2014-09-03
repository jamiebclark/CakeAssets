<?php
App::uses('CakeAssetsAppHelper', 'CakeAssets.View/Helper');
App::uses('AssetMinify', 'CakeAssets.Utilities');

class AssetHelper extends CakeAssetsAppHelper {
	public $name = 'Asset';
	public $helpers = array('Html');
	
	//Assets to be loaded whenever helper is called, broken down by category
	public $defaultAssets = array(
		'jquery' => array(
			'css' => array('Layout.jquery/ui/ui-lightness/jquery-ui-1.10.3.custom.min'),
			'js' => array(
				'//ajax.googleapis.com/ajax/libs/jquery/1.10.0/jquery.min.js',
				'//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js'
			),
		),
		'bootstrap' => array(
			'css' => array('Layout.bootstrap', 'Layout.bootstrap-responsive'),
			'js' => array('Layout.bootstrap3.0/bootstrap.min'),
		),
		'default' => array(
			'css' => array('Layout.style'),
			'js' => array('Layout.script')
		)
	);
	
	public $minify = true;
	
	//After constructor, all assets will be stored here
	private $_defaultAssets = array();

	private $_assetTypes = array('css', 'js');
	private $_assetTypesComplete = array('css', 'js', 'block', 'jsAfterBlock');

	private $_assets = array();
	private $_usedAssets = array();
	private $_blocked = array();

	private $_minifyableTypes = array('css', 'js', 'jsAfterBlock');
	
	public function __construct(View $view, $settings = array()) {
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
	
/**
 * Outputs all stored assets
 *
 * @param bool $inline If the output should be outputted right away or wait until fetch
 * @param bool $repeat If false, skips any assets that have already been outputted
 * @param array $types Optionally specify type of asset to output
 * @return A string of all assets
 **/
	public function output($inline = false, $repeat = false, $types = array()) {
		$AssetMinify = new AssetMinify();

		$eol = "\n\t";
		$out = $eol . '<!--- ASSETS -->'. $eol;
		if (empty($types)) {
			$types = $this->_assetTypesComplete;
		} else if (!is_array($types)) {
			$types = array($types);
		}
		foreach ($types as $type) {
			// Cut and paste those added with HtmlHelper
			if (in_array($type, $this->_minifyableTypes)) {
				$blockName = null;
				if ($type == 'js') {
					$blockName = 'script';
				}
				$this->getBlockAssets($type, $blockName);
			}

			if (!empty($this->_assets[$type])) {
				$files = $this->_assets[$type];
				if ($this->minify && in_array($type, $this->_minifyableTypes)) {
					$files = $AssetMinify->minify($files, $type);
				}
				foreach ($files as $file => $config) {
					if (is_numeric($file)) {
						$file = $config;
						$config = array();
					}
					if ($this->isAssetUsed($type, $file) && !$repeat) {
						continue;
					}
					$out .= $this->_output($type, $file, $config, $inline) . $eol;
					$this->setAssetUsed($type, $file);
				}
				
				if ($htmlType = $this->getHtmlType($type)) {
					$out .= $this->_View->fetch($htmlType);
					$this->_View->set($htmlType, '');
				}
			}
		}
		$out .= '<!--- END ASSETS -->'. $eol;
		return $out;
	}

	public function js($file, $config = array()) {
		$type = 'js';
		if (!empty($config['afterBlock'])) {
			$type = 'jsAfterBlock';
			unset($config['afterBlock']);
		}
		return $this->_addFile($type, $file, $config);
	}
	
	public function css($file, $config = array()) {
		return $this->_addFile('css', $file, $config);
	}	
	
	public function block($script, $config = array()) {
		return $this->_addFile('block', $script, $config);
	}
	
	public function blockStart($options = array()) {
		$this->_blockOptions = array();
		ob_start();
	}
	
	public function blockEnd() {
		$buffer = ob_get_clean();
		$options = $this->_blockOptions;
		$this->_blockOptions = array();
		return $this->block($buffer, $options);
	}
	
	public function removeCss($file) {
		return $this->_removeFile('css', $file);
	}
	
	public function removeJs($file) {
		return $this->_removeFile('js', $file);
	}

	
/**
 * Checks a View block for posted assets and adds them to minify
 *
 * @param String $type The asset type (css|js)
 * @param String $blockName Optional alternate name of the block. Otherwise type will be used
 * @return bool True on success
 **/
	private function getBlockAssets($type, $blockName = null) {
		$AssetMinify = new AssetMinify();

		if (empty($blockName)) {
			$blockName = $type;
		}
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
			$found = false;
			foreach ($xml->{$tag} as $k => $row) {
				$attributes = current($row->attributes());
				if (!empty($attributes[$attr])) {
					$this->_addFile($type, $attributes[$attr]);
					$found = true;
				}
			}

			if (!$found) {
				$this->_addFile('block', (string) $xml->{$tag});
			}
		}
		$this->_View->assign($blockName, '');		// Clear existing block
		return true;
	}
	
	//Converts type to the corresponding Html helper type
	private function getHtmlType($type) {
		$return = null;
		if (in_array($type, array('css', 'script'))) {
			$return = $type;
		} else if (in_array($type, array('block'))) {
			$return = 'block';
		} else if (in_array($type, array('js'))) {
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
	protected function _addFile($type, $files, $configAll = array()) {
		if (!is_array($files)) {
			$files = array($files);
		}
		if (!isset($this->_assets[$type])) {
			$this->_assets[$type] = array();
		}
		$typeFiles =& $this->_assets[$type];
		$prependCount = 0;
		foreach ($files as $file => $config) {
			if (is_numeric($file)) {
				$file = $config;
				$config = array();
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
				$insert = array($file => $config);
				if (empty($typeFiles)) {
					$this->_assets[$type] += $insert;
				} else if (empty($prependCount)) {
					$this->_assets[$type] = $insert + $typeFiles;
					$prependCount++;
				} else {
					$before = array_slice($typeFiles,0,$prependCount);
					$after = array_slice($typeFiles,$prependCount);
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
			$files = array($files);
		}
		foreach ($files as $file) {
			if (isset($this->_assets[$type][$file])) {
				unset($this->_assets[$type][$file]);
			}
			$this->_blocked[$type][$file] = $file;
		}
	}
	
	protected function _output($type, $file, $config = array(), $inline = false) {
		if (empty($file)) {
			return '';
		}
		$options = compact('inline');
		if (!empty($config['plugin'])) {
			$options['plugin'] = $config['plugin'];
			unset($config['plugin']);
		}
		if ($type == 'css') {
			$keys = array('media');
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
		$default = array('css' => array(), 'js' => array());
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