<?php
/**
 * Скрипт.
 *
 * @author MaximAL
 * @date 2020-01-02
 * @time 18:00
 * @since 2020-01-02 Первая версия.
 *
 * @copyright ©  MaximAL, Sijeko  2019
 * @link https://maximals.ru
 * @link https://sijeko.ru
 */

$timeStart = microtime(true);
$outFile = '../src/Detector.php';
$readmeFile = '../README.md';

const SKIN_TONE_REGEX = '/[\x{1F3FB}-\x{1F3FF}]+/ui';
const HAIR_REGEX = '/[\x{1F9B0}-\x{1F9B3}]+/ui';

$string = count($argv) > 1 ? $argv[1] : '🏴󠁧󠁢󠁳󠁣󠁴󠁿';


//// Поехали!

$listUrl = 'https://unicode.org/emoji/charts-14.0/emoji-list.html';
echo 'Getting contents of: ', $listUrl;
$html = file_get_contents($listUrl);
echo '  OK', PHP_EOL;

if (!preg_match('#<title>Emoji\s*List,\s*v([\d.]+)[^<]*</title>#ui', $html, $match)) {
	echo 'Error: Unicode version not found.', PHP_EOL;
	exit(1);
}
$version = $match[1];


if (!preg_match_all(
	'#<td class=\'code\'><a href=\'[^\']+\' name=\'[^\']+\'>([^<]+)</a></td>[\s\r\n]*' .
	'<td class=\'andr\'><a href=\'[^\']+\' target=\'[^\']+\'><img alt=\'([^\']+)\'\s+#ui',
	$html,
	$matches
)) {
	echo 'Error: Emoji characters not found.', PHP_EOL;
	exit(2);
}

$oneChar = [];
$multipleChar = [];
$codes = [];
$count = 0;
foreach ($matches[2] as $index => $emoji) {
	$count++;
	$code = trim(str_replace('U+', '', $matches[1][$index]));
	echo "\t", $count, ': ', $emoji, ' => ', $code;
	if (strpos($code, ' ') === false) {
		// Односимвольный эмодзи
		$pos = codeToPos($code);
		echo ' => ', $pos;
		$converted = posToCode($pos);
		if ($converted !== $code) {
			die;
		}
		$codes []= $pos;
		$oneChar[$pos] = $emoji;
	} else {
		// Многосимвольный эмодзи
		$points = [];
		foreach (explode(' ', $code) as $point) {
			$points []= '\x{' . posToCode(hexdec($point)) . '}';
		}
		echo ' => ', implode($points);
		$multipleChar []= implode($points);
	}
	echo PHP_EOL;
}

echo 'Found one-character emojis: ', count($codes), PHP_EOL;
echo 'Found multiple-character emojis: ', count($multipleChar), PHP_EOL;
echo 'Total emojis: ', $count, PHP_EOL;

echo 'Finding ranges: ', PHP_EOL;
sort($codes);
$lastCode = 0;
$lastStart = 0;
$ranges = [];
foreach ($codes as $code) {
	if ($code > $lastCode + 1) {
		if ($lastCode > $lastStart) {
			// Пишем диапазон
			echo "\t", $lastStart, '—', $lastCode, '  (', ($lastCode - $lastStart + 1), ')    ';
			echo $oneChar[$lastStart], '—', $oneChar[$lastCode], PHP_EOL;
			$ranges []= '\x{' . posToCode($lastStart) . '}' .
				($lastCode > $lastStart + 1 ? '-' : '') .
				'\x{' . posToCode($lastCode) . '}';
		} elseif ($lastCode > 0) {
			// Всего один символ
			echo "\t", $lastCode, '    ', $oneChar[$lastCode], PHP_EOL;
			$ranges []= '\x{' . posToCode($lastCode) . '}';
		}
		$lastStart = $code;
	}
	$lastCode = $code;
}

