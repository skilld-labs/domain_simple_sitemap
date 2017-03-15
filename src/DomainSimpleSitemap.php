<?php

namespace Drupal\domain_simple_sitemap;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\simple_sitemap\Form\FormHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * Class AprolisDomainAccessSitemap.
 *
 * @package Drupal\domain_simple_sitemap
 */
class DomainSimpleSitemap extends Simplesitemap {

  private $sitemapGenerator;
  private $configFactory;
  private $db;
  private $entityQuery;
  private $entityTypeManager;
  private $pathValidator;
  private static $allowedLinkSettings = [
    'entity' => ['index', 'priority'],
    'custom' => ['priority'],
  ];

  /**
   * Simplesitemap constructor.
   *
   * @param DomainSimpleSitemapGenerator $sitemapGenerator
   *   Sitemap generator.
   * @param ConfigFactory $configFactory
   *   Config factory.
   * @param Connection $database
   *   Database connection class.
   * @param QueryFactory $entityQuery
   *   Entity query class.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param PathValidator $pathValidator
   *   Path validator service.
   * @param DateFormatter $dateFormatter
   *   Date formatter.
   */
  public function __construct(
    DomainSimpleSitemapGenerator $sitemapGenerator,
    ConfigFactory $configFactory,
    Connection $database,
    QueryFactory $entityQuery,
    EntityTypeManagerInterface $entityTypeManager,
    PathValidator $pathValidator,
    DateFormatter $dateFormatter
  ) {
    parent::__construct($sitemapGenerator, $configFactory, $database, $entityQuery, $entityTypeManager, $pathValidator, $dateFormatter);
    $this->sitemapGenerator = $sitemapGenerator;
    $this->configFactory = $configFactory;
    $this->db = $database;
    $this->entityQuery = $entityQuery;
    $this->entityTypeManager = $entityTypeManager;
    $this->pathValidator = $pathValidator;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generates the sitemap for all languages and saves it to the db.
   *
   * @param string $from
   *   Can be 'form', 'cron', 'drush' or 'nobatch'.
   *   This decides how the batch process is to be run.
   */
  public function generateSitemap($from = 'form') {
    $this->sitemapGenerator
      ->setGenerator($this)
      ->setGenerateFrom($from)
      ->startGeneration();
  }

}
