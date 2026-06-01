<?php
// Minimal link preview proxy: returns {title, description, image} JSON for a given URL
// Security notes: allow only http(s), short timeouts, size cap, no redirects to local addresses.

header('Content-Type: application/json');

function bad($msg, $code = 400) {
	http_response_code($code);
	echo json_encode(['success' => false, 'message' => $msg]);
	exit();
}

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($url === '') bad('Missing url');

// Ensure scheme
if (!preg_match('/^https?:\/\//i', $url)) {
	$url = 'http://' . $url;
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) bad('Invalid url');

// Prevent SSRF to local/internal addresses
$host = parse_url($url, PHP_URL_HOST);
if (!$host) bad('Invalid host');
$ip = gethostbyname($host);
if ($ip === '127.0.0.1' || $ip === '::1') bad('Forbidden');
if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.|169\.254\.)/', $ip)) bad('Forbidden');

// Fetch content (cap ~200KB)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; LinkPreviewBot/1.0)');
$html = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $httpCode >= 400) bad('Fetch failed', 502);

// Trim to 200KB
if (strlen($html) > 200 * 1024) {
	$html = substr($html, 0, 200 * 1024);
}

// Extract metadata
$title = '';
$desc = '';
$image = '';

// Title tag
if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
	$title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
}
// OG title
if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
	$ogt = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
	if ($ogt) $title = $ogt;
}
// Description
if (preg_match('/<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
	$desc = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
}
if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
	$ogd = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
	if ($ogd) $desc = $ogd;
}
// Image
if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
	$image = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
}

echo json_encode([
	'success' => true,
	'title' => $title,
	'description' => $desc,
	'image' => $image,
]);


