<?php
/**
 * @file
 *
 */

namespace Drupal\wisski_unify\Form;

use Drupal\wisski_salz\Entity\Adapter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_merge\Merger;
use Drupal\wisski_salz\AdapterHelper;

use Drupal\wisski_unify\Utils;
use Drupal\wisski_unify\Query\SparqlQuery;
use Drupal\wisski_unify\Query\UnifyOntology;

/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the Duplication detection
 * @author Kai Amann
 */
class WisskiUnifyForm extends FormBase {


  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class
   */
  public function getFormId() {
    return 'WisskiUnifyForm';
  }

  /**
   * {@inheritdoc}.
   * 
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    #dpm($form_state, "fs?");
    # $form = array();

    $pbs = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();

    $groups = array();
    $group_names = array();

    foreach ($pbs as $pb) {
      $groups = array_merge($groups, $pb->getAllGroups());
    }

    foreach ($groups as $id => $group) {
      $group_names[$group->id()] = $group->label();
    }

    $selected_group = "";

    $selected_group = !empty($form_state->getValue('select_group')) ? $form_state->getValue('select_group') : "0";

    // generate a select field
    $form['select_group'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select the group which you want to merge data.'),
      '#default_value' => $selected_group,
      '#options' => array_merge(array("0" => 'Please select.'), $group_names),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_core\Form\WisskiOntologyForm::ajaxStores',
        'wrapper' => 'select_store_div',
        'event' => 'change',
        #'effect' => 'slide',
      ),
    );

    // ajax wrapper
    $form['stores'] = array(
      '#type' => 'markup',
      // The prefix/suffix provide the div that we're replacing, named by
      // #ajax['wrapper'] below.
      '#prefix' => '<div id="select_store_div">',
      '#suffix' => '</div>',
      '#value' => "",
    );

    // if there is already a bundle selected
   if (!empty($form_state->getValue('select_group'))) {

      $paths = array();

      foreach ($pbs as $pb) {
        $paths = array_merge($paths, $pb->getAllPathsForGroupId($selected_group, TRUE));
      }

      foreach ($paths as $id => $path) {
        $path_names[$path->id()] = $path->label();
      }

      $selected_path = "";

      $selected_path = !empty($form_state->getValue('select_path')) ? $form_state->getValue('select_path') : "0";

      // generate a select field
      $form['stores']['select_path'] = array(
        '#type' => 'select',
        '#title' => $this->t('Select the Path which you want to merge data for duplicates.'),
        '#default_value' => $selected_path,
        '#options' => array_merge(array("0" => 'Please select.'), $path_names),
        '#ajax' => array(
          'callback' => 'Drupal\wisski_core\Form\WisskiOntologyForm::ajaxStores',
          'wrapper' => 'select_store_div',
          'event' => 'change',
          #'effect' => 'slide',
        ),
      );

      if (!empty($form_state->getValue('select_path'))) {
        $path = \Drupal::entityTypeManager()->getStorage('wisski_path')->load($selected_path);

        $conflict_values = array();
        $conflicts = array();
        $pathEntities = array();

        $i = 0;
        foreach ($pbs as $pb) {
          $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($pb->getAdapterId());

          $engine = $adapter->getEngine();

          if (!($engine instanceof Sparql11EngineWithPB))
            continue;

          $triples = $engine->generateTriplesForPath($pb, $path);
          # dpm($triples, "triples");
          $variables = $this->getTripleVariables($triples);
          # dpm($variables, "variables");

          # ignore Uris that are already marked as duplicates
          $minusTriples = $triples;
          $minusTriples = str_replace('?g','?gg', $minusTriples);
          $minusTriples = str_replace('?x', '?xx', $minusTriples);
          $minusTriples .= "GRAPH ?gg { { { ?xx0 <" . UnifyOntology::get('copy_of') . "> " . $variables[0] . " . } UNION {" . $variables[0] . " <" . UnifyOntology::get('copy_of') . "> ?xx0 . } } . } .";

          $subquery = "SELECT DISTINCT ?out "; 
          $subquery .= implode(' ',$variables); 
          $subquery .= " WHERE { { " . $triples . "} MINUS { " . $minusTriples . " } }";

          # this assumes the first variable is always the URI of the respective triple
          $concatVars = "(GROUP_CONCAT( ". $variables[0] . ";separator=', ') as ?uris) ";
          for($j=1; $j<count($variables); $j++){
            $concatVars .= "(GROUP_CONCAT( " . $variables[$j] . ";separator=', ') as ?v" . $j  . " ) ";
          }
          $query = "SELECT ?out " . $concatVars . "(COUNT(?out) as ?anzahl) WHERE { " . $subquery . " } GROUP BY ?out HAVING(?anzahl > 1)";

          // dpm($query, "query?");

          $result = $engine->directQuery($query);
          # dpm($result);

          if (!empty($result) && count($result) > 0) {

            $form['stores']['table'] = array(
              '#type' => 'table',
              '#header' => array(
                $this->t("Conflicting Property: " . $path->getName()),
                $this->t("Candidates"),
              ),
            );

            foreach ($result as $res) {

              $uris = explode(', ',$res->uris->getValue());

              $j=1;

              $localPathEntities = array(); 
              while(property_exists($res, $v = "v". $j)){
                $localPathEntities[] = array_map([$engine, 'escapeSparqlLiteral'], explode(', ', $res->$v->getValue()));
                $j++;
              }
              $pathEntities[] = $localPathEntities;


              $outval = $res->out->getValue();
              $outval = $engine->escapeSparqlLiteral($outval);

              $conflict_values[] = $outval;
              $conflicts[] = array_map([$engine, 'escapeSparqlLiteral'], $uris);

              $form['stores']['table'][$i]['name'] = array(
                '#type' => 'markup',
                '#title' => $this->t('Name'),
                '#markup' => $this->t($outval),
              );

              $form['stores']['table'][$i]['candidates'] = array(
                '#type' => 'table',
                '#header' => array(
                  $this->t("Radios"),
                  $this->t("Rendered Entity"),
                ),
              );

              for ($j = 0 ; $j < count($uris) ; $j++){
                $drupalId = AdapterHelper::getDrupalIdForUri($uris[$j]);

                $entity_type = 'wisski_individual';
                $entity_id = $drupalId; 
                $view_mode = 'full';         

                $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
                # dpm($entity, "?entity");
                $view_builder = \Drupal::entityTypeManager()->getViewBuilder('wisski_individual');
                $pre_render = $view_builder->view($entity, $view_mode);
                #$render_output = render($pre_render);

                $form['stores']['table'][$i]['candidates'][$j]['select'] = array(
                  '#type' => 'checkbox',
                  # for linked radio button setup
                  #'#parents' => array($i. '-candidates'),
                  #'#default_value' => 'off',
                  #'#return_value' => $j,
                );

                $form['stores']['table'][$i]['candidates'][$j]['render'] = $pre_render;

              }
              $i++;
            }
          }
        }

        # store values for later use
        $form['stores']['conflict_properties'] = array(
          #'value' instead of 'hidden' type to avoid "Warning: Array to string conversion"
          '#type' => 'value',
          '#value' => $conflict_values,
        );

        $form['stores']['conflict_uris'] = array(
          #'value' instead of 'hidden' type to avoid "Warning: Array to string conversion"
          '#type' => 'value',
          '#value' => $conflicts,
        );

        $form['stores']['path_entities'] = array(
          '#type' => 'value',
          '#value' => $pathEntities,
        );

        $form['stores']['apply'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Unify'),
          //        '#submit' => array('::unify') 
        );
      }
    }
    return $form;
  }

  public static function ajaxStores(array $form, FormStateInterface $form_state) {
    #   drupal_set_message('hello');
    #   dpm("yay!");
    return $form['stores'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    #   dpm('hello1');
  }

  function getSelection(FormStateInterface $formState){
    $tableValues = $formState->getValues('values')['table'];
    $uris = $formState->getValue('conflict_uris');
    $pathEntities = $formState->getValue('path_entities');

    $selectedUris = array();
    $selectedPathEntities = array();

    for($i=0; $i<count($tableValues); $i++){
      $indices[$i] = array();
      for($j=0; $j<count($tableValues[$i]['candidates']); $j++){
        if($tableValues[$i]['candidates'][$j]['select'] != 0){
          $selectedUris[$i][] = $uris[$i][$j];

          // iterate over the path entities
          for($k=0; $k<count($pathEntities[0]); $k++){
            $selectedPathEntities[$i][$k][] = $pathEntities[$i][$k][$j];
          }
        }
      }
    }

    return array(
      'uris' => $selectedUris,
      'path_entities' => $selectedPathEntities
    );
  }

  public function buildInsertTriples(array $selectedUris){
    $triples = array();
    for($k=0; $k<count($selectedUris); $k++){
      for($i=0; $i<count($selectedUris[$k]); $i++){
        for($j=$i+1; $j<count($selectedUris[$k]); $j++){
          $triples[] = $this->buildInsertTriple($selectedUris[$k][$i], $selectedUris[$k][$j]);
        }
      }
    }
    return $triples;
  }

  function buildPathEntityTriples(array $selectedPathEntities){
    $triples = array();
    for($i=0; $i<count($selectedPathEntities); $i++){
      for($j=0; $j<count($selectedPathEntities[$i]); $j++ ){
	      for($k=0; $k<count($selectedPathEntities[$i][$j]); $k++){
          for($p=$k+1; $p<count($selectedPathEntities[$i][$j]); $p++){
            $triples[] = $this->buildInsertTriple($selectedPathEntities[$i][$j][$k], $selectedPathEntities[$i][$j][$p]);
          }
        }
      }
    }
    return $triples;
  }

  public function buildInsertTriple($uri1, $uri2){
    // TODO: make new local copy_of property
    return "<" . $uri1 . "> unify:copy_of <" . $uri2 . "> . \n";
  }

  public function buildInsertQuery($triples){
    $query = "INSERT DATA {\n GRAPH unify_data: {\n";
    for($i=0; $i<count($triples); $i++){
      $query .= $triples[$i];
    }
    $query .= "}\n }";
    return $query;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    #    dpm('hello');
    #i-candidates"

    $uris = $form_state->getValue('conflict_uris');
    $properties = $form_state->getValue('conflict_properties');

    #dpm($conflicts, "conflicts");
    #dpm($conflict_values, "conflict_values");
    #dpm($form_state, "fs?");
    #dpm($form_state->getValues('values'), 'values?');

    $selection = $this->getSelection($form_state);
    $selectedUris = $selection['uris'];
    $selectedPathEntities = $selection['path_entities'];

   
    //dpm($selection);

    $pbs = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    foreach($pbs as $pb){
      $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($pb->getAdapterId());
      $engine = $adapter->getEngine();
      
      $pathEntityTriples = $this->buildPathEntityTriples($selectedPathEntities);
      //dpm($pathEntityTriples);

      $uriTriples = $this->buildInsertTriples($selectedUris, $selectedPathEntities);
      $triples = array_merge($uriTriples, $pathEntityTriples);

      if(count($triples) <= 0 || empty($triples))
        continue;

      $query = $this->buildInsertQuery($triples);
      $sparqlQuery = new SparqlQuery($query, [], UnifyOntology::getNamespaces());
      $sparqlQuery->update();
    }

    $form_state->setRebuild(TRUE);
    $form_state->setValue('table', array());
    $input = $form_state->getUserInput();
    $input['table'] = array();
    $form_state->setUserInput($input);
    #    $options = 
    #    dpm("submit called!");

    return;

  }

  /*
   * Extracts the uniqe variables used in triples
   *
   * @param String $triples the triples string
   * @return array the uniqe variables
   */
  function getTripleVariables($triples){
    $regex = "/\?x\d+/";
    preg_match_all($regex, $triples,  $matches);
    return array_unique($matches[0]);
  }
}
