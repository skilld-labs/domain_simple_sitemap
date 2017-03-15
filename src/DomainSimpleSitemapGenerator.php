<?php

namespace Drupal\domain_simple_sitemap;

use Drupal\domain\Entity\Domain;
use Drupal\simple_sitemap\SitemapGenerator;
use \XMLWriter;
use Drupal\domain_simple_sitemap\Batch\Batch;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class AprolisDomainAccessSitemapGenerator.
 *
 * @package Drupal\domain_simple_sitemap
 */
class DomainSimpleSitemapGenerator extends SitemapGenerator {

  private $batch;
  private $db;
  private $moduleHandler;
  private $defaultLanguageId;
  private $generateFrom = 'form';
  private $isHreflangSitemap;
  private $generator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    Batch $batch,
    Connection $database,
    ModuleHandler $module_handler,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($batch, $database, $module_handler, $language_manager);
    $this->batch = $batch;
    $this->db = $database;
    $this->moduleHandler = $module_handler;
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->isHreflangSitemap = count($language_manager->getLanguages()) > 1;
  }

  /**
   * {@inheritdoc}
   */
  public function setGenerator($generator) {
    $this->generator = $generator;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setGenerateFrom($from) {
    $this->generateFrom = $from;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSitemap($links, $remove_sitemap = FALSE) {
    $new_links_array = [];
    foreach ($links as $link) {
      $chunk_id = $this->db->query('SELECT id FROM {simple_sitemap} WHERE domain_id = :domain_id', [':domain_id' => $link['domain_id']])->fetchField();
      if (!$chunk_id) {
        $chunk_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField() + 1;
        $values = [
          'id' => $chunk_id,
          'domain_id' => $link['domain_id'],
          'sitemap_string' => $this->generateSitemapChunk([]),
          'sitemap_created' => REQUEST_TIME,
        ];

        $this->db->insert('simple_sitemap')->fields($values)->execute();
      }
      $new_links_array[$chunk_id][$link['domain_id']][] = $link;
    }
    // Invoke alter hook.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    if ($remove_sitemap) {
      $this->db->truncate('simple_sitemap')->execute();
    }
    foreach ($new_links_array as $id => $new_links) {
      $values = [
        'id' => $id,
        'domain_id' => key($new_links),
        'sitemap_string' => $this->generateSitemapChunk(array_shift($new_links)),
        'sitemap_created' => REQUEST_TIME,
      ];

      $this->db->insert('simple_sitemap')->fields($values)->execute();
    }
  }

  /**
   * Wrapper method which takes links along with their options, lets other modules alter the links and then generates and saves the sitemap.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   * @param bool $remove_sitemap
   *   Remove old sitemap from database before inserting the new one.
   * @param string $domain_id
   *   Domain ID.
   */
  public function generateDomainSitemap($links, $remove_sitemap = FALSE, $domain_id = NULL) {
    // Invoke alter hook.
    $this->moduleHandler->alter('simple_sitemap_links', $links);
    $values = [
      'id' => $remove_sitemap ? 1 : $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField() + 1,
      'sitemap_string' => $this->generateSitemapChunk($links),
      'sitemap_created' => REQUEST_TIME,
      'domain_id' => $domain_id
    ];
    if ($remove_sitemap) {
      $this->db->truncate('simple_sitemap')->execute();
    }
    $this->db->insert('simple_sitemap')->fields($values)->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function generateSitemapIndex($chunks) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('sitemapindex');
    $writer->writeAttribute('xmlns', self::XMLNS);

    foreach ($chunks as $chunk_id => $chunk_data) {
      $domain = Domain::load($chunk_data->domain_id);
      $writer->startElement('sitemap');
      $writer->writeElement('loc', $domain->getPath() . 'sitemaps/'
        . $chunk_id . '/sitemap.xml');
      $writer->writeElement('lastmod', date_iso8601($chunk_data->sitemap_created));
      $writer->endElement();
    }
    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

  /**
   * Generates and returns a sitemap chunk.
   *
   * @param array $links
   *   All links with their multilingual versions and settings.
   *
   * @return string
   *   Sitemap chunk
   */
  private function generateSitemapChunk($links) {
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->setIndent(TRUE);
    $writer->startDocument(self::XML_VERSION, self::ENCODING);
    $writer->writeComment(self::GENERATED_BY);
    $writer->startElement('urlset');
    $writer->writeAttribute('xmlns', self::XMLNS);
    if ($this->isHreflangSitemap) {
      $writer->writeAttribute('xmlns:xhtml', self::XMLNS_XHTML);
    }

    foreach ($links as $link) {

      // Add each translation variant URL as location to the sitemap.
      $writer->startElement('url');
      $writer->writeElement('loc', $link['url']);

      // If more than one language is enabled, add all translation variant URLs
      // as alternate links to this location turning the sitemap into a hreflang
      // sitemap.
      if ($this->isHreflangSitemap) {
        foreach ($link['alternate_urls'] as $language_id => $alternate_url) {
          $writer->startElement('xhtml:link');
          $writer->writeAttribute('rel', 'alternate');
          $writer->writeAttribute('hreflang', $language_id);
          $writer->writeAttribute('href', $alternate_url);
          $writer->endElement();
        }
      }

      // Add lastmod if any.
      if (isset($link['lastmod'])) {
        $writer->writeElement('lastmod', $link['lastmod']);
      }

      // @todo: Implement changefreq here.

      // Add priority if any.
      if (isset($link['priority'])) {
        $writer->writeElement('priority', $link['priority']);
      }

      $writer->endElement();
    }
    $writer->endElement();
    $writer->endDocument();
    return $writer->outputMemory();
  }

  /**
   * Adds all operations to the batch and starts it.
   */
  public function startGeneration() {
    $this->batch->setBatchInfo([
      'from' => $this->generateFrom,
      'batch_process_limit' => !empty($this->generator->getSetting('batch_process_limit')) ? $this->generator->getSetting('batch_process_limit') : NULL,
      'max_links' => $this->generator->getSetting('max_links', 2000),
      'skip_untranslated' => $this->generator->getSetting('skip_untranslated', FALSE),
      'remove_duplicates' => $this->generator->getSetting('remove_duplicates', TRUE),
      'entity_types' => $this->generator->getBundleSettings(),
    ]);
    // For each chunk/domain generate custom URLs and entities.
    $domains = \Drupal::service('domain.loader')->loadMultiple();
    foreach ($domains as $domain_id => $domain) {
      if ($domain->status()) {
        // Add custom link generating operation.
        $this->batch->addOperation('generateCustomUrls', $this->getCustomUrlsData($domain_id));

        // Add entity link generating operations.
        foreach ($this->getEntityTypeData() as $data) {
          $data['domain_id'] = $domain_id;
          $this->batch->addOperation('generateBundleUrls', $data);
        }
      }
    }
    $this->batch->start();
  }

  /**
   * Returns a batch-ready data array for custom link generation.
   *
   * @param string $domain_id
   *   Domain ID.
   *
   * @return array
   *   Data to be processed.
   */
  private function getCustomUrlsData($domain_id) {
    $paths = [];
    foreach ($this->generator->getCustomLinks() as $i => $custom_path) {
      $paths[$i]['path'] = $custom_path['path'];
      $paths[$i]['priority'] = isset($custom_path['priority']) ? $custom_path['priority'] : NULL;
      // todo: implement lastmod.
      $paths[$i]['lastmod'] = NULL;
      $paths[$i]['domain_id'] = $domain_id;
    }
    return $paths;
  }

  /**
   * Collects entity metadata for entities that are set to be indexed and returns an array of batch-ready data sets for entity link generation.
   *
   * @return array
   *   Array of entity data.
   */
  private function getEntityTypeData() {
    $data_sets = [];
    $sitemap_entity_types = $this->generator->getSitemapEntityTypes();
    $entity_types = $this->generator->getBundleSettings();
    foreach ($entity_types as $entity_type_name => $bundles) {
      if (isset($sitemap_entity_types[$entity_type_name])) {
        $keys = $sitemap_entity_types[$entity_type_name]->getKeys();
        // Menu fix.
        $keys['bundle'] = $entity_type_name == 'menu_link_content' ? 'menu_name' : $keys['bundle'];
        foreach ($bundles as $bundle_name => $bundle_settings) {
          if ($bundle_settings['index']) {
            $data_sets[] = [
              'bundle_settings' => $bundle_settings,
              'bundle_name' => $bundle_name,
              'entity_type_name' => $entity_type_name,
              'keys' => $keys,
            ];
          }
        }
      }
    }
    return $data_sets;
  }

}
