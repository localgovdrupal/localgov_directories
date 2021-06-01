<?php

namespace Drupal\Tests\localgov_directories\Unit\Processor;

use Drupal\localgov_directories\Plugin\search_api\processor\Stopwords;
use Drupal\Tests\search_api\Unit\Processor\ProcessorTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the "Stopwords in Strings" processor.
 *
 * @covers \Drupal\localgov_directories\Plugin\search_api\processor\Stopwords
 * @group localgov_directories
 */
class StopwordsTest extends UnitTestCase {

  use ProcessorTestTrait;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->processor = new Stopwords([], 'localgov_string_stopwords', []);
  }

  /**
   * Tests the process() method of the Stopwords processor.
   *
   * @param string $passed_value
   *   The string that should be passed to process().
   * @param string $expected_value
   *   The expected altered string.
   * @param string[] $stopwords
   *   The stopwords with which to configure the test processor.
   *
   * @dataProvider processDataProvider
   */
  public function testProcess($passed_value, $expected_value, array $stopwords) {
    $this->processor->setConfiguration(['stopwords' => $stopwords]);
    $this->invokeMethod('process', [&$passed_value]);
    $this->assertEquals($expected_value, $passed_value);
  }

  /**
   * Data provider for testStopwords().
   *
   * Processor checks for exact case, and tokenized content.
   */
  public function processDataProvider() {
    return [
      [
        'the',
        '',
        ['the'],
      ],
      [
        'theme',
        'theme',
        ['the'],
      ],
      [
        'bathe',
        'bathe',
        ['the'],
      ],
      [
        'bother',
        'bother',
        ['the'],
      ],
      [
        'the theme',
        'theme',
        ['the'],
      ],
      [
        'the first the bother',
        'first bother',
        ['the'],
      ],
      [
        'ÄÖÜÀÁ<>»«û',
        'ÄÖÜÀÁ<>»«û',
        ['stopword1', 'ÄÖÜÀÁ<>»«', 'stopword3'],
      ],
      [
        'ÄÖÜÀÁ',
        '',
        ['stopword1', 'ÄÖÜÀÁ', 'stopword3'],
      ],
      [
        'ÄÖÜÀÁ stopword1',
        '',
        ['stopword1', 'ÄÖÜÀÁ', 'stopword3'],
      ],
    ];
  }

}
