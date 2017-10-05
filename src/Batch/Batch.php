<?php

namespace Drupal\domain_simple_sitemap\Batch;

use Drupal\Core\Cache\Cache;
use Drupal\simple_sitemap\Batch\Batch as SimpleSitemapBatch;

/**
 * Batch class.
 */
class Batch extends SimpleSitemapBatch {

  /**
   * {@inheritdoc}
   */
  public static function finishGeneration($success, $results, $operations) {
    if ($success) {
      $remove_sitemap = empty($results['chunk_count']);
      if (!empty($results['generate']) || $remove_sitemap) {
        \Drupal::service('domain_simple_sitemap.sitemap_generator')
          ->generateSitemap($results['generate'], $remove_sitemap);
      }
      Cache::invalidateTags(['simple_sitemap']);
      \Drupal::service('simple_sitemap.logger')->m(self::REGENERATION_FINISHED_MESSAGE,
        ['@url' => $GLOBALS['base_url'] . '/sitemap.xml'])
        ->display('status')
        ->log('info');
    }
    else {
      \Drupal::service('simple_sitemap.logger')->m(self::REGENERATION_FINISHED_ERROR_MESSAGE)
        ->display('error', 'administer sitemap settings')
        ->log('error');
    }
  }

}
