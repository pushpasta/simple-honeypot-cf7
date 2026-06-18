<?php

declare(strict_types=1);

$apiUrl = 'https://api.wordpress.org/translations/core/1.0/';

$response = @file_get_contents($apiUrl);

if ($response === false) {
	fwrite(STDERR, "Failed to fetch translations from WordPress API.\n");
	exit(1);
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
	fwrite(STDERR, "Failed to parse WordPress API response: " . json_last_error_msg() . "\n");
	exit(1);
}

if (! isset($data['translations']) || ! is_array($data['translations'])) {
	fwrite(STDERR, "Unexpected API response structure.\n");
	exit(1);
}

foreach ($data['translations'] as $translation) {
	if (! isset($translation['language'], $translation['english_name'])) {
		continue;
	}

	printf(
		'- %s (%s)' . PHP_EOL,
		$translation['language'],
		$translation['english_name']
	);
}
