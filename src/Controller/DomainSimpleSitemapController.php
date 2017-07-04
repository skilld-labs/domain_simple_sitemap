<?php

namespace Drupal\domain_simple_sitemap\Controller;

use Drupal\domain_simple_sitemap\DomainSimpleSitemap;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\domain\DomainNegotiator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SimplesitemapController.
 *
 * @package Drupal\simple_sitemap\Controller
 */
class DomainSimpleSitemapController extends ControllerBase {

  /**
   * The sitemap generator.
   *
   * @var \Drupal\domain_simple_sitemap\DomainSimpleSitemap
   */
  protected $generator;

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiator
   */
  protected $domainNegotiator;

  /**
   * Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * DomainSimpleSitemapController constructor.
   *
   * @param \Drupal\domain_simple_sitemap\DomainSimpleSitemap $generator
   *   The sitemap generator.
   * @param \Drupal\domain\DomainNegotiator $domainNegotiator
   *   The Domain Negotiator.
   * @param \Drupal\Core\Database\Connection $db
   *   The Database Connection.
   */
  public function __construct(DomainSimpleSitemap $generator, DomainNegotiator $domainNegotiator, Connection $db) {
    $this->generator = $generator;
    $this->domainNegotiator = $domainNegotiator;
    $this->db = $db;
  }

  /**
   * Returns the whole sitemap, requested sitemap chunk or sitemap index file.
   *
   * @param int $chunk_id
   *   Optional ID of the sitemap chunk. If none provided, the first chunk or
   *   the sitemap index is fetched.
   *
   * @throws NotFoundHttpException
   *
   * @return object
   *   Returns an XML response.
   */
  public function getSitemap($chunk_id = NULL) {
    // Get current domain.
    $domain = $this->domainNegotiator->getActiveDomain();
    // Get chunk of the sitemap by domain and display it.
    $chunk_id = $this->db->query('SELECT id FROM {simple_sitemap} WHERE domain_id = :domain_id', [':domain_id' => $domain->id()])->fetchField();
    if (!$chunk_id) {
      $chunk_id = NULL;
    }
    $output = $this->generator->getSitemap($chunk_id);
    if (!$output) {
      throw new NotFoundHttpException();
    }

    $response = new CacheableResponse('', 200);
    $response->setContent($output);
    $cache_metadata = $response->getCacheableMetadata();
    $cache_metadata->addCacheTags(['simple_sitemap:' . $domain->id()]);
    $cache_metadata->addCacheContexts(['url.site']);
    $response->addCacheableDependency($cache_metadata);

    $response->headers->set('Content-type', 'application/xml');

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('domain_simple_sitemap.generator'),
      $container->get('domain.negotiator'),
      $container->get('database')
    );
  }

}
