<?php
/**
 * Скрипт.
 *
 * @author MaximAL
 * @date 2020-01-02
 * @time 18:00
 * @since 2020-01-02 Первая версия.
 *
 * @copyright ©  MaximAL, Sijeko  2019
 * @link https://maximals.ru
 * @link https://sijeko.ru
 */


exit((new Parser())->run($argv));


class Parser
{
	const SKIN_TONE_REGEX = '/[\x{1F3FB}-\x{1F3FF}]+/ui';
	const HAIR_REGEX = '/[\x{1F9B0}-\x{1F9B3}]+/ui';
	const EMOJIPEDIA_URL = 'https://emojipedia.org/';
	const LATEST_TEST_TEXT = 'https://unicode.org/Public/emoji/latest/emoji-test.txt';
	const SKIN_TONES = ['1F3FB', '1F3FC', '1F3FD', '1F3FE', '1F3FF'];
	const HAIRS = ['1F9B0', '1F9B1', '1F9B3', '1F9B2'];
	const VARIANT_FORM = 'FE0F';

	const OUT_SOURCE_FILE = __DIR__ . '/../src/Detector.php';
	const OUT_README_FILE = __DIR__ . '/../README.md';

	/** @var bool */
	private $withShortcodes = false;
	/** @var bool */
	private $withTranslations = false;

