# Emoji Detecting and Processing

Unicode version: 15.0.


## Installation

Install this library using the [Composer](https://getcomposer.org) `require` command:

```sh
composer require maximal/emoji '^1.0'
```

or add the package name to the `require` section of your `composer.json` file:
```
"require": {
	"maximal/emoji": "^1.0"
}
```

and then run:
```sh
composer update
```

Then include Composer autoload anywhere in your code:
```php
require_once __DIR__ . '/vendor/autoload.php';
```


## Usage

```php
use Maximal\Emoji\Detector;

// Whether the given string contains emoji characters
$isEmojiFound = Detector::containsEmoji($string);
// 'test' -> false
// 'test 👍' -> true

// Whether the given string consists of emoji characters only
$isEmojiOnly = Detector::onlyEmoji($string);
// 'test 👍' -> false
// '👍😘' -> true

// String without any emoji character
$stringWithoutEmoji = Detector::removeEmoji($string);
// 'test 👍' -> 'test '
// '👍😘' -> ''

// Starting emojis of the string
$startingEmojis = Detector::startingEmojis($string);
// '👍😘 test' -> ['👍', '😘']
// 'test 👍' -> []
```

### `containsEmoji($string)`
Detects whether the given string contains one or more emoji characters.

### `onlyEmoji($string, $ignoreWhitespace = true)`
Detects whether the given string consists of emoji characters only.

This method ignores any spaces, tabs and other whitespace characters (`\s`).
Pass `false` to the second parameter for not ignoring whitespace characters.

### `removeEmoji($string)`
Returns the given string with all emoji characters removed.

### `startingEmojis($string, $ignoreWhitespace = true)`
Returns an array of starting emojis of the input string.

This method ignores any spaces, tabs and other whitespace characters (`\s`).
Pass `false` to the second parameter for not ignoring whitespace characters.


## Tests

Run simple tests:
```sh
php test/tests.php
```

Expected output:
```
Tests total: 78
        run: 78
  succeeded: 78
     failed: 0
```


## Contact the author
* Website: https://maximals.ru (Russian)
* Twitter: https://twitter.com/almaximal
* Telegram: https://t.me/maximal
* Sijeko Company: https://sijeko.ru (web, mobile, desktop applications development and graphic design)
* Personal GitHub: https://github.com/maximal
* Company’s GitHub: https://github.com/sijeko
