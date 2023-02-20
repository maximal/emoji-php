<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Maximal\Emoji\Detector;

$tests = require __DIR__ . '/cases.php';

$totalTests = count($tests) * 6;
$runTests = 0;
$succeededTests = 0;
$failedTests = 0;
foreach ($tests as $index => $test) {
	$input = $test['input'];

	// Actual results
	$containsEmoji = Detector::containsEmoji($input);
	$onlyEmojiIgnoreWhite = Detector::onlyEmoji($input, true);
	$onlyEmojiNotIgnoreWhite = Detector::onlyEmoji($input, false);
	$removeEmoji = Detector::removeEmoji($input);
	$startingEmojisIgnoreWhite = Detector::startingEmojis($input, true);
	$startingEmojisNotIgnoreWhite = Detector::startingEmojis($input, false);
	$runTests += count($test) - 1;

	// Expected results
	$containsEmojiPass = expected($containsEmoji, $test, 'containsEmoji');
	$onlyEmojiIgnoreWhitePass = expected($onlyEmojiIgnoreWhite, $test, 'onlyEmoji ignoreWhitespace=true');
	$onlyEmojiNotIgnoreWhitePass = expected($onlyEmojiNotIgnoreWhite, $test, 'onlyEmoji ignoreWhitespace=false');
	$removeEmojiPass = expected($removeEmoji, $test, 'removeEmoji');
	$startingEmojisIgnoreWhitePass = expected($startingEmojisIgnoreWhite, $test, 'startingEmojis ignoreWhitespace=true');
	$startingEmojisNotIgnoreWhitePass = expected($startingEmojisNotIgnoreWhite, $test, 'startingEmojis ignoreWhitespace=false');

	// Results
	$succeededTests += $containsEmojiPass ? 1 : 0;
	$succeededTests += $onlyEmojiIgnoreWhitePass ? 1 : 0;
	$succeededTests += $onlyEmojiNotIgnoreWhitePass ? 1 : 0;
	$succeededTests += $removeEmojiPass ? 1 : 0;
	$succeededTests += $startingEmojisIgnoreWhitePass ? 1 : 0;
	$succeededTests += $startingEmojisNotIgnoreWhitePass ? 1 : 0;

	// Print results
	echo 'Input: ', toString($input), PHP_EOL;
	echo "\t", pass($containsEmojiPass), '  containsEmoji(input):  ', toString($containsEmoji), PHP_EOL;
	echo "\t", pass($onlyEmojiIgnoreWhitePass), '  onlyEmoji(input, ignoreWhitespace: true):  ', toString($onlyEmojiIgnoreWhite), PHP_EOL;
	echo "\t", pass($onlyEmojiNotIgnoreWhitePass), '  onlyEmoji(input, ignoreWhitespace: false):  ', toString($onlyEmojiNotIgnoreWhite), PHP_EOL;
	echo "\t", pass($removeEmojiPass), '  removeEmoji(input):  ', toString($removeEmoji), PHP_EOL;
	echo "\t", pass($startingEmojisIgnoreWhitePass), '  startingEmojis(input, ignoreWhitespace: true):  ', toString($startingEmojisIgnoreWhite), PHP_EOL;
	echo "\t", pass($startingEmojisNotIgnoreWhitePass), '  startingEmojis(input, ignoreWhitespace: false):  ', toString($startingEmojisNotIgnoreWhite), PHP_EOL;
}

echo PHP_EOL;
echo 'Tests total:  ', $totalTests, PHP_EOL;
echo '        run:  ', $runTests, PHP_EOL;
echo '  succeeded:  ', $succeededTests, PHP_EOL;
echo '     failed:  ', $totalTests - $succeededTests, PHP_EOL;

exit($totalTests === $succeededTests ? 0 : 1);


function toString($result): string
{
	return json_encode($result, JSON_UNESCAPED_UNICODE);
}

function expected($result, array $test, string $testKey): bool
{
	if (is_bool($result) || is_string($result) || is_int($result)) {
		return $result === $test[$testKey];
	}
	if (is_array($result)) {
		if (!is_array($test[$testKey])) {
			return false;
		}
		if (count($result) !== count($test[$testKey])) {
			return false;
		}
		foreach ($result as $index => $value) {
			if (!isset($test[$testKey][$index])) {
				return false;
			}
			if ($value !== $test[$testKey][$index]) {
				return false;
			}
		}
		return true;
	}
	return false;
}

function pass(bool $pass): string
{
	return $pass ? '[ OK ]' : '[FAIL]';
}
