<?php
/**
 * Small testing.
 *
 * @author MaximAL
 * @date 2020-01-03
 * @time 17:43
 * @since 2020-01-03 First version.
 *
 * @copyright ©  MaximAL, Sijeko  2019
 * @link https://maximals.ru
 * @link https://sijeko.ru
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Maximal\Emoji\Detector;

$string = count($argv) > 1 ? $argv[1] : 'flag 🏴󠁧󠁢󠁳󠁣󠁴󠁿 !';

echo Detector::getInfo(), PHP_EOL;
echo '    input string:  ', $string, PHP_EOL;
echo ' contains emojis:  ', Detector::containsEmoji($string) ? 'yes' : 'no', PHP_EOL;
echo '     only emojis:  ', Detector::onlyEmoji($string) ? 'yes' : 'no', PHP_EOL;
echo '  without emojis:  \'', Detector::removeEmoji($string), '\'', PHP_EOL;
