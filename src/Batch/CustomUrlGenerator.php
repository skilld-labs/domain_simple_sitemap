<?php

namespace Drupal\domain_simple_sitemap\Batch;

use Drupal\Core\Url;
use Drupal\domain\Entity\Domain;
use Drupal\simple_sitemap\Batch\CustomUrlGenerator as SimpleSitemapCustomUrlGenerator;

/**
 * Class CustomUrlGenerator
 * @package Drupal\domain_simple_sitemap\Batch
 */
class CustomUrlGenerator extends SimpleSitemapCustomUrlGenerator {

  /**
   * {@inheritdoc}
   */
  public function generate($custom_paths) {

    foreach ($this->getBatchIterationElements($custom_paths) as $i => $custom_path) {

      $this->setCurrentId($i);

      // todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()?
      if (!$this->pathValidator->isValid($custom_path['path'])) {
//        if (!(bool) $this->pathValidator->getUrlIfValidWithoutAccessCheck($custom_path['path'])) {
        $this->logger->m(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE,
          ['@path' => $custom_path['path'], '@custom_paths_url' => $GLOBALS['base_url'] . '/admin/config/search/simplesitemap/custom'])
          ->display('warning', 'administer sitemap settings')
          ->log('warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      $path = $url_object->getInternalPath();
      if ($this->batchInfo['remove_duplicates'] && $this->pathProcessed($path)) {
        continue;
      }

      $entity = $this->entityHelper->getEntityFromUrlObject($url_object);

      $path_data = [
        'path' => $path,
        'lastmod' => method_exists($entity, 'getChangedTime')
          ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($custom_path['priority']) ? $custom_path['priority'] : NULL,
        'changefreq' => !empty($custom_path['changefreq']) ? $custom_path['changefreq'] : NULL,
        'domain_id' => $custom_path['domain_id'],
      ];
      if (NULL !== $entity) {
        $path_data['entity_info'] = [
          'entity_type' => $entity->getEntityTypeId(),
          'id' => $entity->id()
        ];
      }
      $this->addUrl($path_data, $url_object);
    }
    $this->processSegment();
  }

  /**
   * {@inheritdoc}
   */
  protected function addUrl(array $path_data, Url $url_object = NULL) {
    if ($url_object !== NULL) {
      $url_object->setOption('base_url', Domain::load($path_data['domain_id'])
        ->getRawPath());
      $this->addUrlVariants($path_data, $url_object);
    }
    else {
      $this->context['results']['generate'][] = $path_data;
    }
  }
}
