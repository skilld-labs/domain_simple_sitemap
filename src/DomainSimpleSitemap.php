<?php

namespace Drupal\domain_simple_sitemap;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Form\FormHelper;
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
   * @var \Drupal\simple_sitemap\SitemapGenerator
   */
  protected $sitemapGenerator;

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * @var array
   */
  protected static $allowed_link_settings = [
    'entity' => ['index', 'priority'],
    'custom' => ['priority'],
  ];

  /**
   * DomainSimpleSitemap constructor.
   *
   * @param \Drupal\domain_simple_sitemap\DomainSimpleSitemapGenerator $sitemapGenerator
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\Query\QueryFactory $entityQuery
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Core\Path\PathValidator $pathValidator
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   */
  public function __construct(
    DomainSimpleSitemapGenerator $sitemapGenerator,
    EntityHelper $entityHelper,
    ConfigFactory $configFactory,
    Connection $database,
    QueryFactory $entityQuery,
    EntityTypeManagerInterface $entityTypeManager,
    PathValidator $pathValidator,
    DateFormatter $dateFormatter
  ) {
    parent::__construct($sitemapGenerator, $entityHelper, $configFactory, $database, $entityQuery, $entityTypeManager, $pathValidator, $dateFormatter);
    $this->sitemapGenerator = $sitemapGenerator;
    $this->entityHelper = $entityHelper;
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
