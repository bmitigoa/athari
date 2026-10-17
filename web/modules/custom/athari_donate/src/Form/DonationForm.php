<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Form;

use Drupal\athari_donate\Exception\DarajaException;
use Drupal\athari_donate\PaymentProviderInterface;
use Drupal\athari_donate\PhoneNumber;
use Drupal\athari_donate\Service\DonationManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The public donation form: choose a project, an amount and an M-Pesa number.
 *
 * On submit it saves a pending donation, asks Daraja to send an STK Push to the
 * donor's phone and sends the donor to the status page.
 */
class DonationForm extends FormBase {

  /**
   * How many form submissions one visitor may make in the flood window.
   */
  protected const FLOOD_LIMIT = 5;

  /**
   * The flood window in seconds.
   */
  protected const FLOOD_WINDOW = 600;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DonationManager $donations,
    protected PaymentProviderInterface $provider,
    protected FloodInterface $flood,
    protected ConfigFactoryInterface $settingsFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('athari_donate.donation_manager'),
      $container->get('athari_donate.daraja_client'),
      $container->get('flood'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'athari_donate_donation_form';
  }

  /**
   * Returns the projects that can receive donations (published and active).
   *
   * @return string[]
   *   Project titles keyed by node ID.
   */
  protected function getProjectOptions(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project')
      ->condition('status', 1)
      ->condition('field_status', 'active')
      ->sort('title')
      ->execute();
    $options = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $options[$node->id()] = $node->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $project = NULL): array {
    $settings = $this->settingsFactory->get('athari_donate.settings');
    $options = $this->getProjectOptions();

    if (!$this->provider->isConfigured() || !$options) {
      $form['unavailable'] = [
        '#markup' => '<p>' . $this->t('Online donations are not available right now. Please check back soon.') . '</p>',
      ];
      if ($this->currentUser()->hasPermission('administer athari donate')) {
        $form['admin_hint'] = [
          '#markup' => '<p><em>' . $this->t('Administrator note: finish the setup on the <a href=":url">Athari Donate settings</a> page.', [':url' => '/admin/config/athari/donate']) . '</em></p>',
        ];
      }
      return $form;
    }

    $default_project = NULL;
    if ($project && $project->bundle() === 'project' && isset($options[$project->id()])) {
      $default_project = $project->id();
    }
    elseif ($project) {
      $this->messenger()->addWarning($this->t('"@title" is not taking donations at the moment. You can choose another project below.', ['@title' => $project->label()]));
    }

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Donate by M-Pesa. After you press the button, a payment prompt appears on your phone. Enter your M-Pesa PIN to approve it.') . '</p>',
    ];
    $form['project'] = [
      '#type' => 'select',
      '#title' => $this->t('Project'),
      '#options' => $options,
      '#default_value' => $default_project,
      '#empty_option' => $this->t('- Choose a project -'),
      '#required' => TRUE,
    ];
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount (KES)'),
      '#min' => (int) ($settings->get('min_amount') ?: 1),
      '#max' => (int) ($settings->get('max_amount') ?: 150000),
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Whole shillings only.'),
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('M-Pesa phone number'),
      '#required' => TRUE,
      '#maxlength' => 20,
      '#attributes' => ['placeholder' => '07XXXXXXXX', 'autocomplete' => 'tel'],
      '#description' => $this->t('For example 0712345678, 0112345678 or +254712345678.'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Donate with M-Pesa'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['project'])) {
      return;
    }
    $settings = $this->settingsFactory->get('athari_donate.settings');

    if (!$this->flood->isAllowed('athari_donate.donate', self::FLOOD_LIMIT, self::FLOOD_WINDOW)) {
      $form_state->setErrorByName('', $this->t('You have tried several times. Please wait a few minutes and try again.'));
      return;
    }

    $project_id = (string) $form_state->getValue('project');
    if (!isset($this->getProjectOptions()[$project_id])) {
      $form_state->setErrorByName('project', $this->t('Please choose a project from the list.'));
    }

    $raw_amount = trim((string) $form_state->getValue('amount'));
    $min = (int) ($settings->get('min_amount') ?: 1);
    $max = (int) ($settings->get('max_amount') ?: 150000);
    if (!preg_match('/^\d+$/', $raw_amount)) {
      $form_state->setErrorByName('amount', $this->t('Enter a whole number of shillings, for example 500.'));
    }
    elseif ((int) $raw_amount < $min || (int) $raw_amount > $max) {
      $form_state->setErrorByName('amount', $this->t('The amount must be between KES @min and KES @max.', [
        '@min' => number_format($min),
        '@max' => number_format($max),
      ]));
    }

    $phone = PhoneNumber::normalise((string) $form_state->getValue('phone'));
    if (!PhoneNumber::isValid($phone)) {
      $form_state->setErrorByName('phone', $this->t('Enter a Safaricom number such as 0712345678 or 254712345678.'));
    }
    else {
      $form_state->setValue('phone', $phone);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Count every real attempt, successful or not, towards the flood limit.
    $this->flood->register('athari_donate.donate', self::FLOOD_WINDOW);

    $project = $this->entityTypeManager->getStorage('node')->load((int) $form_state->getValue('project'));
    $donation = $this->donations->createPending($project, (int) $form_state->getValue('amount'), (string) $form_state->getValue('phone'));

    try {
      $this->donations->initiate($donation);
    }
    catch (DarajaException $e) {
      // The exception message is written for donors; details are in the log.
      $this->messenger()->addError($e->getMessage());
      $form_state->setRebuild();
      return;
    }
    $form_state->setRedirect('athari_donate.status', ['uuid' => $donation->uuid()]);
  }

}
