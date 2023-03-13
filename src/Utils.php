<?php

/**
 * @file
 *
 */

namespace Drupal\wisski_unify;

use Drupal\wisski_salz\AdapterHelper;
use Guzzle\Http\Exception\RequestException;
use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;

use DOMDocument;
use DOMElement;
use DOMText;

use Drupal\Component\Serialization\Json;


class Utils {


  // Networking

  /*
   * Performs a POST query to a URL.
   *
   * @param string $url
   *  The target URL
   * @param integer $timeout
   *  The timeout for the request
   *
   * @return array/int
   *  The response or status code in case of an error
   */
  public static function post($url, $data=[], $headers = array(), $timeout = 120) {

    // Make the request.
    $options = [
      'connect_timeout' => $timeout,
      'timeout' => $timeout,
      //'debug' => true,
      'headers' => $headers,
      'json' => $data,
      'verify' => true,
    ];

    $client = \Drupal::httpClient();


    try {
      $response = $client->request('POST', $url, $options);
      // dpm($request->getBody()->getContents());
      // return $request->getBody()->getContents();
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      if ($e->hasResponse()) {
        $response = $e->getResponse();
        $decodedBody = json_decode((string) $response->getBody()); // Body as the decoded JSON;
      }
    }
    finally {
      $statusCode = $response->getStatusCode(); // HTTP status code;
      $message = $response->getReasonPhrase(); // Response message;
      //var_dump($response->getHeaders()); // Headers array;
      //var_dump($response->hasHeader('Content-Type')); // Is the header presented?
      //var_dump($response->getHeader('Content-Type')[0]); // Concrete header value;

      //$decodedBody = Json::decode($response->getBody()->getContents());
      $body = $response->getBody();
      if(is_array($decodedBody)){
        $body = $body->getContents();
      }
      $decodedBody = Json::decode($body);

      return array(
        'code' => $statusCode,
        'message' => $message,
        'body' => $decodedBody,
      );
    }
  }

  public static function get($url, $headers){
    $client = \Drupal::httpClient();

    $timeout = 120;

    $options = [
      'connect_timeout' => $timeout,
      'timeout' => $timeout,
      //'debug' => true,
      'headers' => $headers,
      'verify' => true,
    ];

    $decodedBody = null;
    $response = null;
    try {
      $response = $client->request('GET', $url, $options);
      // dpm($request->getBody()->getContents());
      // return $request->getBody()->getContents();
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      if ($e->hasResponse()) {
        $response = $e->getResponse();
        $decodedBody = json_decode((string) $response->getBody()); // Body as the decoded JSON;
      }
    }
    finally {
      if(!$response){
        return array(
          'code' => 404,
          'message' => "Not found",
          'body' => "",
        );

      }
      $statusCode = $response->getStatusCode(); // HTTP status code;
      $message = $response->getReasonPhrase(); // Response message;
      //var_dump($response->getHeaders()); // Headers array;
      //var_dump($response->hasHeader('Content-Type')); // Is the header presented?
      //var_dump($response->getHeader('Content-Type')[0]); // Concrete header value;

      $body = $response->getBody();
      if(is_array($decodedBody)){
        $body = $body->getContents();
      }
      $decodedBody = Json::decode($body);

      return array(
        'code' => $statusCode,
        'message' => $message,
        'body' => $decodedBody,
      );
    }

  }


  // Random Helpers

  /*
   * Applies a function to all passed variables.
   * Basically array_map without array.
   *
   * @param $f 
   *  The funciton to be applied
   *
   * @param $args
   *  A list of variables
   */
  static function forall($f, &...$args) {
    foreach ($args as $k => &$v) {
      $v = $f($v);
    }
  }

  /*
   * Sorts the given array by numbers occuring in values
   *
   * @param array &array
   *  The array to sort
   *
   * @return array
   *  The sorted array
   */
  public static function numsort($array) {
    $f = function ($s1, $s2) {
      $n1 = preg_replace('/\D/', '', $s1);
      $n2 = preg_replace('/\D/', '', $s2);
      if ($n1 > $n2)
        return 1;
      if ($n1 < $n2)
        return -1;
      else
        return 0;
    };
    uasort($array, $f);
    return $array;
  }


