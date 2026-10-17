<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Controller;

use Drupal\athari_donate\Service\DonationManager;
use Drupal\Core\Controller\ControllerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives the STK Push result that Safaricom POSTs to the callback URL.
 *
 * Safaricom cannot log in, so the endpoint is protected by an unguessable token
 * in the path and by matching the CheckoutRequestID to a donation we created.
 * It always answers 200 with the JSON Daraja expects: a non-200 answer would
 * only make Safaricom retry, and telling a caller why a request was ignored
 * would help an attacker. Problems are written to the athari_donate log.
 */
class CallbackController extends ControllerBase {

  /**
   * Largest request body that is parsed, in bytes. Real callbacks are ~1 KB.
   */
  protected const MAX_BODY = 65536;

  public function __construct(
    protected DonationManager $donations,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('athari_donate.donation_manager'),
      $container->get('logger.channel.athari_donate'),
    );
  }

  /**
   * Handles POST /athari/mpesa/callback/{token}.
   */
  public function receive(Request $request, string $token): JsonResponse {
    $accepted = new JsonResponse(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    $accepted->setPrivate();

    if (!hash_equals($this->donations->getCallbackSecret(), $token)) {
      $this->logger->warning('Callback received with a wrong token (IP @ip).', ['@ip' => $request->getClientIp()]);
      return $accepted;
    }

    $body = $request->getContent();
    if (!is_string($body) || $body === '' || strlen($body) > self::MAX_BODY) {
      $this->logger->warning('Callback ignored: empty or oversized body.');
      return $accepted;
    }

    $payload = json_decode($body, TRUE, 8);
    if (!is_array($payload)) {
      $this->logger->warning('Callback ignored: body is not valid JSON.');
      return $accepted;
    }

    // Idempotent: a repeat delivery of the same result changes nothing.
    $this->donations->processCallback($payload);
    return $accepted;
  }

}
