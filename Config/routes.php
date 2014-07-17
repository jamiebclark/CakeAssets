<?php
	Router::connect('/cake_assets/css-min/:file', array(
		'controller' => 'minified_assets', 
		'action' => 'css',
		'plugin' => 'cake_assets'
	), array('pass' => array('file')));

	Router::connect('/cake_assets/js-min/:file', array(
		'controller' => 'minified_assets', 
		'action' => 'js',
		'plugin' => 'cake_assets'
	), array('pass' => array('file')));