  /*
   * Compare two arrays and
   * searches for matches/conflicts.
   *
   * @param $a1
   *  The array to be compared
   * @param $a2
   *  The array that $a1 should to be compared to 
   *
   * @return array
   *  An array of the same structure as $a1, but having the
   *  values replaced by numbers representing an comparison status:
   *  0 => match
   *  1 => conflict
   *  2 => not present in $a2
   *
   */
  static function compare($a1, $a2) {
    $p1 = array();

    foreach ($a1 as $key => $value) {
      if (array_key_exists($key, $a2)) {
        if (is_array($a1[$key]) && is_array($a2[$key])) {
          $p1[$key] = self::compare($a1[$key], $a2[$key]);
        } else if (!is_array($a1[$key]) && !is_array($a2[$key])) {
          $p1[$key] = $a1[$key] == $a2[$key] ? 0 : 1;
        } else if (is_array($a1[$key])) {
          foreach ($a1[$key] as $k => $v) {
            $p1[$key][$k] = $v == $a2[$key] ? 0 : 1;
          }
        }
      } else {
        if (is_array($a1[$key])) {
          $p1[$key] = self::compare($a1[$key], []);
        } else {
          $p1[$key] = 2;
        }
      }
    }

    return $p1;
  }



  // Entity Serialization/Normalization

  /**
   * Converts an entity to an array
   *
   * @param Entity $entity
   *    The entity to be serialized
   *
   * @return array
   *    The array representation of the entity
   */
  public static function normalizeEntity($entity) {
    $serializer = \Drupal::service('serializer');
    return $serializer->normalize($entity);
  }


  /*
   * Converts an array to an Entity
   *
   * @param array $array
   *  The array to be converted
   * @param string $entityType
   *  The desired entity class
   */
  static function denormalizeEntity($array, $entityType) {
    $serializer = \Drupal::service('serializer');
    return $serializer->denormalize($array, $entityType);
  }

  /**
   * Deserializes a JSON string into an Entity
   *
   * @param string $json
   *  The JSON string representing the Entity
   * @param string $class
   *  The class of the Entity, relevant here:
   *  - Drupal\wisski_pathbuilder\Entity\WisskiPathEntity
   *  - Drupal\wisski_core\Entity\WisskiEntity
   *
   * @return Entity
   *  The deserialized Entity
   */
  static function deserializeEntity($json, $entityType) {
    $serializer = \Drupal::service('serializer');
    return $serializer->deserialize($json, $entityType, 'json');
  }

  /**
   * Converts an entity to JSON
   *
   * @param Entity $entity
   *    The entity to be serialized
   *
   * @return string
   *    The JSON string
   */
  static function serializeEntity($entity) {
    $serializer = \Drupal::service('serializer');
    return $serializer->serialize($entity, 'json', ['plugin_id' => 'entity']);
  }


  // Drupal specific stuff

  /*
   * Returns the Entity for a given Entity URI
   *
   * @param string $uri
   *    The URI
   * @param string $entity_type
   *    The expected type of the entity
   *
   * @return WissKiEntity(?)
   */
  static function getEntityForUri($uri, $entity_type = 'wisski_individual') {
    // make sure uri is unescaped for drupal
    $entity_id = AdapterHelper::getDrupalIdForUri($uri);
    return \Drupal::service('entity_type.manager')->getStorage($entity_type)->load($entity_id);
  }

  static function getEntityForId($id) {
    return \Drupal::service('entity_type.manager')->getStorage('wisski_individual')->load($id);
  }



  /*
   * Gets the wisski credentials for an external wisski
   * from /wisski_unify/config/install/wisski_unify.credentials.yml
   *
   * @param string $host
   *  The hostname of the desired wisski
   *
   * @return array
   *  An array containaing the credentials,
   *  or null if none were found.
   */
  public static function getCredentials($host) {
    $config = \Drupal::config('wisski_unify.credentials');
    $rawData = $config->getRawData();
    foreach ($rawData as $wisski) {
      if (
        array_key_exists('host', $wisski) &&
        array_key_exists('user', $wisski) &&
        array_key_exists('password', $wisski)
      ) {
        return array(
          $wisski['user'],
          $wisski['password']
        );
      }
    }
    return null;
  }


  // Entity Rendering/HTML conversion

