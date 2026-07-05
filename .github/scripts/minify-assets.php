<?php

declare(strict_types=1);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php minify-assets.php <input> <output>\n");
	exit(1);
}

$input  = $argv[1];
$output = $argv[2];

if (! file_exists($input)) {
	fwrite(STDERR, "Input file not found: {$input}\n");
	exit(1);
}

$extension = strtolower(pathinfo($input, PATHINFO_EXTENSION));

require_once __DIR__ . '/../../vendor/autoload.php';

switch ($extension) {
	case 'js':
		$minifier = new \MatthiasMullie\Minify\JS($input);
		break;
	case 'css':
		$minifier = new \MatthiasMullie\Minify\CSS($input);
		break;
	default:
		fwrite(STDERR, "Unsupported file type: {$extension}\n");
		exit(1);
}

$originalSize = strlen(file_get_contents($input));
$result        = $minifier->minify($output);

if ($result === false) {
	fwrite(STDERR, "Failed to minify: {$input}\n");
	exit(1);
}

$minifiedSize = strlen($result);
$savings      = $originalSize > 0 ? round((1 - $minifiedSize / $originalSize) * 100) : 0;

printf(
	'%s → %s (%d%% smaller)' . PHP_EOL,
	$input,
	$output,
	$savings
);
