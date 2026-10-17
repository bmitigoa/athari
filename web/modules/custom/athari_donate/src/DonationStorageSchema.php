<?php

declare(strict_types=1);

namespace Drupal\athari_donate;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Adds database indexes the callback and reports rely on.
 */
class DonationStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $table = $this->storage->getBaseTable();
    // The callback looks a donation up by its CheckoutRequestID.
    $schema[$table]['indexes']['athari_donation__checkout_request_id'] = ['checkout_request_id'];
    // Reports filter and total by project and status.
    $schema[$table]['indexes']['athari_donation__project_status'] = ['project', 'status'];
    return $schema;
  }

}