	public function run(array $argv): int
	{
		$timeStart = microtime(true);

		$this->parseCommandParams($argv);

		//// Поехали!

		echo 'Getting contents of: ', self::LATEST_TEST_TEXT;
		$text = file_get_contents(self::LATEST_TEST_TEXT);
		echo '  OK', PHP_EOL;
		$lines = preg_split('/(\r\n|\r|\n)+/', $text, -1, PREG_SPLIT_NO_EMPTY);
		$version = null;
		$date = null;
		$unique = [];
		$qualified = [];
		$oneChars = [];
		$oneCharPositions = [];
		$multipleChars = [];
		foreach ($lines as $line) {
			$line = trim($line);
			if (preg_match('/^#/', $line)) {
				if (preg_match('/^#\s*Version:\s*(\S+)/', $line, $match)) {
					$version = $match[1];
				} elseif (preg_match('/^#\s*Date:\s*(.+)/', $line, $match)) {
					$date = new DateTime($match[1]);
				}
				continue;
			}
			[$codePoints, $reminder] = explode(';', $line, 2);
			$codePointsString = strtoupper(trim($codePoints));
			if (in_array($codePointsString, self::SKIN_TONES)) {
				// Skip skin tones themselves
				continue;
			}
			if (in_array($codePointsString, self::HAIRS)) {
				// Skip hair types themselves
				continue;
			}
			if (!preg_match('/\s#\s(\S+)\s/', $reminder, $match)) {
				continue;
			}

			$codePoints = explode(' ', $codePointsString);
			$codePointsWithoutSkinTones = array_filter(
				$codePoints,
				static fn ($point) => !in_array($point, self::SKIN_TONES)
			);

			$key = self::codePointsToRegex($codePointsWithoutSkinTones);
			if (!isset($unique[$key])) {
				$emoji = $match[1];

				if (count($codePointsWithoutSkinTones) === 1) {
					// Односимвольные Эмодзи
					$oneChars[$key] = $emoji;
					$oneCharPositions[$key] = self::codeToPos($codePointsWithoutSkinTones[0]);
				} else {
					$multipleChars[$key] = $emoji;
				}

				$lastPoint = $codePointsWithoutSkinTones[count($codePointsWithoutSkinTones) - 1];
				if ($lastPoint === self::VARIANT_FORM) {
					$qualified[$key] = $emoji;
				} else {
					$unique[$key] = $emoji;
				}
			}
		}

		if ($date === null) {
			echo 'Could not parse latest Emoji data.', PHP_EOL;
			return 2;
		}

		$full = [];
		foreach ($unique as $codes => $emoji) {
			$codesWithVariantForm = $codes . '\x{' . self::VARIANT_FORM . '}';
			if (isset($qualified[$codesWithVariantForm])) {
				// Длинная форма вначале
				$full[$codesWithVariantForm] = $qualified[$codesWithVariantForm] . ' (fully-qualified)';
			}
			$full[$codes] = $emoji;
		}

		// Обработка диапазонов односимвольных Эмодзи
		echo 'Finding ranges in one-char Emojis...', PHP_EOL;
		asort($oneCharPositions);
		$lastPosition = 0;
		$lastCode = '';
		$lastStartPosition = 0;
		$lastStartCode = '';
		$ranges = [];
		foreach ($oneCharPositions as $code => $position) {
			if ($position > $lastPosition + 1) {
				if ($lastPosition > $lastStartPosition) {
					// Пишем диапазон
					$count = $lastPosition - $lastStartPosition + 1;
					$range =  $lastStartCode . ($count > 2 ? '-' : '') . $lastCode;
					$ranges[] = $range;
					//echo "\t", $lastStartCode, '—', $lastCode;
					//echo '   ', $range, '   (', $count, ')    ';
					//echo $oneChars[$lastStartCode], '—', $oneChars[$lastCode], PHP_EOL;

				} elseif ($lastPosition > 0) {
					// Всего один символ
					$ranges[] = $lastCode;
					//echo "\t", $lastCode, '    ', $lastCode, '    ', $oneChars[$lastCode], PHP_EOL;
				}
				$lastStartCode = $code;
				$lastStartPosition = $position;
			}
			$lastCode = $code;
			$lastPosition = $position;
		}

		echo PHP_EOL;
		echo 'Emojis total:  ', count($full), PHP_EOL;
		echo '      unique:  ', count($unique), PHP_EOL;
		echo '   one-chars:  ', count($oneChars), PHP_EOL;
		echo '      ranges:  ', count($ranges), PHP_EOL;
		echo ' multi-chars:  ', count($multipleChars), PHP_EOL;
		echo '     version:  ', $version ?? '<none>', PHP_EOL;
		echo '        date:  ', $date->format('c'), PHP_EOL;

		$multipleCharCodes = array_keys($multipleChars);
		// Long sequences first
		uasort($multipleCharCodes, static function ($a, $b) {
			if (strlen($a) === strlen($b)) {
				return $a > $b ? 1 : -1;
			}
			return strlen($a) < strlen($b) ? 1 : -1;
		});

		// Общий регвыр
		$totalRegex =
			'/(' .
			// Многосимвольные
			implode('|', $multipleCharCodes) .
			// Односимвольные
			'|[' . implode($ranges) . ']' .
			')+/ui';

		$written = file_put_contents(
			self::OUT_SOURCE_FILE,
			preg_replace(
				[
					'/const PARSE_DATETIME = [^;]*;/ui',
					'/\* @since .+ Last parsing.\n/ui',
					'/\* @since .+ Latest data.\n/ui',
					'/const UNICODE_DATA_DATETIME = [^;]*;/ui',
					'/const UNICODE_VERSION = [^;]*;/ui',
					'/\* Unicode version:[^\n]*\n/ui',
					'/const TOTAL_EMOJI_COUNT = [^;]*;/ui',
					'/\* Full Emoji regex[^\n]*\n/ui',
					'/const EMOJI_REGEX =[^;]*;/ui',
				],
				[
					"const PARSE_DATETIME = '" .
					preg_replace('/\+00:00$/', 'Z', date('c')) . "';",
					"* @since " . date('Y-m-d') . " Last parsing.\n",
					"* @since " . $date->format('Y-m-d') . " Latest data.\n",
					"const UNICODE_DATA_DATETIME = '" .
					preg_replace('/\+00:00$/', 'Z', $date->format('c')) . "';",
					"const UNICODE_VERSION = '" . $version . "';",
					"* Unicode version: " . $version . "\n",
					"const TOTAL_EMOJI_COUNT = " . count($unique) . ";",
					"* Full Emoji regex (" . strlen($totalRegex) . " bytes)\n",
					"const EMOJI_REGEX =\n\t\t" . self::regexWrap($totalRegex) . ";",
				],
				file_get_contents(self::OUT_SOURCE_FILE)
			)
		);
		if (!$written) {
			return 3;
		}

		$written = file_put_contents(
			self::OUT_README_FILE,
			preg_replace(
				'/Unicode version:[^\n]*\n/ui',
				'Unicode version: ' . $version . ".\n",
				file_get_contents(self::OUT_README_FILE)
			)
		);
		if (!$written) {
			return 4;
		}


		// Получение дополнительной информации обо всех эмодзи
		if ($this->withShortcodes) {
			$timeInfoStart = microtime(true);
			echo 'Getting info about all emojis...', PHP_EOL;
			$shortCodes = [];
			$noShortCodes = [];
			$nonUniqueShortCodes = [];
			$index = 0;
			foreach ($emojis as $emoji) {
				echo "\t", $index++, '/', $count, ' getting info about emoji ', $emoji, '...', PHP_EOL;
				$infoHtml = file_get_contents(self::EMOJIPEDIA_URL . $emoji);
				if (preg_match_all('#<span class="shortcode">:([^:]+):</span>#ui', $infoHtml, $matches)) {
					foreach ($matches[1] as $shortcode) {
						if (isset($shortCodes[$shortcode])) {
							if (!isset($nonUniqueShortCodes[$shortcode])) {
								$nonUniqueShortCodes[$shortcode] = [$shortCodes[$shortcode]];
							}
							$nonUniqueShortCodes[$shortcode][] = $emoji;
						} else {
							$shortCodes[$shortcode] = $emoji;
						}
					}
					echo "\t\t", 'Shortcodes: ', implode(', ', $matches[1]), PHP_EOL;
				} else {
					$noShortCodes[] = $emoji;
					echo "\t\t", 'Shortcodes not found :-(', PHP_EOL;
				}
			}

			$timeInfoEnd = microtime(true);

			echo 'Shortcodes found: ', count($shortCodes), PHP_EOL;
			echo 'Shortcodes not found: ', count($noShortCodes), '  (ideally 0)', PHP_EOL;
			echo 'Shortcodes non-unique: ', count($nonUniqueShortCodes), '  (ideally 0)', PHP_EOL;
			echo 'Time: ', sprintf('%.1f s', $timeInfoEnd - $timeInfoStart), PHP_EOL;
			echo 'No shortcodes:', PHP_EOL;
			var_dump($noShortCodes);
			echo 'Non-unique shortcodes:', PHP_EOL;
			var_dump($nonUniqueShortCodes);
		}


		// Аннотации и переводы названий (может быть когда-нибудь позже)
		if ($this->withTranslations) {
			$annotationsUrl = 'https://www.unicode.org/cldr/charts/latest/annotations/slavic.html';
			echo 'Getting contents of: ', $annotationsUrl;
			$html = file_get_contents($annotationsUrl);
			echo '  OK', PHP_EOL;
			// ... ... ... обработать аннотации и переводы ... ... ...
		}

		$timeDiff = microtime(true) - $timeStart;
		echo PHP_EOL, 'All done in ', sprintf('%.1f', $timeDiff), ' sec.', PHP_EOL;
		return 0;
	}

