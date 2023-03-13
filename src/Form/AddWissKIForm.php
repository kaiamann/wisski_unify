<?php

namespace Drupal\wisski_unify\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Drupal\wisski_unify\Entity\UnifyListBuilder;
use Drupal\wisski_unify\Utils;

class TestForm extends FormBase {


    /**
     * The Drupal State API
     * 
     * @var Drupal\Core\State
     */
    protected $state;

    /**
     * The constructor.
     */
    public function __construct(State $state) {
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

    public function getFormId() {
        return self::class;
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        // name
        // url 
        // username
        // password

        $form['name'] = array(
            '#type' => 'textfield',
        );

        $form['user'] = array(
            '#type' => 'textfield',
        );

        $form['url'] = array(
            '#type' => 'textfield',
        );

        $form['password'] = array(
            '#type' => 'passwordfield',
        );

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {

        $form_state->setRedirect('wisski.wisski_unify.config/wisski', $query);
    }
}
