<?php

namespace Drupal\wisski_unify\Query;

use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_salz\Entity\Adapter;
use EasyRdf\RdfNamespace;

const QUERY = 'query';
const VARIABLES = 'variables';



/*
 * TODO: turn this into a enum with PHP 8.1
 *
 * Used to define the format in which
 * a query result should be represented.
 *
 * ROW: list data by row
 * COLUMN: list data by columns
 */
abstract class QueryResultFormat {
  const ROW = 'row';
  const COLUMN = 'column';
}


class SparqlQuery {

  /**
   * Used Namespaces
   * 
   * @var array
   */
  protected $namespaces;


  /**
   * The query string 
   * 
   * @var string
   */
  protected $query;

  /**
   * The queried variables
   * 
   * @var array
   */
  protected $variables;

  /**
   * The result format of the query
   * 
   * @var QueryResultFormat
   */
  protected $format;


  /**
   * The adapters the query should be executed on
   * 
   * @var Adapter[];
   */
  protected $adapters;

  public function __construct($query, $variables, $namespaces = [], $adapters = [], $format = QueryResultFormat::ROW){
    $this->query = $query;
    $this->variables = $variables;
    $this->namespaces = $namespaces;
    $this->adapters = $adapters;
    $this->format = $format;
  }


  // Query execution
  public function execute(){
    // set namespaces
    foreach($this->namespaces as $prefix => $uri){
      RdfNamespace::set($prefix, $uri);
    }
    return $this->doExecute($this->query, $this->variables, $this->adapters, $this->format);
  }

  /*
   * Executes this query for the selected wisski_salz adapters.
   *
   * @param string $format
   *  The format in which the data should be returned.
   *
   * @return array
   *  The parsed result of the query.
   *  One entry corresponds to one query result row.
   */
  private function doExecute($query, $variables, $adapters = [], $format = QueryResultFormat::ROW){
    $results = array();

    // iterate over selected adapters
    if(empty($adapters)){
      $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    }

    foreach($adapters as $adapter){
      $engine = $adapter->getEngine();
      if (!$engine instanceof Sparql11EngineWithPB)
        continue;


      $queryResult = $engine->directQuery($query);

      if($format == QueryResultFormat::ROW){
        // iterate over each row
        foreach($queryResult as $res){
          $result = array();
          // iterate over variables of the row
          foreach($variables as $variable){
            if(method_exists($res->$variable, 'getUri')){
              $result[$variable] = $res->$variable->getUri();
            }
            else if (method_exists($res->$variable, 'getValue')){
              $result[$variable] = $res->$variable->getValue();
            }
            else {
              $results[$variable][] = "No Value";
            }
          }
          $results[] = $result;
        }
      }
      else if ($format == QueryResultFormat::COLUMN){
        // iterate over each row
        foreach($queryResult as $res){
          // iterate over variables of the row
          foreach($variables as $variable){
            if(method_exists($res->$variable, 'getUri')){
              $results[$variable][] = $res->$variable->getUri();
            }
            else if (method_exists($res->$variable, 'getValue')){
              $results[$variable][] = $res->$variable->getValue();
            }
            else {
              $results[$variable][] = "No Value";
            }
          }
        }
      }
    }

    return $results;
  }

  /*
   * Executes this query as an update
   *
   * @return array 
   *  an array of EasyRdf\Http\Response
   */
  public function update(){
    // set namespaces
    foreach($this->namespaces as $prefix => $uri){
      RdfNamespace::set($prefix, $uri);
    }
    // iterate over selected adapters
    $adapters = $this->adapters;
    if(empty($adapters)){
      $adapters = \Drupal::entityTypeManager()->getStorage('wisski_salz_adapter')->loadMultiple();
    }
    $res = array();
    foreach($adapters as $adapter){
      $engine = $adapter->getEngine();
      if (!$engine instanceof Sparql11EngineWithPB)
        continue;

      $res[] = $engine->directUpdate($this->query);
    }
    return $res;
  }
}

