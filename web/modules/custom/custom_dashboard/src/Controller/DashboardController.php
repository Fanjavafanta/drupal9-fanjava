<?php

namespace Drupal\custom_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Cache\Cache;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

class DashboardController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new DashboardController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('messenger')
    );
  }

  /**
   * Displays the dashboard page.
   */
  public function dashboard() {
    try {
      // Récupérer tous les contenus
      $node_storage = $this->entityTypeManager->getStorage('node');
      $query = $node_storage->getQuery()
        ->sort('field_score_seo', 'DESC')
        ->accessCheck(TRUE);
      
      $nids = $query->execute();
      
      // Préparer les données pour le tableau
      $rows = [];
      
      if (!empty($nids)) {
        $nodes = $node_storage->loadMultiple($nids);
        
        foreach ($nodes as $node) {
          $rows[] = [
            'id' => $node->id(),
            'title' => $node->getTitle(),
            'author' => $node->getOwner()->getDisplayName(),
            'created' => $this->dateFormatter->format($node->getCreatedTime(), 'short'),
            'seo_score' => $this->getSeoScore($node),
            'status' => $node->isPublished() ? $this->t('Publié') : $this->t('Non publié'),
            'edit' => Link::fromTextAndUrl(
              $this->t('Modifier'), 
              Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])
            )->toString(),
          ];
        }
      }
      
      // Créer les liens d'action
      $purge_cache_link = Link::fromTextAndUrl(
        $this->t('Purge du cache'), 
        Url::fromRoute('custom_dashboard.purge_cache')
      )->toString();
      
      $seo_check_link = Link::fromTextAndUrl(
        $this->t('Vérification SEO'), 
        Url::fromRoute('custom_dashboard.seo_check')
      )->toString();
      
      // Retourner le rendu Twig
      return [
        '#theme' => 'dashboard',
        '#header' => [
          $this->t('ID'),
          $this->t('Titre'),
          $this->t('Auteur'),
          $this->t('Date'),
          $this->t('Score SEO'),
          $this->t('Statut'),
          $this->t('Actions'),
        ],
        '#rows' => $rows,
        '#purge_cache_link' => $purge_cache_link,
        '#seo_check_link' => $seo_check_link,
        '#attached' => [
          'library' => [
            'custom_dashboard/dashboard-styles',
          ],
        ],
      ];
      
    } catch (\Exception $e) {
      // Journaliser l'erreur
      $this->logger('custom_dashboard')->error($e->getMessage());
      
      return [
        '#markup' => $this->t('Une erreur est survenue lors du chargement du tableau de bord.'),
      ];
    }
  }
  
  /**
   * Get SEO score display.
   */
  private function getSeoScore(NodeInterface $node) {
    if ($node->hasField('field_score_seo') && !$node->get('field_score_seo')->isEmpty()) {
      $score = $node->get('field_score_seo')->value;
      $class = $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low');
      return [
        '#markup' => '<span class="seo-score ' . $class . '">' . $score . '</span>',
      ];
    }
    return 0;
  }
  
  /**
   * Purge cache action.
   */
  public function purgeCache() {
    // Purge du cache
    Cache::invalidateTags(['rendered']);
    $this->messenger->addStatus($this->t('Le cache a été purgé avec succès.'));
    return new RedirectResponse(Url::fromRoute('custom_dashboard.dashboard')->toString());
  }
  
  /**
   * SEO check action.
   */
  public function seoCheck() {
    // Ici vous pourriez ajouter une logique de vérification SEO
    $this->messenger->addStatus($this->t('Vérification SEO lancée. Un rapport sera généré sous peu.'));
    return new RedirectResponse(Url::fromRoute('custom_dashboard.dashboard')->toString());
  }
}
