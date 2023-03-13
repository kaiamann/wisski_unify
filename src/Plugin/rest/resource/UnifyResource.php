<?php

namespace Drupal\wisski_unify\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;

use Drupal\wisski_salz\AdapterHelper;
use Drupal\wisski_core\WisskiCacheHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_salz\Plugin\Action\SparqlQuery;
use Drupal\wisski_unify\Utils;
use Drupal\wisski_unify\Queries;
use Drupal\wisski_unify\Query\SparqlQuery as QuerySparqlQuery;
use Drupal\wisski_unify\Query\StaticQueries;
use EasyRdf\RdfNamespace;

/**
 * Provides a Demo Resource
 
 * @RestResource(
 *   id = "unify_resource",
 *   label = @Translation("Unify Resource"),
 *   uri_paths = {
 *     "canonical" = "/wisski/unify/rest",
 *     "create" = "/wisski/unify/rest"
 *   }
 * )
 */
class UnifyResource extends ResourceBase {

  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get(Request $request) {
    $queryArgs = $request->query->all();
    $pbs = $this->getPathbuilders();

    if(array_key_exists('group', $queryArgs)){
      $groupId = $queryArgs['group'];
      $paths = Utils::getPathsForGroupId($pbs, $groupId);
      return new ModifiedResourceResponse($paths);
    }
    if(array_key_exists('path', $queryArgs)){
      $pathId = $queryArgs['path'];

      // get bundle and field ID for path
      $pbPaths = Utils::getPbPathsForPbs($pbs);
      $fieldInfo = Utils::getBundleAndFieldForPath($pathId, $pbPaths);
      $bundleId = $fieldInfo['bundle'];
      $fieldId = $fieldInfo['field'];

      // get entities for path
      $query = \Drupal::entityQuery('wisski_individual');
      $query->condition('bundle', [$bundleId]);
      $eids = $query->execute();
      $entities = \Drupal::entityTypeManager()->getStorage('wisski_individual')->loadMultiple($eids);

      // get uris and key them by field value
      $values = [];
      foreach($entities as $entity){
        $value = $entity->$fieldId->value;
        $uri = $entity->wisski_uri->value;
        $values[$value][] = $uri;
      }
      
      return new ModifiedResourceResponse($values);
    }

    if(array_key_exists('uri', $queryArgs)){
      $uri = $queryArgs['uri'];
      $entity = Utils::getEntityForUri($uri);
      $html = Utils::renderEntity($entity);
      $serializedEntity = Utils::HTMLToArray($html);

      return new ModifiedResourceResponse($serializedEntity);
    }

    return new ModifiedResourceResponse(Utils::getGroupsForPbs($pbs));
  }



  public static function getPathbuilders(){
    return \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();
  }

  public function post($data){
    $decodedData = $data;

    #get query parameters
    $leafData = $decodedData['values'];
    $class = $decodedData['class'];


    $ecrmVersions = StaticQueries::ecrmVersions()->execute()['s'];
    if(count($ecrmVersions) > 1){
      $versionsString = implode(', ',$ecrmVersions);
      \Drupal::messenger()->addError("Found multiple versions of ECRM: $versionsString, which is currently not supported!");
      // return $form;
    }

    // get available ECRM classes
    $ecrmVersion = current($ecrmVersions);
    RdfNamespace::set('ecrm', $ecrmVersion);

    $result = StaticQueries::appellationInfo($class, $leafData)->execute();
    
    $matchingEntities = array();
    //return new ModifiedResourceResponse($result);

    foreach($result as $res){
      $value = $res['leaf'];
      $uri = str_replace(['<','>'], '', $res['o']);

      $eid = AdapterHelper::getDrupalIdForUri($uri, false);
      // $duplicateEntities[$value][] = Utils::normalizeEntity(Utils::getEntityForUri($uri));
      WisskiCacheHelper::flushCallingBundle($eid);

      $entity = Utils::getEntityForUri($uri);
           
      $html = Utils::renderEntity($entity);
      // filter entities that cannot be rendered for now
      //if(!$html)
        //continue;
      $matchingEntities[$value][$uri] = $html;
    }
    return new ModifiedResourceResponse($matchingEntities);

  }
}

