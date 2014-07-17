<?php
header("Content-Type: $contentType");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: must-revalidate");
header('Vary: Accept-Encoding');

// No recompile needed, but see if we can send a 304 to the browser.
header("Last-Modified: $lastModified GMT"); 
header("Etag: $etag"); 

$offset = 60 * 60;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT");

if ($isModified) { 
	// Read the cache file and send it to the client.
	$content = file_get_contents($filepath);
	if (!isset($_SERVER['HTTP_ACCEPT_ENCODING']) || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
		header('Content-Encoding: gzip');
		$content = gzencode(trim($content), 9);
	}
	header('Content-Length: ' . strlen($content));
	echo $content;
} else {
	header("HTTP/1.1 304 Not Modified"); 
}
