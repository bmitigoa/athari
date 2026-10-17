<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Exception;

/**
 * Thrown when Daraja cannot be reached or rejects a request.
 *
 * The message is safe to show to a donor. Technical detail is logged instead.
 */
class DarajaException extends \RuntimeException {
}