	private static function codeToPos(string $position): int
	{
		return hexdec(str_replace('U+', '', $position));
	}

	private static function posToCode(int $position): string
	{
		$code = strtoupper(dechex($position));
		if (strlen($code) === 2) {
			return '00' . $code;
		}
		return $code;
	}

	private static function codePointsToRegex(array $codePoints): string
	{
		return implode(
			array_map(
				static function ($item) {
					return '\x{' . $item . '}';
				},
				$codePoints
			)
		);
	}

	private static function regexWrap(string $string): string
	{
		return "'" . preg_replace('/(.{70,75}[}|-])/', "$1' .\n\t\t'", $string) . "'";
	}

	private function parseCommandParams(array $argv)
	{
		foreach ($argv as $argument) {
			$argument = strtolower($argument);
			switch ($argument) {
				case '--with-shortcodes':
				case '-c':
					$this->withShortcodes = true;
					break;
				case '--with-translations':
				case '-t':
					$this->withTranslations = true;
					break;
				case '--help':
				case '-h':
					$this->printHelp();
					break;
			}
		}
	}

	private function printHelp()
	{
		echo 'Emoji Parser', PHP_EOL, PHP_EOL;
		echo 'Params:', PHP_EOL;
		echo "\t", '-c  --with-shortcodes    Parse shortcodes. Takes long time!', PHP_EOL;
		echo "\t", '-t  --with-translations  Parse translations. Takes long time!', PHP_EOL;
		exit(0);
	}
}
