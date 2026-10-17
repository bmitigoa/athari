<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Controller;

use Drupal\athari_donate\DonationInterface;
use Drupal\athari_donate\Service\DonationManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows the donor what happened to their payment.
 *
 * The page polls a small JSON endpoint. On each poll, a donation that is still
 * pending is also checked with the STK Push Query, so the page resolves even
 * when Safaricom cannot deliver the callback (for example on a local site).
 */
class StatusController extends ControllerBase {

  public function __construct(protected DonationManager $donations) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('athari_donate.donation_manager'));
  }

  /**
   * Loads the donation or responds with a 404.
   */
  protected function load(string $uuid): DonationInterface {
    $donation = $this->donations->loadByUuid($uuid);
    if (!$donation) {
      throw new NotFoundHttpException();
    }
    return $donation;
  }

  /**
   * The status page. It is rendered once and then updated by JavaScript.
   */
  public function page(string $uuid): array {
    $donation = $this->load($uuid);
    $data = $this->donations->statusData($donation);
    $project = $donation->getProject();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['athari-donation-status', 'athari-donation-status--' . $data['status']],
        'data-status-url' => Url::fromRoute('athari_donate.status_json', ['uuid' => $uuid])->toString(),
        'data-status' => $data['status'],
        'aria-live' => 'polite',
      ],
      'message' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['athari-donation-status__message']],
        '#value' => $data['message'],
      ],
      'summary' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['athari-donation-status__summary']],
        '#value' => $this->t('KES @amount to @project', [
          '@amount' => number_format($donation->getAmount()),
          '@project' => $project ? $project->label() : '',
        ]),
      ],
      'receipt' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['athari-donation-status__receipt']],
        '#value' => $data['receipt'] !== '' ? $this->t('M-Pesa receipt: @receipt', ['@receipt' => $data['receipt']]) : '',
      ],
      'links' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['athari-donation-status__links']],
        'again' => [
          '#type' => 'link',
          '#title' => $this->t('Make another donation'),
          '#url' => Url::fromRoute('athari_donate.donate'),
          '#attributes' => ['class' => ['athari-btn', 'athari-btn--earth']],
        ],
      ],
      '#attached' => ['library' => ['athari_donate/status']],
      '#cache' => ['max-age' => 0],
    ];
    return $build;
  }

  /**
   * JSON used by the page's polling script.
   */
  public function json(string $uuid): JsonResponse {
    $donation = $this->load($uuid);
    $donation = $this->donations->refreshPending($donation);
    $response = new JsonResponse($this->donations->statusData($donation));
    $response->setPrivate();
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

}
