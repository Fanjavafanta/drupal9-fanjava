<?php

namespace Drupal\seo_articles_list\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bloc des derniers articles avec score SEO.
 *
 * @Block(
 *   id = "seo_articles_block",
 *   admin_label = @Translation("Derniers articles SEO"),
 * )
 */
class SeoArticlesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SeoArticlesBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration, 
    $plugin_id, 
    $plugin_definition, 
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container, 
    array $configuration, 
    $plugin_id, 
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'item_count' => 5,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    
    $form['item_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Nombre d\'articles'),
      '#default_value' => $this->configuration['item_count'],
      '#min' => 1,
      '#max' => 20,
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['item_count'] = $form_state->getValue('item_count');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('field_score_seo', 'DESC')
      ->range(0, $this->configuration['item_count'])
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $articles = [];

    foreach ($nodeStorage->loadMultiple($nids) as $node) {
      // Vérifier si le champ existe et a une valeur
      $score = 'N/A';
      $score_class = 'no-score';
      
      if ($node->hasField('field_score_seo') && !$node->get('field_score_seo')->isEmpty()) {
        $score = (int) $node->get('field_score_seo')->value;
        
        // Déterminer la classe CSS pour le score
        if ($score >= 90) {
          $score_class = 'excellent';
        } elseif ($score >= 75) {
          $score_class = 'good';
        } elseif ($score >= 60) {
          $score_class = 'average';
        } elseif ($score >= 40) {
          $score_class = 'poor';
        } else {
          $score_class = 'bad';
        }
      }

      $articles[] = [
        'title' => $node->getTitle(),
        'url' => $node->toUrl()->toString(),
        'score' => $score,
        'score_class' => $score_class,
        'created' => \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'custom', 'd/m/Y'),
      ];
    }

    return [
      '#theme' => 'seo_articles_list',
      '#articles' => $articles,
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
      '#attached' => [
        'library' => [
          'seo_articles_list/seo_articles_list_block',
        ],
      ],
    ];
  }
}
