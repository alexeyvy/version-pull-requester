<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use VersionPullRequester\RegexReplacer;

final class RegexReplacerTest extends TestCase
{
    public function testSimpleReplacing(): void
    {
        $replacer = new RegexReplacer();
        $input = '{
  "name": "blahblah",
  "autoload": {
    "psr-4": {
      "Validator\\": "src/Blahblah"
    }
  },
  "require": {
    "knplabs/github-api": "^2",
    "php-http/guzzle6-adapter": "^1.1"
  },
  "require-dev": {
    "phpunit/phpunit": "*"
  }
}';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api', '^3');

        $this->assertEquals($output, '{
  "name": "blahblah",
  "autoload": {
    "psr-4": {
      "Validator\\": "src/Blahblah"
    }
  },
  "require": {
    "knplabs/github-api": "^3",
    "php-http/guzzle6-adapter": "^1.1"
  },
  "require-dev": {
    "phpunit/phpunit": "*"
  }
}');
    }

    public function testNotReplacingWhenOldVersionDoesNotMatch()
    {
        $replacer = new RegexReplacer();
        $input = '
    "knplabs/github-api": "NOT_EXPECTED",
    "php-http/guzzle6-adapter": "^1.1"';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            '^3', 'EXPECTED_VERSION');

        $this->assertEquals($input, $output);
    }

    public function testReplacingWhenOldVersionMatches()
    {
        $replacer = new RegexReplacer();
        $input = '
    "knplabs/github-api": "EXPECTED_VERSION",
    "php-http/guzzle6-adapter": "^1.1"';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            '^3', 'EXPECTED_VERSION');

        $this->assertEquals($output, '
    "knplabs/github-api": "^3",
    "php-http/guzzle6-adapter": "^1.1"');
    }

    public function testNothingOccurredWhenLibraryNotPresentedInText()
    {
        $replacer = new RegexReplacer();
        $input = '
    "php-http/guzzle6-adapter": "^1.1"';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            '^3');

        $this->assertEquals($output, '
    "php-http/guzzle6-adapter": "^1.1"');
    }

    public function testRegexSpecialSymbolsDoNotBreakReplacing()
    {
        $replacer = new RegexReplacer();
        $input = '"knplabs/github-api": "^+1.)1!*("';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            '!^*+|)', '^+1.)1!*(');

        $this->assertEquals($output, '"knplabs/github-api": "!^*+|)"');
    }

    public function testConsideringPossibleWhitespaceAfterDependencyName()
    {
        $replacer = new RegexReplacer();
        $input = '"knplabs/github-api" : "^1"';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            '^2');

        $this->assertEquals($output, '"knplabs/github-api" : "^2"');
    }

    // for now JSON Replacer can only change the version only in a one place
/*    public function testReplacingEveryMatchEvenWhenDifferentVersions()
    {
        $replacer = new RegexReplacer();
        $input = '
    "knplabs/github-api": "OLD_VERSION",
    "php-http/guzzle6-adapter": "^1.1",
    "knplabs/github-api": "YET_ANOTHER_OLD_VERSION"
    ';

        $output = $replacer->replaceVersionOfDependency($input, 'knplabs/github-api',
            'NEW_VERSION');

        $this->assertEquals($output, '
    "knplabs/github-api": "NEW_VERSION",
    "php-http/guzzle6-adapter": "^1.1",
    "knplabs/github-api": "NEW_VERSION"
    ');
    }*/
}
