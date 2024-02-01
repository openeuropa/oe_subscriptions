<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form that list user subscriptions and related settings.
 */
class UserSubscriptionsForm extends FormBase {

  /**
   * The user account for which the form is being rendered.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $account;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FlagServiceInterface $flagService,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity_type.manager'),
      $container->get('flag'),
      $container->get('current_user'),
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_subscriptions_user_subscriptions_form';
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
    $this->currentUser = $user;

    $form['preferred_language'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Preferred language'),
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#description' => $this->t("The primary language of this account's profile information."),
      '#default_value' => $this->currentUser->getPreferredLangcode(),
    ];

    $form['flag_list'] = $this->buildFlagList();

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
    // @see \Drupal\user\AccountForm::buildEntity()
    $preferred_language = $form_state->getValue('preferred_language');
    $this->currentUser->set('preferred_langcode', $preferred_language === '' ? NULL : $preferred_language);
    $this->currentUser->save();

    $this->messenger()->addStatus($this->t('Your preferences have been saved.'));
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
      return $this->t('Manage your subscriptions');
    }

    return $this->t('Manage @name subscriptions', [
      '@name' => $user->getDisplayName(),
    ]);
  }

  /**
   * Builds the list of flagged entities for the current account.
   *
   * @return array
   *   A render array.
   */
  protected function buildFlagList(): array {
    $flag_storage = $this->entityTypeManager->getStorage('flagging');
    // @todo Add paging.
    $results = $flag_storage->getQuery()
      ->accessCheck()
      ->condition('uid', $this->currentUser->id())
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
      $build['no_results'] = ['#theme' => 'oe_subscriptions_no_subscriptions'];

      return $build;
    }

    $cacheability = CacheableMetadata::createFromRenderArray($build);
    /** @var \Drupal\flag\FlaggingInterface[] $flaggings */
    $flaggings = $flag_storage->loadMultiple($results);
    foreach ($flaggings as $flagging) {
      $entity = $flagging->getFlaggable();
      if (!$entity) {
        continue;
      }
      $flag = $flagging->getFlag();

      $entity_access = $entity->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($entity_access);
      // We don't render the row if the user has no view access to this entity
      // (e.g. it has been unpublished).
      // Note that the flag link already handles it own cache information and
      // won't render if the user has no access to it.
      if (!$entity_access->isAllowed()) {
        continue;
      }

      $build[$flagging->id()] = [
        '#flag_id' => $flag->id(),
        '#entity_type_id' => $entity->getEntityTypeId(),
        '#entity_id' => $entity->id(),
        'type' => [
          '#plain_text' => $entity->getEntityType()->getLabel(),
        ],
        'label' => $entity->toLink()->toRenderable(),
        'unflag' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#submit' => ['::unflagSubmit'],
        ],
      ];
    }

    $build += [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['user-subscriptions'],
      ],
      '#header' => [
        $this->t('Type'),
        $this->t('Title'),
        $this->t('Operations'),
      ],
    ];
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Submit handler to remove a single flag.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function unflagSubmit(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $row = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    [
      '#flag_id' => $flag_id,
      '#entity_type_id' => $entity_type_id,
      '#entity_id' => $entity_id,
    ] = $row;

    $flag = $this->entityTypeManager->getStorage('flag')->load($flag_id);
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);

    // Unsubscribe only if the entity is found. It could have been deleted
    // while the user has the page open.
    if ($entity) {
      $this->flagService->unflag($flag, $entity, $this->currentUser);
    }

    // We always show the correct message even if the entity was not found.
    $this->messenger()->addStatus($this->t('You have successfully unsubscribed from @label.', [
      '@label' => $entity->label(),
    ]));
  }

}
