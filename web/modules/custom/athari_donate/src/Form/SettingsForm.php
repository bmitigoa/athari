<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Form;

use Drupal\athari_donate\Exception\DarajaException;
use Drupal\athari_donate\Service\DarajaClient;
use Drupal\athari_donate\Service\DonationManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the Daraja integration.
 *
 * Credentials (consumer key, consumer secret, passkey) are deliberately NOT
 * editable here. They come from $config overrides in settings.local.php, so
 * they never reach the database, the config export or Git. The form shows
 * whether each one is present, never its value. Any other setting that is
 * overridden is shown read-only.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Settings the form edits and the secrets it only reports on.
   */
  protected const SECRETS = [
    'consumer_key' => 'Consumer key',
    'consumer_secret' => 'Consumer secret',
    'passkey' => 'Lipa na M-Pesa passkey',
  ];

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected DarajaClient $daraja,
    protected DonationManager $donations,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('athari_donate.daraja_client'),
      $container->get('athari_donate.donation_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'athari_donate_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['athari_donate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Configuration with overrides applied, so read-only state is accurate.
    $config = $this->config('athari_donate.settings');
    $note = $this->t('Set in settings.local.php, so it is read-only here.');

    $form['environment'] = [
      '#type' => 'radios',
      '#title' => $this->t('Environment'),
      '#options' => [
        'sandbox' => $this->t('Sandbox (test money, safe)'),
        'production' => $this->t('Production (real money)'),
      ],
      '#default_value' => $config->get('environment') === 'production' ? 'production' : 'sandbox',
      '#disabled' => $config->hasOverrides('environment'),
      '#description' => $config->hasOverrides('environment') ? $note : '',
    ];
    $form['shortcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Business short code'),
      '#default_value' => $config->get('shortcode'),
      '#size' => 12,
      '#disabled' => $config->hasOverrides('shortcode'),
      '#description' => $config->hasOverrides('shortcode') ? $note : $this->t('The paybill or till number. The Daraja sandbox uses 174379.'),
    ];

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Credentials'),
      '#open' => TRUE,
      '#description' => $this->t('Credentials are read only from <code>settings.local.php</code> and are never saved by this form, exported to config or shown on screen.'),
    ];
    foreach (self::SECRETS as $name => $label) {
      $set = $this->daraja->isOverridden($name);
      $missing = $this->t("<strong>Not set.</strong> Add <code>\$config['athari_donate.settings']['@name']</code> to settings.local.php.", ['@name' => $name]);
      $form['credentials'][$name] = [
        '#type' => 'item',
        '#title' => $label,
        '#markup' => $set ? $this->t('Configured (value hidden)') : $missing,
      ];
    }

    $form['callback_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Public callback base URL'),
      '#default_value' => $config->get('callback_base_url'),
      '#disabled' => $config->hasOverrides('callback_base_url'),
      '#description' => $config->hasOverrides('callback_base_url') ? $note : $this->t('Safaricom must be able to reach this site over the internet. Leave empty on a public site. On a local machine, enter the address of your tunnel (for example https://example.trycloudflare.com) without a trailing slash.'),
    ];

    $callback_url = $this->donations->getCallbackUrl();
    $host = (string) parse_url($callback_url, PHP_URL_HOST);
    $local = $host === 'localhost' || str_ends_with($host, '.ddev.site') || str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
    $form['callback_preview'] = [
      '#type' => 'item',
      '#title' => $this->t('Callback URL sent to Daraja'),
      '#markup' => '<code>' . htmlspecialchars(preg_replace('/[0-9a-f]{32}$/', '••••••••••••••••', $callback_url) ?? '') . '</code>',
      '#description' => $local ? $this->t('<strong>This address is only reachable from your own computer.</strong> Daraja cannot deliver callbacks to it, so the status page will fall back to the STK Push Query.') : $this->t('The last part is a secret token. Regenerate it if it is ever exposed.'),
    ];
    $form['regenerate_token'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate a new callback token when saving'),
    ];

    $form['limits'] = [
      '#type' => 'details',
      '#title' => $this->t('Donation limits (KES)'),
      '#open' => FALSE,
    ];
    foreach (['min_amount' => $this->t('Minimum amount'), 'max_amount' => $this->t('Maximum amount')] as $name => $label) {
      $form['limits'][$name] = [
        '#type' => 'number',
        '#title' => $label,
        '#min' => 1,
        '#default_value' => $config->get($name),
        '#disabled' => $config->hasOverrides($name),
      ];
    }

    $form = parent::buildForm($form, $form_state);
    $form['actions']['test'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test connection to Daraja'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $base = trim((string) $form_state->getValue('callback_base_url'));
    if ($base !== '' && !preg_match('#^https://[^\s/]+$#i', rtrim($base, '/'))) {
      $form_state->setErrorByName('callback_base_url', $this->t('Use an https address without a path, for example https://example.trycloudflare.com'));
    }
    $min = (int) $form_state->getValue('min_amount');
    $max = (int) $form_state->getValue('max_amount');
    if ($min > 0 && $max > 0 && $min > $max) {
      $form_state->setErrorByName('min_amount', $this->t('The minimum cannot be larger than the maximum.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Edit the raw config object, so overridden values are not written back.
    $editable = $this->configFactory->getEditable('athari_donate.settings');
    $overridden = $this->config('athari_donate.settings');
    foreach (['environment', 'shortcode', 'callback_base_url', 'min_amount', 'max_amount'] as $name) {
      if (!$overridden->hasOverrides($name)) {
        $value = $form_state->getValue($name);
        $editable->set($name, in_array($name, ['min_amount', 'max_amount'], TRUE) ? (int) $value : trim((string) $value));
      }
    }
    $editable->save();

    if ($form_state->getValue('regenerate_token')) {
      $this->donations->regenerateCallbackSecret();
      $this->messenger()->addStatus($this->t('A new callback token was generated.'));
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the "Test connection" button.
   */
  public function testConnection(array &$form, FormStateInterface $form_state): void {
    if (!$this->daraja->isConfigured()) {
      $this->messenger()->addWarning($this->t('Add the shortcode and the three credentials in settings.local.php first.'));
      return;
    }
    try {
      $this->daraja->getAccessToken(TRUE);
      $this->messenger()->addStatus($this->t('Connected to Daraja (@env). An access token was issued.', ['@env' => $this->daraja->getEnvironment()]));
    }
    catch (DarajaException $e) {
      $this->messenger()->addError($this->t('Could not get a token from Daraja. Check the credentials and the environment. Details are in the athari_donate log.'));
    }
  }

}
