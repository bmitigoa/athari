<?php

declare(strict_types=1);

namespace Drupal\athari_donate;

/**
 * Normalises and validates Safaricom (M-Pesa) phone numbers.
 *
 * Daraja requires the international format without a plus sign: country code
 * 254, then 7 or 1, then eight digits (12 digits in total), for example
 * 254712345678 or 254112345678.
 */
final class PhoneNumber {

  /**
   * The only format Daraja accepts.
   */
  public const PATTERN = '/^254[17]\d{8}$/';

  /**
   * Turns what a donor typed into the Daraja format where that is unambiguous.
   *
   * Spaces, dashes, brackets, dots and a leading plus are removed. Then:
   * - 07XXXXXXXX and 01XXXXXXXX become 2547XXXXXXXX and 2541XXXXXXXX;
   * - 7XXXXXXXX and 1XXXXXXXX (nine digits) get 254 in front;
   * - +254... and 254... are kept.
   * Anything else is returned cleaned but otherwise unchanged, so validation
   * rejects it.
   */
  public static function normalise(string $input): string {
    $number = preg_replace('/[\s\-().]/', '', trim($input)) ?? '';
    $number = ltrim($number, '+');
    if (preg_match('/^0([17]\d{8})$/', $number, $m)) {
      return '254' . $m[1];
    }
    if (preg_match('/^[17]\d{8}$/', $number)) {
      return '254' . $number;
    }
    return $number;
  }

  /**
   * Whether the number is already in the exact Daraja format.
   */
  public static function isValid(string $number): bool {
    return (bool) preg_match(self::PATTERN, $number);
  }

  /**
   * Masks a number for display, keeping only the start and the last digits.
   */
  public static function mask(string $number): string {
    if (strlen($number) < 8) {
      return str_repeat('*', strlen($number));
    }
    return substr($number, 0, 5) . str_repeat('*', strlen($number) - 8) . substr($number, -3);
  }

}
