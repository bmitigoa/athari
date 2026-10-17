<?php

declare(strict_types=1);

namespace Drupal\athari_donate;

/**
 * A mobile-money provider that can ask a donor to approve a payment.
 *
 * Daraja (M-Pesa) implements this today. An MTN MoMo or Airtel Money provider
 * could implement the same interface and be selected in DonationManager.
 */
interface PaymentProviderInterface {

  /**
   * Machine name of the provider, for example "mpesa".
   */
  public function getId(): string;

  /**
   * Whether the provider has everything it needs to take payments.
   */
  public function isConfigured(): bool;

  /**
   * Asks the donor's phone to approve a payment.
   *
   * @param string $phone
   *   Payer's phone number in the provider's required format.
   * @param int $amount
   *   Whole amount in the provider's currency.
   * @param string $reference
   *   Short account reference shown on the donor's phone.
   * @param string $description
   *   Short transaction description.
   * @param string $callback_url
   *   Public URL the provider will POST the result to.
   *
   * @return array
   *   Normalised result with the keys merchant_request_id, checkout_request_id
   *   and customer_message.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   *   When the provider rejects the request or cannot be reached.
   */
  public function initiatePayment(string $phone, int $amount, string $reference, string $description, string $callback_url): array;

  /**
   * Asks the provider for the current state of a payment request.
   *
   * @param string $checkout_request_id
   *   The identifier returned by initiatePayment().
   *
   * @return array
   *   The keys state (processing, success, cancelled or failed), result_code
   *   and result_desc. The state is processing while the donor has not yet
   *   answered the prompt.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   *   When the provider cannot be reached.
   */
  public function queryPayment(string $checkout_request_id): array;

}
