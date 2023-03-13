<?php

namespace Drupal\wisski_unify\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\State\State;
use Symfony\Component\DependencyInjection\ContainerInterface;

const credentials_key = 'wisski.unify.credentials';
const new_wisskis_key = 'wisski.unify.new_wisskis';

/**
 * Configure example settings for this site.
 */
class UnifyConfigForm extends FormBase {


  protected $count = 0;

  /**
   * The Drupal State API
   * 
   * @var Drupal\Core\State
   */
  protected $state;

  /**
   * The constructor.
   */
  public function __construct(
    State $state,
  ) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
    );
  }

  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::class;
  }


  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = array(
      '#type' => 'item',
      '#attributes' => ['class' => ['action-links']],
    );

    $form['actions']['add'] = array(
      "#type" => "submit",
      '#value' => "+ Add WissKI",
      '#id' => "add_wisski",
      '#attributes' => ['class' => ["button-action", "button-primary", "button--small"]],
    );

    $table = array(
      '#type' => 'table',
      '#empty' => 'No external WissKIs registered.',
      '#header' => [
        'remove' => t('Remove'),
        'url' => t('URL'),
        'user' => t('Username'),
        'password' => t('Password'),
      ],
    );
    // $this->state->delete(credentials_key);

    $data = $this->state->get(credentials_key, []);
    $urls = array();
    foreach ($data as $url => $credentials) {
      if (
        is_array($credentials) &&
        array_key_exists('user', $credentials) &&
        array_key_exists('password', $credentials)
      ) {
        $table[] = $this->buildRow($url, $credentials['user'], $credentials['password']);
        $urls[] = $url;
      } else {
        // clear invalid entries
        unset($data[$url]);
      }
    }

    $userInput = $form_state->getUserInput();
    if(array_key_exists('op', $userInput) && $userInput['op'] == $form['actions']['add']['#value']){
     $table[] = $this->buildRow();
    }

    $form['table'] = $table;

    $form['urls'] = array(
      '#type' => 'hidden',
      '#value' => $urls
    );

    $form['submit'] = array(
      '#value' => "Save",
      '#type' => "submit",
      '#attributes' => array('class' => ["button button--primary js-form-submit form-submit"]),
    );

    return $form;
  }

  public function buildRow($url = null, $user = null, $password = null) {
    // add fields to fill in new credentials 
    $textfield = array('#type' => 'textfield');
    $passwordfield = array('#type' => 'password');
    return array(
      'remove' => array('#type' => 'checkbox', '#attibutes' => ['style' => ["color: coral"]]),
      'url' => $url ? array_merge($textfield, array('#default_value' => $url)) : $textfield,
      'user' => $user ? array_merge($textfield, array('#default_value' => $user)) : $textfield,
      'password' => $password ? array_merge($passwordfield, array('#default_value' => $password)) : $passwordfield,
    );
  }


  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $triggeringElement = $form_state->getTriggeringElement();
    $id = $triggeringElement['#id'];

    if($id == "add_wisski"){
      return;
    }

    $values = $form_state->getValues()['table'];
    $data = $this->state->get(credentials_key);

    foreach ($values as $i => $row) {
      $remove = $row['remove'];
      $url = $row['url'];
      $user = $row['user'];
      $password = $row['password'];

      if ($url && $user && $password && $remove == 0) {
        $data[$url] = array(
          'user' => $user,
          'password' => $password,
        );
      }
      if($remove == "1"){
        unset($data[$url]);
      }
    }
    $this->state->set(credentials_key, $data);
    $input = $form_state->getUserInput();
    $input['table'] = array();
    $form_state->setUserInput($input);
  }
}
