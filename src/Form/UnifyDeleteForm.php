<?php

namespace Drupal\wisski_unify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\wisski_unify\Query\SparqlQuery;
use Drupal\wisski_unify\Query\StaticQueries;

const ALL = 'all';

class UnifyDeleteForm extends FormBase {


  public function getFormId(){
    return self::class;
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    $wisskiInfo = StaticQueries::wisskiInfo()->execute();

    $baseUris = array();
    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    $f = function($adapter){ return $adapter->getEngine()->getDefaultDataGraphUri(); };
    $baseUris = array_values(array_map($f, $adapters));

    // TODO: option to select adapter if necessary
    // assume the first adapter has the default base Uri
    $wisskiSelection = [$baseUris[0] => 'Local'];
    for ($i = 0; $i < count($wisskiInfo); $i++){
      $wisskiSelection[$wisskiInfo[$i]['prefix']] = $wisskiInfo[$i]['name'];
    }
    $wisskiSelection[ALL] = "All";

    $form['select_wisski'] = array(
        '#type' => 'select',
        '#title' => t("Select the WissKi to which you want to remove \"copy_of\" links"),
        '#default_value' => '0',
        '#options' => array_merge(['0' => 'Please select'], $wisskiSelection),
        '#ajax' => array(
          'callback' => 'Drupal\wisski_unify\Form\UnifyDeleteForm::ajaxStores',
          'wrapper' => 'store_div',
          'event' => 'change',
          #'effect' => 'slide',
          ),
        );

    $form['stores'] = array(
        '#type' => 'markup',
        // The prefix/suffix provide the div that we're replacing, named by
        // #ajax['wrapper'] below.
        '#prefix' => '<div id="store_div">',
        '#suffix' => '</div>',
        '#value' => "", 
        );


    if(!empty($form_state->getValue('select_wisski'))){

      $prefix = $form_state->getValue('select_wisski');
      if($prefix == ALL)
        $prefix = null;

      // get number of found links
      $f = function ($carry, $item){ return $carry + $item['cnt']; };
      $info = StaticQueries::numLinks($prefix)->execute();
      $numLinks = array_reduce($info, $f, 0);

      $form['stores']['deletion_info'] = array(
          '#type' => 'markup',
          '#markup' => 'Found ' . $numLinks . " links.<br>",
          );

      $form['stores']['num_links'] = array(
          '#type' => 'value',
          '#value' => $numLinks
          );

      if($numLinks != 0) {
        $form['stores']['delete'] = array(
            '#type' => 'submit',
            '#value' => 'Delete Links',
            );
      }

    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state){
  }

  public static function ajaxStores(array &$form, FormStateInterface $form_state){
    return $form['stores'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    $prefix = $form_state->getValue('select_wisski');

    if($prefix == ALL)
      $prefix = null;

    StaticQueries::deleteLinks($prefix)->update();

    $form_state->setRebuild(True);
    $input = $form_state->getUserInput();
    $form_state->setUserInput($input);
    return $form;
  }
}

