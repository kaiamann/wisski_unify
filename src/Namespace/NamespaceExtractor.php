<?php

namespace Drupal\wisski_unify\Namespace;

use Drupal\wisski_unify\Query\SparqlQuery;
use Drupal\wisski_unify\Ontology\Ontology;

/**
 * Extracts the definitions of the explicit 
 * Ontologies from the Triplestore.
 */
class TriplestoreExtractor implements ExtractorInterface
{

  protected $configName = "wisski_";

  public static function extract() : array
  {
    return self::extractNamespacesFromTriplestore();
  }

  public static function getNamespaces(): array
  {
    return [];
  }

  public static function extractNamespacesFromTriplestore()
  {
    $query = self::extractNamespacesQuery();
    $rawOntologies = $query->execute();
    $ontologies = [];
    foreach ($rawOntologies as $ontology) {
      $base = $ontology['base'];
      $path = $ontology['path'];
      $definitions = explode(',', $ontology['defs']);
      $defs = array_combine($definitions, $definitions);
      $ontologies[$base] = new Ontology($base, $path, $defs);
    }
    return $ontologies;
  }

  /**
   * Returns a Query which queries the definitions of
   * the explicit ontologies.
   * 
   * @param string $w3Ontology
   *  The URI of a w3 Ontology
   * 
   * @return SparqlQuery
   *  The query object
   */
  private static function extractNamespacesQuery()
  {
    $query = "select distinct ?g as ?ns) where {
      GRAPH ?g { ?g a owl:Ontology }
    } ORDER BY ?g";
    $variables = ['g'];

    return new SparqlQuery($query, $variables);
  }

  /**
   * Extracts the definitions of the explicit 
   * Ontologies from the Triplestore.
   * 
   * return Ontology[]
   *  an array of Ontologies indexed by their base URI
   */
  public static function extractDefinitionsFromTriplestore()
  {
    $query = self::extractDefinitionsQuery();
    $rawOntologies = $query->execute();
    $ontologies = [];
    foreach ($rawOntologies as $ontology) {
      $base = $ontology['base'];
      $path = $ontology['path'];
      $definitions = explode(',', $ontology['defs']);
      $defs = array_combine($definitions, $definitions);
      $ontologies[$base] = new Ontology($base, $path, $defs);
    }
    return $ontologies;
  }

  /**
   * Returns a Query which queries the definitions of
   * the explicit ontologies.
   * 
   * @param string $w3Ontology
   *  The URI of a w3 Ontology
   * 
   * @return SparqlQuery
   *  The query object
   */
  private static function extractDefinitionsQuery()
  {
    $query = "select distinct ?base ?path (GROUP_CONCAT(?def; separator=',') as ?defs) where {
      GRAPH ?g {
        ?g a owl:Ontology .
        ?s ?p ?o
      }
      BIND(REPLACE(str(?g), 'http[s]?://[^/]*/', '') AS ?path)
      BIND(REPLACE(str(?g), ?path, '') AS ?base)
      BIND(REPLACE(str(?s), CONCAT(?base, ?path), '') as ?def)
      FILTER(?def != '')
      FILTER(?path != '')
    } GROUP BY ?base ?path";
    $variables = ['base', 'path', 'defs'];

    return new SparqlQuery($query, $variables);
  }
}