// Long sequences first
usort($multipleChar, function ($a, $b) {
	if (strlen($a) === strlen($b)) {
		return $a > $b ? 1 : -1;
	}
	return strlen($a) < strlen($b) ? 1 : -1;
});

// Все диапазоны
echo 'Found ranges: ', count($ranges), PHP_EOL;
echo "\t", implode(PHP_EOL. "\t", $ranges), PHP_EOL;

// Регвыр для односимвольных эмодзи
$oneCharRegex = '/[' . implode($ranges) . ']+/ui';
echo PHP_EOL, 'One-char regex: ', $oneCharRegex, PHP_EOL;

// Регвыр для многосимвольных эмодзи
$complexRegex = '/(' . implode('|', $multipleChar) . ')+/ui';
echo PHP_EOL, 'Multiple-char regex: ', $complexRegex, PHP_EOL;

// Общий регвыр
$totalRegex = '/(' . implode('|', $multipleChar) . '|[' . implode($ranges) . ']+)+/ui';
echo PHP_EOL, 'Total regex: ', $totalRegex, PHP_EOL;

$written = file_put_contents(
	$outFile,
	preg_replace(
		[
			'/const PARSE_DATETIME = [^;]*;/ui',
			'/const UNICODE_VERSION = [^;]*;/ui',
			'/\* Unicode version:[^\n]*\n/',
			'/const TOTAL_EMOJI_COUNT = [^;]*;/ui',
			'/const EMOJI_REGEX = [^;]*;/ui',
		],
		[
			"const PARSE_DATETIME = '" . date('c') . "';",
			"const UNICODE_VERSION = '" . $version . "';",
			"* Unicode version: " . $version . "\n",
			"const TOTAL_EMOJI_COUNT = " . $count . ";",
			"const EMOJI_REGEX = \n\t\t" .  stringWrap($totalRegex) . ";",
		],
		file_get_contents($outFile)
	)
);
if (!$written) {
	exit(3);
}

$written = file_put_contents(
	$readmeFile,
	preg_replace(
		'/Unicode version:[^\n]*\n/ui',
		'Unicode version: ' . $version . ".\n",
		file_get_contents($readmeFile)
	)
);
if (!$written) {
	exit(4);
}

$timeDiff = microtime(true) - $timeStart;
echo 'All done in ', sprintf('%.1f', $timeDiff), ' sec.', PHP_EOL;
exit(0);



//// Annotations
// Maybe later
$annotationsUrl = 'https://www.unicode.org/cldr/charts/latest/annotations/slavic.html';
echo 'Getting contents of: ', $annotationsUrl;
$html = file_get_contents($annotationsUrl);
echo '  OK', PHP_EOL;


$codes = [];
$count = 0;
$oneCharEmojis = [];
$complexEmojis = [];
if (preg_match_all(
	'#<td\s+class=\'source-image\'><a\s+name=\'([^\']+)\'\s+href=\'([^\']+)\'>([^<]+)</a></td><td class=\'source\'>([0-9a-f\s]+)</td>#ui',
	$html,
	$matches
)) {
	foreach ($matches[4] as $index => $code) {
		$emoji = $matches[3][$index];
		//echo $emoji, ' => ', $code, preg_match($skinTones, $emoji) ? ' кожа ' : '';
		if (strpos($code, ' ') === false) {
			$val = hexdec($code);
			$codes []= $val;
			$converted = posToCode($val);
			if (strlen($converted) === 2) {
				$converted = '00' . $converted;
			}
			if ($converted !== $code) {
				echo 'Error while checking emoji `', $emoji, '` with code ', $code, PHP_EOL;
				exit(1);
			}
			$oneCharEmojis[$val] = $emoji;
			//echo ' => ', $val;
			//echo ' => ', $converted;
		} else {
			$points = [];
			foreach (explode(' ', $code) as $point) {
				//var_dump($point, hexdec($point));
				$points []= '\x{' . posToCode(hexdec($point)) . '}';
			}
			$complexEmojis []= implode($points);
		}

		//echo PHP_EOL;
		$count++;
	}
}

