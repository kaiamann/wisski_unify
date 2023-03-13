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

use Drupal\wisski_merge\Merger;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\Component\Serialization\Json;

# import these for namespace resolution
use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;

use Drupal\wisski_unify\Render\MarkupGenerator;
use Drupal\wisski_unify\Utils;
use Drupal\wisski_unify\Config;
use Drupal\wisski_unify\Queries;
use Drupal\wisski_unify\QueryResultFormat;

use DOMDocument;
use DOMElement;
use DOMText;
use Drupal\wisski_unify\Plugin\rest\resource\UnifyResource;
use Drupal\wisski_unify\Query\StaticQueries;
use Drupal\wisski_unify\Render\MarkupGenerator as RenderMarkupGenerator;
use EasyRdf\RdfNamespace;

/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the Duplication detection
 * @author Kai Amann
 */
class WissKiTestForm extends FormBase {

  /**
   * {@inheritdoc}.
   * The Id of every WissKI form is the name of the form class
   */
  public function getFormId() {
    return 'WisskiUnifyForm';
  }



  static function flatten($array) {
    $return = array();
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $isNumeric = true;
        foreach ($value as $k => $v) {
          if (!is_numeric($k)) {
            $isNumeric = false;
            break;
          }
        }
        if (!$isNumeric) {
          $return = array_merge($return, self::flatten($value));
        } else {
          $return = array_merge($return, [$key => self::flatten($value)]);
        }
      } else {
        $return[$key] = $value;
      }
    }

    return $return;
  }

  private static function getBundleLabel($bundleId) {
    $bundle = \Drupal::entityTypeManager()->getStorage('wisski_bundle')->load($bundleId);
    if ($bundle)
      return $bundle->label();
    return null;
  }

  private function test3() {
    $a = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();
    dpm($a);
    //load($bundleId);
    //dpm(self::getBundleLabel('b7f2a47361f214a1ed1474f65c9229fd'));

    $nuri = 'http://objekte-im-netz.fau.de/orangerie/content/5f885faaabdc8';
    $euri = 'http://objekte-im-netz.fau.de/orangerie/content/5f897ad05fae8';


    $form['table'] = array(
      '#type' => 'table',
      '#header' => array(
        $this->t("uri"),
        $this->t("value"),
        $this->t("Entity")
      ),

    );
    return $form;

    $class = '<http://erlangen-crm.org/170309/E36_Visual_Item>';
    $class = '<http://objekte-im-netz.fau.de/ontology/common/S86_Organisation>';
    $class = '<http://objekte-im-netz.fau.de/ontology/common/S1_Collection_Object>';

    $result = Queries::executeQuery(Queries::appellationInfo($class));

    $cnt = 0;
    $i = 0;
    $uris = array();
    foreach ($result as $data) {
      $value = $data['leaf'];
      $oclass = str_replace(['<', '>'], '', $data['oclass']);
      $uri = str_replace(['<', '>'], '', $data['o']);

      if (in_array($uri, $uris))
        continue;
      $uris[] = $uri;


      $id = AdapterHelper::getDrupalIdForUri($uri);
      //WisskiCacheHelper::flushCallingBundle($id);

      $entity = Utils::getEntityForUri($uri);
      $html = Utils::renderEntity($entity);


      if ($entity && !$html) {
        $bundle = $entity->bundle();
        $bundleLabel = self::getBundleLabel($bundle);
        $html = $bundleLabel . " " . $bundle;
        $cnt++;
      }
      if (!$entity) {
        $html = "no entity";
      }

      $form['table'][$i]['uri'] = array(
        '#type' => 'markup',
        '#markup' => $uri
      );
      $form['table'][$i]['value'] = array(
        '#type' => 'markup',
        '#markup' => $oclass
      );

      $form['table'][$i]['html'] = array(
        '#type' => 'markup',
        '#markup' => $html,
      );
      //$form['table'][$i]['entity'] = $html;
      $i++;
    }
    dpm(count($uris));
    dpm($cnt);

    return $form;
  }

  private function test2() {

    //$idl = AdapterHelper::getDrupalIdForUri('http://objekte-im-netz.fau.de/orangerie/content/5d5ba2489b827');
    //$id = AdapterHelper::getDrupalIdForUri('http://testt-data.com/person10');
    //$callingBundle = WisskiCacheHelper::getCallingBundle($id);
    //dpm($callingBundle);
    //dpm($id);

    /*$callingBundle = WisskiCacheHelper::getCallingBundle($id);
      if($callingBundle){
      $bundleName = \Drupal::entityTypeManager()->getStorage('wisski_bundle')->load($callingBundle)->label();
      dpm($bundleName, "callingBundle");
      }
      else
      dpm($callingBundle, "no CallingBundle");
     */

    //$entityl = \Drupal::service('entity_type.manager')->getStorage('wisski_individual')->load($idl);
    $entity1 = Utils::getEntityForUri("http://objekte-im-netz.fau.de/orangerie/content/5d5ba2489b827");


    //$entity = \Drupal::service('entity_type.manager')->getStorage('wisski_individual')->load($id);
    $entity = Utils::getEntityForUri("http://testt-data.com/person11");

    //dpm($entityl->bundle(), "L");
    //dpm($entity->bundle(), "P");

    //$preRender = \Drupal::service('entity_type.manager')->getViewBuilder('wisski_individual')->view($entity3, 'full');

    //WisskiCacheHelper::flushCallingBundle($id);
    dpm($entity);
    // $entity = Utils::getEntityForUri('http://objekte-im-netz.fau.de/orangerie/content/5d70e4f87fa7a');
    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('wisski_individual');
    $preRender = $viewBuilder->view($entity, 'full');
    $renderedHtml = render($preRender);

    //$preRender = Utils::preRenderEntity($entity);
    //dpm($preRender);
    //dpm($renderedHtml);


    $form['test'] = $viewBuilder->view($entity, 'full');

    return $form;
  }

  private function test() {

    $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    dpm($adapters);
    dpm($adapters['default']->getEngine()->getConfiguration());


    $form['first'] = array(
      '#type' => 'markup',
      '#markup' => "Hi",
    );

    return $form;
  }


  /**
   * {@inheritdoc}.
   * 
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $entities = UnifyResource::getEntitiesForPath("name_person");
    dpm($entities);

    // attach library
    $form['#attached']['library'][] = 'wisski_unify/unify-theme';

    // get available WissKIs
    $wisskiInfo = StaticQueries::wisskiInfo()->execute();

    // format for display
    $wisskiSelection = [\Drupal::request()->getHost() => 'Local'];
    for ($i = 0; $i < count($wisskiInfo); $i++) {
      $wisskiSelection[$wisskiInfo[$i]['url']] = $wisskiInfo[$i]['name'];
    }

    $form['select_wisski'] = array(
      '#type' => 'select',
      '#title' => 'Select the Wisski to check for duplicates',
      '#default_value' => '0',
      '#options' => array_merge(['0' => 'Please select'], $wisskiSelection),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_unify\Form\WisskiTestForm::ajaxStores',
        'wrapper' => 'stores_div',
        'event' => 'change',
        //'effect' => 'slide',
      ),

    );

    // get ECRM versions and abort if there's more than one present
    $ecrmVersions = StaticQueries::ecrmVersions()->execute()['s'];
    if (count($ecrmVersions) > 1) {
      $versionsString = implode(', ', $ecrmVersions);
      \Drupal::messenger()->addError("Found multiple versions of ECRM: $versionsString, which is currently not supported!");
      // TODO: comment this in: return $form;
    }

    // get ECRM Version
    $ecrmVersion = current($ecrmVersions);
    // add the ecrm Prefix to every query
    RdfNamespace::set('ecrm', $ecrmVersion);

    // get all ECRM Classes
    $ecrmClasses = StaticQueries::ecrmClasses()->execute()['class'];

    // function for getting the base element name
    $getRawName = function ($uri) use ($ecrmVersion) {
      return str_replace($ecrmVersion, '', $uri);
    };
    // function for getting the display element name
    $getDisplayName = function ($rawClassName) {
      return str_replace('_', ' ', $rawClassName);
    };
    // format the class names
    $rawClassNames = array_map($getRawName, $ecrmClasses);
    $displayClassNames = array_map($getDisplayName, $rawClassNames);
    //combine them and sort
    $classSelection = array_combine($rawClassNames, $displayClassNames);
    $classSelection = Utils::numsort($classSelection);

    // Take the "Please select" option if no class is selected
    $selectedClass = !empty($form_state->getValue('select_group')) ? $form_state->getValue('select_group') : "0";

    // ajax stores
    $form['stores'] = array(
      '#type' => 'markup',
      // The prefix/suffix provide the div that we're replacing, named by
      // #ajax['wrapper'] below.
      '#prefix' => '<div id="stores_div">',
      '#suffix' => '</div>',
      '#value' => "",
    );


    // if theres no host selected
    if (empty($form_state->getValue('select_wisski'))) {
      return $form;
    }

    // CRM Class selection
    $form['stores']['select_group'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select the group which you want to merge data.'),
      '#default_value' => $selectedClass,
      '#options' => array_merge(array("0" => 'Please select.'), $classSelection),
      '#ajax' => array(
        'callback' => 'Drupal\wisski_unify\Form\WisskiTestForm::ajaxStores',
        'wrapper' => 'stores_div',
        'event' => 'change',
        //'effect' => 'slide',
      ),
    );



    // if there is no ECRM class selected
    if (empty($form_state->getValue('select_group'))) {
      return $form;
    }

    $class = $form_state->getValue('select_group');

    $appellationData = StaticQueries::appellationInfo($class)->execute();

    $value_map = array();
    $values = array();
    // the following for loop is effectively a group by URI
    // TODO: transform to GROUP BY in the query
    foreach ($appellationData as $data) {
      $value = $data['leaf'];
      $uri = $data['o'];
      $value_map[$value][] = $uri;
      $values[] = $value;
    }

    // escape the strings for use in query
    $values = array_map('addslashes', $values);

    $data = [
      'values' => $values,
      'class' => $class
    ];

    // for fast testing
    // TODO: remove this
    // $values = ['Dürer, Albrecht']
    // $values = ["Garofalo, Benventuo"];

    $host = $form_state->getValue('select_wisski');
    $credentials = Utils::getCredentials($host);

    // build authorization headers
    $headers = array(
      'Application' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode(implode(':', $credentials))
    );

    // $route = Utils::getRoutePath('wisski.wisski_unify.controller.external');
    // dpm($route);
    $route = '/wisski/unify/rest?_format=json';
    $url = 'https://' . $host . $route;
    // dpm($url);


    $response = Utils::post($url, $data, $headers);
    $code = $response['code'];
    if ($code !== 200) {
      $message = $response['body']['message'];
      switch($code){
        case 403: $message .= " Make sure the user '{$credentials[0]}' has this permission on '$host'."; break;
        case 404: $message .= " Make sure '$host' has the 'Unify Resource' acitvated."; break;
      }
      \Drupal::messenger()->addError($message);
      return $form;

    }

    // dpm($decodedResponse, "resp");

    // prepare data for rendering
    $conflictUris = array();
    $conflictValues = array();
    $conflictRenderData = array();

    // get all copy_of pairs
    $res = StaticQueries::linkedEntities()->execute();
    $linkedEntities = array();
    foreach ($res as $uris) {
      $linkedEntities[] = [
        'local' => $uris['s'],
        'external' => $uris['o']
      ];
    }
    //dpm($linkedEntities);

    // build conflicts
    $nonExistentEntities = [];
    // iterate over external return values
    foreach ($response['body'] as $value => $externalEntities) {
      // iterate over the external Entiies for this return value
      foreach ($externalEntities as $externalUri => $externalHTML) {
        // 
        foreach ($value_map[$value] as $localUri) {
          $id = AdapterHelper::getDrupalIdForUri($localUri);
          WisskiCacheHelper::flushCallingBundle($id);
          $localEntity = Utils::getEntityForUri($localUri);

          // if the local Entity does not exist, skip for now
          // this happens when there's no bundle for this URI
          if (!$localEntity) {
            $nonExistentEntities[$localUri] = $value;
            continue;
          }

          $localHTML = Utils::renderEntity($localEntity);

          $conflict = array(
            'local' => $localUri,
            'external' => $externalUri
          );

          // filter already linked duplicates
          if (in_array($conflict, $linkedEntities)) {
            continue;
          }

          // extract Data from HTML
          $localData = Utils::HTMLToArray($localHTML);
          $externalData = Utils::HTMLToArray($externalHTML);

          // find matching/conflicting Data
          $m1 = Utils::compare($localData, $externalData);
          $m2 = Utils::compare($externalData, $localData);

          // if the conflict already exists only add values for display
          if (in_array($conflict, $conflictUris)) {
            $index = array_search($conflict, $conflictUris);
            $conflictValues[$index][] = $value;
          } else {
            // since we cannot use the usual pre-render data
            // render the entities directly here
            $conflictValues[] = array($value);
            $conflictUris[] = $conflict;
            $conflictRenderData[] = array(
              'local' => MarkupGenerator::generateHTML($localData, $m1),
              'external' => MarkupGenerator::generateHTML($externalData, $m2),
            );
          }
        }
      }
    }
    dpm($nonExistentEntities, "wtf");
    $form['stores']['hits'] = array(
      '#type' => 'markup',
      '#markup' => count($conflictUris) . " Duplicates found"
    );

    $form['stores']['table'] = array(
      '#type' => 'table',
      '#header' => array(
        $this->t("Unify"),
        $this->t("Rendered Local Entity"),
        $this->t("Rendered Remote Entity")
      ),
    );


    for ($i = 0; $i < count($conflictUris); $i++) {
      // checkboxes
      $form['stores']['table'][$i]['checkbox'] = array(
        '#type' => 'checkbox',
      );

      $form['stores']['table'][$i]['values'] = array(
        '#type' => 'markup',
        '#markup' => implode(", ", $conflictValues[$i]) . "URIs" . implode(", ", $conflictUris[$i]),
      );


      // render Entities
      $form['stores']['table'][$i]['local'] = array(
        '#type' => 'markup',
        '#markup' => $conflictRenderData[$i]['local']
      );
      $form['stores']['table'][$i]['external'] = array(
        '#type' => 'markup',
        '#markup' => $conflictRenderData[$i]['external']
      );
    }

    # store values for later use
    $form['stores']['conflict_properties'] = array(
      '#type' => 'value',
      '#value' => $conflictValues,
    );

    $form['stores']['conflict_uris'] = array(
      #'value' instead of 'hidden' type to avoid "Warning: Array to string conversion"
      '#type' => 'value',
      '#value' => $conflictUris,
    );

    $form['stores']['apply'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Unify'),
      //        '#submit' => array('::unify')
    );


    return $form;
  }

  /*
   * Return the 'stores' field of the form.
   * Used for getting the data to be displayed on AJAX callback.
   */
  public static function ajaxStores(array $form, FormStateInterface $form_state) {
    return $form['stores'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    #   dpm('hello1');
  }


  public function getSelectedUris(FormStateInterface $form_state) {
    $uris = $form_state->getValue('conflict_uris');
    $boxValues = $form_state->getValues('values')['table'];

    $selectedUris = array();
    for ($i = 0; $i < count($boxValues); $i++) {
      if ($boxValues[$i]['checkbox'] != 0)
        $selectedUris[] = $uris[$i];
    }

    return $selectedUris;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    #    dpm('hello');
    #i-candidates"

    $uris = $form_state->getValue('conflict_uris');
    $properties = $form_state->getValue('conflict_properties');

    #dpm($conflictUris, "conflicts");
    #dpm($conflict_values, "conflict_values");
    #dpm($form_state, "fs?");
    #dpm($form_state->getValues('values'), 'values?');

    $selectedUris = $this->getSelectedUris($form_state);

    StaticQueries::insertLinks($selectedUris)->update();

    $form_state->setRebuild(TRUE);
    $form_state->setValue('table', array());
    $input = $form_state->getUserInput();
    $input['table'] = array();
    $form_state->setUserInput($input);
    #    $options = 
    #    dpm("submit called!");

    return;
  }
}
