<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form that list user subscriptions and related settings.
 */
class UserSubscriptionsForm extends FormBase {

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_subscriptions_anonymous_user_subscriptions_form';
  }

  /**
   * Creates the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\user\UserInterface|null $user
   *   The user for whom we are rendering the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $form['flag_list'] = $this->buildFlagList($user);

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

  /**
   * Returns the title for the current form route.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account for which the form is being rendered.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function getTitle(UserInterface $user): TranslatableMarkup {
    if ($this->currentUser->id() === $user->id()) {
      return $this->t('My subscriptions');
    }

    return $this->t('@name subscriptions', [
      '@name' => $user->getDisplayName(),
    ]);
  }

  /**
   * Builds the list of flagged entities for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return array
   *   A render array.
   */
  protected function buildFlagList(UserInterface $user): array {
    $flag_storage = $this->entityTypeManager->getStorage('flagging');
    // @todo Add paging.
    $results = $flag_storage->getQuery()
      ->accessCheck()
      ->condition('uid', $user->id())
      ->condition('flag_id', 'subscribe_', 'STARTS_WITH')
      ->sort('entity_type')
      // Sorting by flag ID is equivalent to sorting by creation time, as IDs
      // are incremental.
      ->sort('id', 'DESC')
      ->execute();

    $build = [
      '#cache' => [
        '#cache' => [
          'contexts' => $flag_storage->getEntityType()->getListCacheContexts(),
          'tags' => $flag_storage->getEntityType()->getListCacheTags(),
        ],
      ],
    ];

    if (empty($results)) {
      $build['no_results'] = [
        '#plain_text' => $this->t('No subscriptions found.'),
      ];

      return $build;
    }

    $cacheability = CacheableMetadata::createFromRenderArray($build);
    $rows = [];
    /** @var \Drupal\flag\FlaggingInterface[] $flaggings */
    $flaggings = $flag_storage->loadMultiple($results);
    foreach ($flaggings as $flagging) {
      $entity = $flagging->getFlaggable();
      if (!$entity) {
        continue;
      }
      $flag = $flagging->getFlag();

      $entity_access = $entity->access('view', $user, TRUE);
      $cacheability->addCacheableDependency($entity_access);
      // We don't render the row if the user has no view access to this entity
      // (e.g. it has been unpublished).
      // Note that the flag link already handles it own cache information and
      // won't render if the user has no access to it.
      if (!$entity_access->isAllowed()) {
        continue;
      }

      $rows[] = [
        'type' => $entity->getEntityType()->getLabel(),
        'label' => ['data' => $entity->toLink()->toRenderable()],
        'flag_link' => [
          'data' => $flag->getLinkTypePlugin()->getAsFlagLink($flag, $entity),
        ],
      ];
    }

    $build = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['user-subscriptions'],
      ],
      '#header' => [
        $this->t('Type'),
        $this->t('Title'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
    ];
    $cacheability->applyTo($build);

    return $build;
  }

}
