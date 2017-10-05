<?php

namespace Drupal\domain_simple_sitemap;

use Drupal\simple_sitemap\EntityHelper;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathValidator;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\simple_sitemap\Simplesitemap;

/**
 * Class DomainSimpleSitemap.
 *
 * @package Drupal\domain_simple_sitemap
 */
class DomainSimpleSitemap extends Simplesitemap {

  /**
   * Sitemap generator.
   *
   * @var \Drupal\simple_sitemap\SitemapGenerator
   */
  protected $sitemapGenerator;

  /**
   * Entity helper for simple_sitemap.
   *
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Entity manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * Alloweb link settings.
   *
   * @var array
   */
  protected static $allowedLinkSettings = [
    'entity' => ['index', 'priority'],
    'custom' => ['priority'],
  ];

  /**
   * DomainSimpleSitemap constructor.
   *
   * @param \Drupal\domain_simple_sitemap\DomainSimpleSitemapGenerator $sitemapGenerator
   *   Sitemap generator.
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   *   Entity helper.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Path\PathValidator $pathValidator
   *   Path validator.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   Data formater.
   */
  public function __construct(
    DomainSimpleSitemapGenerator $sitemapGenerator,
    EntityHelper $entityHelper,
    ConfigFactory $configFactory,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    PathValidator $pathValidator,
    DateFormatter $dateFormatter
  ) {
    parent::__construct($sitemapGenerator, $entityHelper, $configFactory, $database, $entityTypeManager, $pathValidator, $dateFormatter);
    $this->sitemapGenerator = $sitemapGenerator;
    $this->entityHelper = $entityHelper;
    $this->configFactory = $configFactory;
    $this->db = $database;
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
