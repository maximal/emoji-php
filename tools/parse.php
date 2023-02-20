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

	/** @var bool */
	private $withShortcodes = false;
	/** @var bool */
	private $withTranslations = false;

	public function run(array $argv): int
	{
		$timeStart = microtime(true);
		$outFile = __DIR__ . '/../src/Detector.php';
		$readmeFile = __DIR__ . '/../README.md';

		$this->parseCommandParams($argv);

		//// Поехали!

		$timeUnicodeStart = microtime(true);
		$listUrl = 'https://unicode.org/emoji/charts-15.0/emoji-list.html';
		echo 'Getting contents of: ', $listUrl;
		$html = file_get_contents($listUrl);
		echo '  OK', PHP_EOL;

		if (!preg_match('#<title>Emoji\s*List,\s*v([\d.]+)[^<]*</title>#ui', $html, $match)) {
			echo 'Error: Unicode version not found.', PHP_EOL;
			return 1;
		}
		$version = $match[1];


		if (!preg_match_all(
			'#<td class=\'code\'><a href=\'[^\']+\' name=\'[^\']+\'>([^<]+)</a></td>[\s\r\n]*' .
			'<td class=\'andr\'><a href=\'[^\']+\' target=\'[^\']+\'><img alt=\'([^\']+)\'\s+#ui',
			$html,
			$matches
		)) {
			echo 'Error: Emoji characters not found.', PHP_EOL;
			return 2;
		}

		$oneChar = [];
		$multipleChar = [];
		$codes = [];
		$emojis = [];
		$count = 0;
		foreach ($matches[2] as $index => $emoji) {
			$count++;
			$code = trim(str_replace('U+', '', $matches[1][$index]));
			echo "\t", $count, ': ', $emoji, ' => ', $code;
			if (strpos($code, ' ') === false) {
				// Односимвольный эмодзи
				$pos = self::codeToPos($code);
				echo ' => ', $pos;
				$converted = self::posToCode($pos);
				if ($converted !== $code) {
					die;
				}
				$codes []= $pos;
				$oneChar[$pos] = $emoji;
			} else {
				// Многосимвольный эмодзи
				$points = [];
				foreach (explode(' ', $code) as $point) {
					$points []= '\x{' . self::posToCode(hexdec($point)) . '}';
				}
				echo ' => ', implode($points);
				$multipleChar []= implode($points);
			}
			$emojis[$emoji] = $emoji;
			echo PHP_EOL;
		}

		$timeUnicodeEnd = microtime(true);

		echo 'Found one-character emojis: ', count($codes), PHP_EOL;
		echo 'Found multiple-character emojis: ', count($multipleChar), PHP_EOL;
		echo 'Found emojis: ', count($emojis), PHP_EOL;
		echo 'Time: ', sprintf('%.1f s', $timeUnicodeEnd - $timeUnicodeStart), PHP_EOL;


		// Обработка диапазонов
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
					$ranges []= '\x{' . self::posToCode($lastStart) . '}' .
						($lastCode > $lastStart + 1 ? '-' : '') .
						'\x{' . self::posToCode($lastCode) . '}';
				} elseif ($lastCode > 0) {
					// Всего один символ
					echo "\t", $lastCode, '    ', $oneChar[$lastCode], PHP_EOL;
					$ranges []= '\x{' . self::posToCode($lastCode) . '}';
				}
				$lastStart = $code;
			}
			$lastCode = $code;
		}

		// Long sequences first
		usort($multipleChar, static function ($a, $b) {
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
					'/const EMOJI_REGEX =[^;]*;/ui',
				],
				[
					"const PARSE_DATETIME = '" . date('c') . "';",
					"const UNICODE_VERSION = '" . $version . "';",
					"* Unicode version: " . $version . "\n",
					"const TOTAL_EMOJI_COUNT = " . $count . ";",
					"const EMOJI_REGEX =\n\t\t" .  self::stringWrap($totalRegex) . ";",
				],
				file_get_contents($outFile)
			)
		);
		if (!$written) {
			return 3;
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
		}

		$timeDiff = microtime(true) - $timeStart;
		echo 'All done in ', sprintf('%.1f', $timeDiff), ' sec.', PHP_EOL;
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

	private static function stringWrap(string $string): string
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
