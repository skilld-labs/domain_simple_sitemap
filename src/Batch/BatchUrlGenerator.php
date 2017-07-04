<?php

namespace Drupal\domain_simple_sitemap\Batch;

use Drupal\domain_simple_sitemap\DomainSimpleSitemap;
use Drupal\domain_simple_sitemap\DomainSimpleSitemapGenerator;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\domain\Entity\Domain;
use Drupal\simple_sitemap\Logger;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\simple_sitemap\Batch\BatchUrlGenerator as SimpleSitemapBatchUrlGenerator;

/**
 * Class BatchUrlGenerator.
 *
 * @package Drupal\simple_sitemap\Batch
 */
class BatchUrlGenerator extends SimpleSitemapBatchUrlGenerator {

  use StringTranslationTrait;

  const ANONYMOUS_USER_ID = 0;

  const PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE = "The path @path has been omitted from the XML sitemap as it either does not exist, or it is not accessible to anonymous users.";

  const PROCESSING_PATH_MESSAGE = 'Processing path #@current out of @max: @path';

  const REGENERATION_FINISHED_MESSAGE = "The <a href='@url' target='_blank'>XML sitemap</a> has been regenerated for all languages.";

  const REGENERATION_FINISHED_ERROR_MESSAGE = 'The sitemap generation finished with an error.';

  protected $generator;

  protected $sitemapGenerator;

  protected $languageManager;

  protected $languages;

  protected $defaultLanguageId;

  protected $entityTypeManager;

  protected $pathValidator;

  protected $entityQuery;

  protected $logger;

  protected $anonUser;

  protected $context;