echo 'Found one-character emojis: ', count($codes), PHP_EOL;
echo 'Found multiple-character emojis: ', count($complexEmojis), PHP_EOL;
echo 'Total emojis: ', $count, PHP_EOL;
if ($count !== count($codes) + count($complexEmojis)) {
	echo 'Error: total emoji count is ', $count, ' while sum of one-char and multiple-char emojis is ',
		count($codes) + count($complexEmojis), '.', PHP_EOL;
	exit(2);
}

echo 'Finding ranges: ', PHP_EOL;
sort($codes);
$lastCode = 0;
$lastStart = 0;
$ranges = [];
foreach ($codes as $code) {
	if ($code > $lastCode + 1) {
		if ($lastCode > $lastStart) {
			// Пишем диапазон
			echo "\t", $lastStart, '—', $lastCode, '  (', ($lastCode - $lastStart + 1), ')    ';
			echo $oneCharEmojis[$lastStart], '—', $oneCharEmojis[$lastCode], PHP_EOL;
			$ranges []= '\x{' . posToCode($lastStart) . '}' .
				($lastCode > $lastStart + 1 ? '-' : '') .
				'\x{' . posToCode($lastCode) . '}';
		} elseif ($lastCode > 0) {
			echo "\t", $lastCode, '    ', $oneCharEmojis[$lastCode], PHP_EOL;
			$ranges []= '\x{' . posToCode($lastCode) . '}';
			// Всего один символ
		}
		$lastStart = $code;
	}
	$lastCode = $code;
}

if ($lastCode !== 0) {
	echo "\t", $lastStart, '—', $lastCode, '  (', ($lastCode - $lastStart + 1), ')    ';
	echo $oneCharEmojis[$lastStart], '—', $oneCharEmojis[$lastCode], PHP_EOL;
	$ranges []= '\x{' . posToCode($lastStart) . '}-\x{' . posToCode($lastCode) . '}';
}
echo 'Found ranges: ', count($ranges), PHP_EOL;
echo "\t", implode(PHP_EOL. "\t", $ranges), PHP_EOL;

$oneCharRegex = '/[' . implode($ranges) . ']+/ui';
echo PHP_EOL, 'One-char regex: ', $oneCharRegex, PHP_EOL;

$complexRegex = '/(' . implode('|', $complexEmojis) . ')+/ui';
echo PHP_EOL, 'Multiple-char regex: ', $complexRegex, PHP_EOL;

$totalRegex = '/([' . implode($ranges) . ']+|' . implode('|', $complexEmojis) . ')+/ui';
echo PHP_EOL, 'Total regex: ', $totalRegex, PHP_EOL;

$res = preg_match($totalRegex, $string, $match);

echo PHP_EOL, '   string:  \'', $string, '\'', PHP_EOL;
echo '      hex:  ';
foreach (unpack('C*', $string) as $byte) {
	echo dechex($byte), ' ';
}
echo PHP_EOL;

if ($res) {
	echo 'match hex:  ';
	foreach (unpack('C*', $match[1]) as $byte) {
		echo dechex($byte), ' ';
	}
	echo PHP_EOL;
	echo 'String contains Emojis.', PHP_EOL;
} else {
	echo 'String doesn’t contain Emojis.', PHP_EOL;
}

exit(0);


function codeToPos(string $position): int
{
	return hexdec(str_replace('U+', '', $position));
}

function posToCode(int $position): string
{
	$code = strtoupper(dechex($position));
	if (strlen($code) === 2) {
		return '00' . $code;
	}
	return $code;
}

function stringWrap(string $string): string
{
	return "'" . preg_replace('/(.{70,75}[}|-])/', "$1' .\n\t\t'", $string) . "'";
}
