<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Entity;

use Drupal\athari_donate\DonationInterface;
use Drupal\athari_donate\DonationStorageSchema;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\EntityViewsData;

/**
 * Defines the Donation entity: one mobile-money payment attempt.
 *
 * A donation starts as "pending" when the donor submits the form and is moved
 * to success, failed or cancelled when Daraja calls back.
 */
#[ContentEntityType(
  id: 'athari_donation',
  label: new TranslatableMarkup('Donation'),
  label_collection: new TranslatableMarkup('Donations'),
  label_singular: new TranslatableMarkup('donation'),
  label_plural: new TranslatableMarkup('donations'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'access' => EntityAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
    'storage_schema' => DonationStorageSchema::class,
  ],
  base_table: 'athari_donation',
  admin_permission: 'administer athari donations',
  label_count: [
    'singular' => '@count donation',
    'plural' => '@count donations',
  ],
)]
class Donation extends ContentEntityBase implements DonationInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    $receipt = $this->getReceipt();
    return $receipt !== '' ? $receipt : (string) new TranslatableMarkup('Donation @id', ['@id' => $this->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount(): int {
    return (int) round((float) $this->get('amount')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): string {
    return (string) $this->get('phone')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getReceipt(): string {
    return (string) $this->get('mpesa_receipt')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutRequestId(): string {
    return (string) $this->get('checkout_request_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getProject() {
    return $this->get('project')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // Never keep unvalidated phone formats on a donation record.
    $this->set('phone', preg_replace('/\D/', '', $this->getPhone()));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Project'))
      ->setDescription(new TranslatableMarkup('The project that receives the donation.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', ['target_bundles' => ['project' => 'project']])
      ->setDisplayConfigurable('view', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('decimal')
      ->setLabel(new TranslatableMarkup('Amount (KES)'))
      ->setRequired(TRUE)
      ->setSetting('precision', 12)
      ->setSetting('scale', 2)
      ->setDisplayConfigurable('view', TRUE);

    $fields['phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Phone'))
      ->setDescription(new TranslatableMarkup('Payer phone in 2547XXXXXXXX format.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 15)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue(self::STATUS_PENDING)
      ->setSetting('allowed_values', self::statusOptions())
      ->setDisplayConfigurable('view', TRUE);

    $fields['mpesa_receipt'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('M-Pesa receipt'))
      ->setSetting('max_length', 32)
      ->setDisplayConfigurable('view', TRUE);

    $fields['merchant_request_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Merchant request ID'))
      ->setSetting('max_length', 64);

    $fields['checkout_request_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Checkout request ID'))
      ->setDescription(new TranslatableMarkup('Daraja identifier used to match the callback to this donation.'))
      ->setSetting('max_length', 64);

    $fields['result_code'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Result code'));

    $fields['result_desc'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Result description'))
      ->setSetting('max_length', 255);

    $fields['paid'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Paid'))
      ->setDescription(new TranslatableMarkup('When M-Pesa recorded the payment.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Allowed status values and their labels.
   */
  public static function statusOptions(): array {
    return [
      self::STATUS_PENDING => 'Pending',
      self::STATUS_SUCCESS => 'Success',
      self::STATUS_FAILED => 'Failed',
      self::STATUS_CANCELLED => 'Cancelled',
    ];
  }

}