  protected $batchInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    DomainSimpleSitemap $generator,
    DomainSimpleSitemapGenerator $sitemap_generator,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    PathValidator $path_validator,
    QueryFactory $entity_query,
    Logger $logger
  ) {
    parent::__construct($generator, $sitemap_generator, $language_manager, $entity_type_manager, $path_validator, $entity_query, $logger);
    $this->generator = $generator;
    // todo: using only one method, maybe make method static instead?
    $this->sitemapGenerator = $sitemap_generator;
    $this->languageManager = $language_manager;
    $this->languages = $language_manager->getLanguages();
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
    $this->entityQuery = $entity_query;
    $this->logger = $logger;
    $this->anonUser = $this->entityTypeManager->getStorage('user')->load(self::ANONYMOUS_USER_ID);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationEntities($entity_info) {
    $query = $this->entityQuery->get($entity_info['entity_type_name']);

    if (!empty($entity_info['keys']['id'])) {
      $query->sort($entity_info['keys']['id'], 'ASC');
    }
    if (!empty($entity_info['keys']['bundle'])) {
      $query->condition($entity_info['keys']['bundle'], $entity_info['bundle_name']);
    }
    if (!empty($entity_info['keys']['status'])) {
      $query->condition($entity_info['keys']['status'], 1);
    }
    if ($entity_info['entity_type_name'] == 'node') {
      $orGroupDomain = $query->orConditionGroup()
        ->condition('field_domain_access.target_id', $entity_info['domain_id'])
        ->condition('field_domain_all_affiliates', 1);
      $query->condition($orGroupDomain);
    }

    if ($this->needsInitialization()) {
      $count_query = clone $query;
      $this->initializeBatch($count_query->count()->execute());
    }

    if ($this->isBatch()) {
      $query->range($this->context['sandbox']['progress'], $this->batchInfo['batch_process_limit']);
    }

    return $this->entityTypeManager
      ->getStorage($entity_info['entity_type_name'])
      ->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  protected function addUrlVariants($url_object, $path_data, $entity) {
    $alternate_urls = [];
    $url_object->setOption('base_url', Domain::load($path_data['domain_id'])
      ->getRawPath());

    $translation_languages = !is_null($entity) && $this->batchInfo['skip_untranslated']
      ? $entity->getTranslationLanguages() : $this->languages;

    // Entity is not translated.
    if (!is_null($entity) && isset($translation_languages['und'])) {
      if ($url_object->access($this->anonUser)) {
        $url_object->setOption('language', $this->languages[$this->defaultLanguageId]);
        $alternate_urls[$this->defaultLanguageId] = $url_object->toString();
      }
    }
    else {
      // Including only translated variants of entity.
      if (!is_null($entity)) {
        foreach ($translation_languages as $language) {
          if ($entity->hasTranslation($language->getId())) {
            $url_object->setOption('language', $language);
            // Check if anonymous user can access the path.
            $fullUrlObject = Url::fromUri($url_object->toString());
            if ($fullUrlObject->access($this->anonUser)) {
              $alternate_urls[$language->getId()] = $url_object->toString();
            }
          }
        }
      }

      // Not an entity or including all untranslated variants.
      elseif ($url_object->access($this->anonUser)) {
        foreach ($translation_languages as $language) {
          if ($entity && !$entity->hasTranslation($language->getId())) {
            continue;
          }
          $url_object->setOption('language', $language);
          $alternate_urls[$language->getId()] = $url_object->toString();
        }
      }
    }

    foreach ($alternate_urls as $langcode => $url) {
      $this->context['results']['generate'][] = $path_data + [
        'langcode' => $langcode,
        'url' => $url,
        'alternate_urls' => $alternate_urls,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getBatchIterationCustomPaths(array $custom_paths) {

    if ($this->needsInitialization()) {
      $this->initializeBatch(count($custom_paths));
    }

    if ($this->isBatch()) {
      $custom_paths = array_slice($custom_paths, $this->context['sandbox']['progress'], $this->batchInfo['batch_process_limit']);
    }

    return $custom_paths;
  }

  /**
   * {@inheritdoc}
   */
  public function generateBundleUrls(array $entity_info) {

    foreach ($this->getBatchIterationEntities($entity_info) as $entity_id => $entity) {

      $this->setCurrentId($entity_id);

      $entity_settings = $this->generator->getEntityInstanceSettings($entity_info['entity_type_name'], $entity_id);

      if (empty($entity_settings['index'])) {
        continue;
      }

      switch ($entity_info['entity_type_name']) {
        // Loading url object for menu links.
        case 'menu_link_content':
          if (!$entity->isEnabled()) {
            continue 2;
          }
          $url_object = $entity->getUrlObject();
          break;

        // Loading url object for other entities.
        default:
          $url_object = $entity->toUrl();
      }

      // Do not include external paths.
      if (!$url_object->isRouted()) {
        continue;
      }

      $path = $url_object->getInternalPath();

      $url_object->setOption('absolute', TRUE);

      $path_data = [
        'path' => $path,
        'entity_info' => ['entity_type' => $entity_info['entity_type_name'], 'id' => $entity_id],
        'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => $entity_settings['priority'],
        'domain_id' => $entity_info['domain_id'],
      ];
      $this->addUrlVariants($url_object, $path_data, $entity);
    }
    $this->processSegment();
  }

  /**
   * {@inheritdoc}
   */
  public function generateCustomUrls(array $custom_paths) {

    $custom_paths = $this->getBatchIterationCustomPaths($custom_paths);

    if ($this->needsInitialization()) {
      $this->initializeBatch(count($custom_paths));
    }

    foreach ($custom_paths as $i => $custom_path) {
      $this->setCurrentId($i);

      /* @todo: Change to different function, as this also checks if current user has access. The user however varies depending if process was started from the web interface or via cron/drush. Use getUrlIfValidWithoutAccessCheck()? */
      if (!$this->pathValidator->isValid($custom_path['path'])) {
        $this->logger->m(self::PATH_DOES_NOT_EXIST_OR_NO_ACCESS_MESSAGE, ['@path' => $custom_path['path']])
          ->display('warning', 'administer sitemap settings')
          ->log('warning');
        continue;
      }
      $url_object = Url::fromUserInput($custom_path['path'], ['absolute' => TRUE]);

      $path = $url_object->getInternalPath();

      $entity = $this->getEntityFromUrlObject($url_object);

      $path_data = [
        'path' => $path,
        'lastmod' => method_exists($entity, 'getChangedTime') ? date_iso8601($entity->getChangedTime()) : NULL,
        'priority' => isset($custom_path['priority']) ? $custom_path['priority'] : NULL,
        'domain_id' => $custom_path['domain_id'],
      ];
      if (!is_null($entity)) {
        $path_data['entity_info'] = ['entity_type' => $entity->getEntityTypeId(), 'id' => $entity->id()];
      }
      $this->addUrlVariants($url_object, $path_data, $entity);
    }
    $this->processSegment();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityFromUrlObject($url_object) {
    $route_parameters = $url_object->getRouteParameters();

    return !empty($route_parameters) && $this->entityTypeManager
      ->getDefinition($entity_type_id = key($route_parameters), FALSE)
      ? $this->entityTypeManager->getStorage($entity_type_id)
        ->load($route_parameters[$entity_type_id])
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function finishGeneration($success, $results, $operations) {
    if ($success) {
      $remove_sitemap = empty($results['chunk_count']);
      if (!empty($results['generate']) || $remove_sitemap) {
        $this->sitemapGenerator->generateSitemap($results['generate'], $remove_sitemap);
      }
      Cache::invalidateTags(['simple_sitemap']);
      $this->logger->m(self::REGENERATION_FINISHED_MESSAGE,
        ['@url' => $GLOBALS['base_url'] . '/sitemap.xml'])
        ->display('status')
        ->log('info');
    }
    else {
      $this->logger->m(self::REGENERATION_FINISHED_ERROR_MESSAGE)
        ->display('error', 'administer sitemap settings')
        ->log('error');
    }
  }

}