  /*
   * Produces the render data for a given entity,
   *
   * @param Entity $entity
   *  The entity to be rendered
   * @param string $entityType
   *  The type of the entity
   * @param string $viewMode
   *  The view mode
   *
   * @return array
   *  The render array
   */
  static function preRenderEntity($entity, $entityType = 'wisski_individual', $viewMode = 'full') {
    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entityType);
    $preRender = $viewBuilder->view($entity, $viewMode);
    return $preRender;
  }

  /*
   * Converts an Entity into renderable HTML
   * using Drupals built-in render service.
   *
   * @param Entity $entity
   *  The entity to be rendered
   * @param string $entityType
   *  The type of the entity
   * @param string $viewMode
   *  The view mode
   *
   * @return string
   *  The html render data 
   */
  static function renderEntity($entity, $entityType = 'wisski_individual', $viewMode = 'full') {
    if (!$entity) {
      return [];
    }
    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entityType);
    $preRender = $viewBuilder->view($entity, $viewMode);
    $renderOutput = render($preRender);
    return $renderOutput;
  }






  /*
   * Gets the route path for a route
   *
   * @param string $routeName
   *  The Drupal name of the route
   */
  static function getRoutePath($routeName) {
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName($routeName);
    // $controller = $route->getPath('_controller');
    return $route->getPath();
  }


  // HTML conversion stuff

  static function HTMLToArray($html) {
    // remove unneccessary whitespaces between the tags
    // $html = preg_replace('/\>\s+\</m', '><', $html);
    // decode dumb special chars

    $html = htmlspecialchars_decode($html);

    $dom = new DOMDocument();

    $internalErrors = libxml_use_internal_errors(true);
    $dom->loadHTML('<meta charset="utf-8">' . $html);
    libxml_use_internal_errors($internalErrors);

    $body = $dom->getElementsByTagName('body')[0];
    $res = array();
    foreach ($body->childNodes as $child) {
      if ($child instanceof DOMElement)
        $res = array_merge($res, self::entityHTMLToArray($dom, $child));
    }
    return $res;
  }

  /*
   * Converts a DOMElement to array.
   *
   * @param DOMElement
   *  The DOMElement to be converted.
   *
   * @return arary
   *  The serialized DOMElement data
   */
  static private function entityHTMLToArray(DOMDocument $document,DOMElement | DOMText $element){

    if($element instanceof DOMText){
      return [];
    }

    $tag = $element->tagName;
    if($tag === "a"){
      $href = $element->getAttribute('href');
      // replace relative links with full URL
      if (str_starts_with($href, "/wisski/navigate/")) {
        $host = \Drupal::request()->getSchemeAndHttpHost();
        $href = $host . $href;
      }
      $element->setAttribute('href', $href);
      return [$document->saveHTML($element)]; 
      // return ['value' => "<a href=\"$href\">{$element->nodeValue}</a>"];
    }

    $class = $element->getAttribute('class');

    // case field wrapper div
    if(str_contains($class, "field field--name")){
      $isText = false;
      // TODO: figure out if this suffices
      if(str_contains($class, "text")){
        $isText = true;
      }

      $res = [];
      foreach($element->childNodes as $child){
        $res = array_merge($res, self::entityHTMLToArray($document, $child));
      }
      $label = $res['label'];
      $value = $res['value'];
      if($isText){
        if(is_array($value)){
          $value = implode("", $value);
        }
      }
      return array($label => $value);
    }
    // label
    if($class === "field__label"){
      return ['label' => $element->nodeValue];
    }

    // single field value
    if($class === "field__item"){
      // default to node value for e.g. plain_text
      $value = $element->nodeValue;

      // // Link or text
      // if($element->childElementCount === 1){
      //   $child = $element->childNodes[0];
      //   $value = self::entityHTMLToArray($document, $child);
      // }

      // Rendered Entity or HTML markup
      if($element->childElementCount > 0){
        $value = [];
        foreach($element->childNodes as $child){
          $value = array_merge($value, self::entityHTMLToArray($document, $child));
        }
      }

      return ['value' => $value];
    }

    // multiple field values
    if($class === "field__items"){
      $values = array();
      foreach($element->childNodes as $child){
        $value = self::entityHTMLToArray($document, $child);
        if(array_key_exists('value',$value)){
          $v = $value['value'];
          $v = is_array($v) ? $v : array($v);
          $values += array_merge($values,$v);
        }
      }
      return ['value' => $values];
    }
    return [$document->saveHTML($element)];
  }



	static function getPbPathsForPbs($pbs){
		$pbPaths = [];
		foreach($pbs as $pb){
			$pbPaths =array_merge($pbPaths, $pb->getPbPaths());
		}
		return $pbPaths;
	}

  public static function getBundleAndFieldForPath($pathId, $pbPaths){
    if(!array_key_exists($pathId, $pbPaths)){
      return [];
    }
    $bundleId = $pbPaths[$pathId]['bundle'];
    $fieldId = $pbPaths[$pathId]['field'];
    return array(
      'bundle' => $bundleId,
      'field' => $fieldId,
    );
  }


  public static function getEntitiesForPathId($pathId, $pbs){
    $path = \Drupal::entityTypeManager()->getStorage('wisski_path')->load($pathId);

    $entities= array();
    foreach ($pbs as $pb) {
      $adapter = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->load($pb->getAdapterId());
      $engine = $adapter->getEngine();

      if (!($engine instanceof Sparql11EngineWithPB))
        continue;

      $triples = $engine->generateTriplesForPath($pb, $path);
      $query = "SELECT DISTINCT ?x0 ?out WHERE { $triples }";
      $result = $engine->directQuery($query);

      foreach($result as $row){
        $uri = $row->x0->getUri();
        $value = $row->out->getValue();
        $entity = Utils::getEntityForUri($uri);
        $html = Utils::renderEntity($entity);
        $entities[$value][$uri] = $html;
      }
    }
    return $entities;
  }


  /*
   * Extracts the data from a wisski Enity
   *
   * Deprecated Do not use
   */
  static function extractWissKiData($entity) {
    $defaultFieldNames = [
      'eid',
      'langcode',
      'bundle',
      'published',
      'wisski_uri',
      'label',
      'preview_image',
      'default_langcode'
    ];
    $normalizedEntity = array();
    foreach ($entity as $fieldName => $fieldItemList) {
      if (in_array($fieldName, $defaultFieldNames))
        continue;
      foreach ($fieldItemList as $weight => $fieldItem) {
        $label = $fieldItem->getFieldDefinition()->getLabel();
        if ($label instanceof \Drupal\Core\StringTranslation\TranslatableMarkup) {
          $label = $label->render();
        }

        // get the raw values from the field item
        $fieldItemValue = $fieldItem->getValue();
        foreach ($fieldItemValue as $key => $value) {
          $res = null;
          if ($key == 'target_id') {
            $linkedEntity = \Drupal::entityTypeManager()->getStorage('wisski_individual')->load($value);
            //if($label == "Copy of"){
            $res = $linkedEntity->toLink()->toString()->getGeneratedLink();
            //}
            //This creates infinite loops because of circles 
            //in the data when copy_of links are involved
            //else {
            //  $res = self::extractWissKiData($entity);
            //}
          }
          if ($key == 'value') {
            $res = $value;
          }
          if ($key == 'uri') {
            $res = '<a href="' . $value . '">' . $value . '</a>';
          }
          if ($res) {
            $normalizedEntity[$label][] = $res;
          }
        }
      }
    }
    return $normalizedEntity;
  }

	public static function getGroupsForPbs(array $pbs) : array {
    $groups = array();
		foreach ($pbs as $pb) {
			$groups = array_merge($groups, $pb->getAllGroups());
		}
    $groupNames = array();
    foreach ($groups as $group){
      $groupNames[$group->id()] = $group->label();
    }
		return $groupNames;
	}


  static function getPathsForGroupId(array $pbs, $groupId){
    $paths = array();
    foreach ($pbs as $pb) {
      $paths = array_merge($paths, $pb->getAllPathsForGroupId($groupId, TRUE));
    }
    $pathNames = array();
    $pathArrays = array();
    foreach ($paths as $path) {
      //dpm($path);
      $datatypeProperty = $path->getDatatypeProperty();
      // Only look at paths that actually 
      // contain datatypeProperties
      if($datatypeProperty === "empty"){
        continue;
      }

      $pathNames[$path->getID()] = $path->label();

      $pathArray = $path->getPathArray();
      $pathArray[] = $datatypeProperty;
      $pathArrays[$path->getID()] = $pathArray; 
    }
    return array(
      'path_names' => $pathNames,
      'path_arrays' => $pathArrays,
    );
  }


}
