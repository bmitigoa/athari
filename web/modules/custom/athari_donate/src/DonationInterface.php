<?php

declare(strict_types=1);

namespace Drupal\athari_donate;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Describes a donation record.
 */
interface DonationInterface extends ContentEntityInterface {

  /**
   * The donor has been asked to pay; the result is not known yet.
   */
  public const STATUS_PENDING = 'pending';

  /**
   * Daraja confirmed the payment.
   */
  public const STATUS_SUCCESS = 'success';

  /**
   * The payment failed (wrong PIN, insufficient funds, timeout and so on).
   */
  public const STATUS_FAILED = 'failed';

  /**
   * The donor cancelled the prompt on their phone.
   */
  public const STATUS_CANCELLED = 'cancelled';

  /**
   * Returns the status machine name.
   */
  public function getStatus(): string;

  /**
   * Sets the status machine name.
   */
  public function setStatus(string $status): static;

  /**
   * Returns the donated amount in whole shillings.
   */
  public function getAmount(): int;

  /**
   * Returns the payer's phone number (2547XXXXXXXX).
   */
  public function getPhone(): string;

  /**
   * Returns the M-Pesa receipt number, or an empty string.
   */
  public function getReceipt(): string;

  /**
   * Returns the Daraja CheckoutRequestID, or an empty string.
   */
  public function getCheckoutRequestId(): string;

  /**
   * Returns the project node, or NULL.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The project that receives the donation.
   */
  public function getProject();

}
