<?php

declare(strict_types=1);

namespace Drupal\Tests\athari_donate\Unit;

use Drupal\athari_donate\PhoneNumber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests phone number normalisation and validation.
 */
#[Group('athari_donate')]
class PhoneNumberTest extends UnitTestCase {

  /**
   * Inputs a donor might type and the Daraja format they must become.
   */
  public static function normaliseProvider(): array {
    return [
      'local 07' => ['0712345678', '254712345678'],
      'local 01' => ['0112345678', '254112345678'],
      'plus 254 07' => ['+254712345678', '254712345678'],
      'plus 254 01' => ['+254112345678', '254112345678'],
      'already 254 07' => ['254712345678', '254712345678'],
      'already 254 01' => ['254112345678', '254112345678'],
      'nine digits 7' => ['712345678', '254712345678'],
      'nine digits 1' => ['112345678', '254112345678'],
      'spaces' => ['0712 345 678', '254712345678'],
      'dashes' => ['0712-345-678', '254712345678'],
      'brackets and dots' => ['(0712).345.678', '254712345678'],
      'surrounding whitespace' => ["  +254 712 345 678\n", '254712345678'],
    ];
  }

  /**
   * Tests that typed numbers are normalised to the Daraja format.
   */
  #[DataProvider('normaliseProvider')]
  public function testNormalise(string $input, string $expected): void {
    $normalised = PhoneNumber::normalise($input);
    $this->assertSame($expected, $normalised);
    $this->assertTrue(PhoneNumber::isValid($normalised));
  }

  /**
   * Numbers that must be rejected after normalisation.
   */
  public static function invalidProvider(): array {
    return [
      'empty' => [''],
      'letters' => ['07abcdefgh'],
      'too short' => ['071234567'],
      'too long' => ['07123456789'],
      'wrong country code' => ['255712345678'],
      'landline style 02' => ['0212345678'],
      'uganda number' => ['256712345678'],
      'plus only' => ['+'],
      'sql-ish' => ["0712345678'; DROP TABLE x;--"],
    ];
  }

  /**
   * Tests that malformed numbers are not valid.
   */
  #[DataProvider('invalidProvider')]
  public function testInvalid(string $input): void {
    $this->assertFalse(PhoneNumber::isValid(PhoneNumber::normalise($input)));
  }

  /**
   * Tests that masking keeps only the start and the last three digits.
   */
  public function testMask(): void {
    $this->assertSame('25471****678', PhoneNumber::mask('254712345678'));
    $this->assertSame('***', PhoneNumber::mask('123'));
  }

}
