<?php

namespace Drupal\wisski_unify\Controller;

// Change following https://www.drupal.org/node/2457593
// See https://www.drupal.org/node/2549395 for deprecate methods information
// use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Html;
// use Html instead SAfeMarkup
use Drupal\rest\ResourceResponse;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Serialization\Json;

use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;

use Drupal\wisski_unify\Utils;
use Drupal\wisski_unify\Queries;

/**
 * Controller routines for Lorem ipsum pages.
 */
class UnifyController {

  /**
   * Constructs Lorem ipsum text with arguments.
   * This callback is mapped to the path
   * 'wisski/unify'.
   * 
   * @param string $var1
   *   The amount of paragraphs that need to be generated.
   * @param string $var2
   *   The maximum amount of phrases that can be generated inside a paragraph.
   */
  public function action(Request $request){
    return $request;
  }

  public function PostTest1($url){
    $client = \Drupal::httpClient();
    $method = 'POST';
    $options = [
      'headers' => [
        'Content-type' => 'application/json',
      ],
      'form_params' => [
        'name' => 'value',
      ],
    ];
      try {
        $response = $client->request($method, $url, $options);
        $code = $response->getStatusCode();
        if ($code == 200) {
          $body = $response->getBody()->getContents();
          return $body;
        }
      }
      catch (RequestException $e) {
        watchdog_exception('custom_modulename', $e);
      }

  }

  public function test(){

    $url = 'https://kai.wisski.agfd.fau.de/wisski/unify/external';
    $uri = 'http://objekte-im-netz.fau.de/orangerie/content/5d5ba247c08af';

    $entity = Utils::getEntityForUri($uri);
    $form['table'] = Utils::preRenderEntity($entity);
    $form['markup'] = array(
        '#type' => 'markup',
        '#markup' => Utils::renderEntity($entity)
        );
    return $form;

    $testNames = ['Die Geburt Christi', 'Raddatz, Nadine', 'Amberger, Christoph'];
    $testClass = '<http://erlangen-crm.org/170309/E21_Person>';

    $data = [
      'values' => $testNames,
      'class' => $testClass
    ];

    $result = Utils::post($url, $data);
    $entities = Json::decode($result);
    dpm($entities);

    return array(
        '#type' => 'markup',
        '#markup' => "Hasdasd"
        );
  }


  /*
   * Endpoint for route 'wisski/unify/entities'
   *
   * @param Request $request
   *  The POST request
   */
  public function getEntities(Request $request){
    $data = $request->getContent();
    $decodedData = Json::decode($data);

    $pathEntity = Utils::denormalizeEntity(WisskiPathEntity::class);
    $values = $decodedData['values'];

    $pbs = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();

    foreach ($pbs as $pb){
      $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($pb->getAdapterId());
      $engine = $adapter->getEngine();
      $triples = $engine->generateTriplesForPath($pb, $pathEntity);

      // Filter only the entities with the same value
      $filters = 'FILTER ';
      for($i = 0; $i<count($values); $i++){
        if($i != count($values)-1){
          $filters .= '?out = "' . $values[$i] . '" || ';
        } 
        else {
          $filters .= '?out = "' . $values[$i] . '") .';
        }
      }

      $query = 'SELECT DISTINCT ?out ?x0 WHERE { ' . $triples . ' ' . $filters . ' }';

      $result = $engine->directQuery($query);

      if(!empty($result) && count($result) > 0){
        $duplicateEntities = array();
        foreach($result as $res){
          $uri = $res->x0;
          $value = $res->out->getValue();
          $duplicateEntities[$value][] = Utils::normalizeEntity(Utils::getEntityForUri($uri));
        }
        return new JsonResponse($duplicateEntities); 
      }

    }
    return new CacheableJsonResponse("Nothing found");
  }


  /**
   * Routine for route 'wisski/unify/external'
   */
  public function external(Request $request){
    $data = $request->getContent();
    $decodedData = Json::decode($data);

    #get query parameters
    $leafData = $decodedData['values'];
    $class = $decodedData['class'];

    $query = Queries::appellationInfo($class, $leafData);

    $result = Queries::executeQuery(Queries::appellationInfo($class, $leafData));
    
    $duplicateEntities = array();

    foreach($result as $res){
      $value = $res['leaf'];
      $uri = $res['o'];

      // $duplicateEntities[$value][] = Utils::normalizeEntity(Utils::getEntityForUri($uri));
      $entity = Utils::getEntityForUri($uri);
      $duplicateEntities[$value][$uri] = array(
          'html' => Utils::renderEntity($entity),
          'data' => Utils::extractWissKiData($entity)
          );
    }
    return new JsonResponse($duplicateEntities);
  }


  public static function tete($data){

    #get query parameters
    $leafData = $data['values'];
    $class = $data['class'];

    $query = Queries::appellationInfo($class, $leafData);

    $result = Queries::executeQuery(Queries::appellationInfo($class, $leafData));
    
    $duplicateEntities = array();

    foreach($result as $res){
      $value = $res['leaf'];
      $uri = $res['o'];

      // $duplicateEntities[$value][] = Utils::normalizeEntity(Utils::getEntityForUri($uri));
      $entity = Utils::getEntityForUri($uri);
      $html = Utils::renderEntity($entity);
      $duplicateEntities[$value][$uri] = array(
          'html' => $html,
          'data' => Utils::extractWissKiData($entity)
          );
    }

    return $duplicateEntities;
  }

}

