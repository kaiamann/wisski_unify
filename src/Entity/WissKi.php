<?php

namespace Drupal\wisski_unify\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\RoleInterface;
use Drupal\user\StatusItem;
use Drupal\user\TimeZoneItem;
use Drupal\user\UserInterface;

/**
 * Defines the user entity class.
 *
 * The base table name here is plural, despite Drupal table naming standards,
 * because "user" is a reserved word in many databases.
 *
 * @ContentEntityType(
 *   id = "wisski",
 *   label = @Translation("WissKI"),
 *   label_collection = @Translation("WissKIs"),
 *   label_singular = @Translation("WissKI"),
 *   label_plural = @Translation("WissKIs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count WissKI",
 *     plural = "@count WissKIs",
 *   ),
 *   entity_keys = {
 *      "id" = "id",
 *      "uuid" = "uuid",
 *      "url" = "url",
 *      "username" = "username",
 *      "password" = "password",
 *   },
 *   base_table = "wisski_unify_external_wisski"
 * )
 */
class WissKI extends ContentEntityBase {

//  *   handlers = {
//  *     "storage" = "Drupal\user\UserStorage",
//  *     "storage_schema" = "Drupal\user\UserStorageSchema",
//  *     "access" = "Drupal\user\UserAccessControlHandler",
//  *     "list_builder" = "Drupal\user\UserListBuilder",
//  *     "views_data" = "Drupal\user\UserViewsData",
//  *     "route_provider" = {
//  *       "html" = "Drupal\user\Entity\UserRouteProvider",
//  *     },
//  *     "form" = {
//  *       "default" = "Drupal\user\ProfileForm",
//  *       "cancel" = "Drupal\user\Form\UserCancelForm",
//  *       "register" = "Drupal\user\RegisterForm"
//  *     },
//  *     "translation" = "Drupal\user\ProfileTranslationHandler"
//  *   },
//  *   admin_permission = "administer users",
//  *   base_table = "users",
//  *   data_table = "users_field_data",
//  *   translatable = TRUE,
//  *   entity_keys = {
//  *     "id" = "uid",
//  *     "langcode" = "langcode",
//  *     "uuid" = "uuid"
//  *   },
//  *   links = {
//  *     "canonical" = "/user/{user}",
//  *     "edit-form" = "/user/{user}/edit",
//  *     "cancel-form" = "/user/{user}/cancel",
//  *     "collection" = "/admin/people",
//  *   },
//  *   field_ui_base_route = "entity.user.admin_form",
//  *   common_reference_target = TRUE

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getPassword() {
    return $this->get('pass')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPassword($password) {
    $this->get('pass')->value = $password;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->get('url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->get('url')->value = $url;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername() {
    return $this->get('username')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setUsername($username) {
    $this->set('username', $username);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('WissKI ID'))
      ->setDescription(t('The WissKI ID.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('WissKI UUID'))
      ->setDescription(t('The UUID of the WissKI entity.'))
      ->setReadOnly(TRUE);

    $fields['url'] = BaseFieldDefinition::create('link')
        ->setLabel(t("URL"))
        ->setDescription(t('The URL of the external WissKI.'))
        ->setRequired(TRUE);

    $fields['username'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Username'))
      ->setDescription(t('The name the external WissKI user.'))
      ->setRequired(TRUE);
    $fields['username']->getItemDefinition()->setClass('\Drupal\user\UserNameItem');


    $fields['pass'] = BaseFieldDefinition::create('password')
      ->setLabel(t('Password'))
      ->setDescription(t('The password of this user (hashed).'))
      ->addConstraint('ProtectedUserField');

    return $fields;
  }
}
