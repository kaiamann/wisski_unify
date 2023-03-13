<?php

namespace Drupal\wisski_unify\Query;

use Drupal\wisski_unify\Query\QueryResultFormat;
use Drupal\wisski_unify\Query\SparqlQuery;

class StaticQueries {
    // Queries 

    /**
     * Query for getting appellation data for a given CRM superclass.
     *
     * @param string $superlcass
     *  The CRM superlcass to search under.
     *
     * @param array $filters
     *  The appallation values to be filtered out
     *
     * @return SparqlQuery 
     *  The query info
     */
    static function appellationInfo($superclass, $filters = array()) {
        $query = "SELECT DISTINCT ?o ?oclass ?leaf WHERE { \n";

        // superclass relations/definitions
        $query .= "?is_identified_by rdfs:subPropertyOf* ecrm:P1_is_identified_by .\n";
        $query .= "?appellation rdfs:subClassOf* ecrm:E41_Appellation .\n";
        $query .= "?oclass rdfs:subClassOf* ecrm:" . $superclass . " .\n";
        $query .= "?datatypeProperty a owl:DatatypeProperty .\n";

        // actual query
        $query .= "?o ?is_identified_by ?a .\n";
        $query .= "?o a ?oclass .\n";
        $query .= "?a a ?appellation .\n";
        $query .= "?a ?datatypeProperty ?leaf .\n";

        if (!empty($filters)) {
            $query .= self::buildFilters('?leaf', $filters);
        }
        $query .= "\n}";

        $variables = array(
            'o',
            'oclass',
            'leaf',
        );

        return new SparqlQuery($query, $variables);
    }

    /**
     * Query for getting WissKiInfo
     * 
     * @return SparqlQuery
     *  The query info
     */
    static function wisskiInfo(): SparqlQuery {
        $query = "SELECT ?name ?uri ?url ?prefix  WHERE { 
            ?uri a unify:wisski . 
            ?uri unify:has_label ?name . 
            ?uri unify:has_url ?url . 
            ?uri unify:has_uri_prefix ?prefix 
        }";

        $variables = array(
            'name',
            'uri',
            'url',
            'prefix'
        );

        return new SparqlQuery($query, $variables, UnifyOntology::getNamespaces());
    }

    /**
     * Query for getting all CRM classes
     *
     * @return SparqlQuery
     *  The query info 
     */
    static function ecrmClasses(): SparqlQuery {
        $query = "SELECT ?class WHERE { 
            GRAPH ecrm: { ?class a owl:Class } 
        }";
        return new SparqlQuery($query, ['class'], [], [], QueryResultFormat::COLUMN);
    }


    /**
     * Query for getting all present ecrm verisons
     */
    public static function ecrmVersions(): SparqlQuery {
        $query = "SELECT ?s WHERE { 
            ?s a owl:Ontology 
            FILTER(STRSTARTS(str(?s), 'http://erlangen-crm.org/'))
        }";
        $variables = ['s'];
        return new SparqlQuery($query, $variables, [], [], QueryResultFormat::COLUMN);
    }


    /**
     * Query for finding entities linked by copy_of.
     * e.g. e1 copy_of e2.
     *
     * @param string $baseUri
     *  The base Uri of the entity that is the recipient of the link (e.g. e2).
     *
     * @return array
     *  The query info
     */
    static function linkedEntities(string $baseUri = null): SparqlQuery {
        $query = "SELECT ?s ?o WHERE {";
        $query .= "GRAPH unify_data: { ?s unify:copy_of ?o . } .\n";
        if ($baseUri) {
            $query .= "FILTER (strStarts(str(?o), \"{$baseUri}\")) .\n";
        }
        $query .= "}";
        $variables = ['o', 's'];
        return new SparqlQuery($query, $variables, UnifyOntology::getNamespaces());
    }

    /*
   * Query for getting the number of items that will be deleted
   *
   * @param string $baseUri
   *  The unescaped base URI of the external WissKi URI 
   *  to which the copy_of link should be deleted.
   *
   * @return SparqlQuery
   *  The query info
   */
    static function numLinks(string $baseUri = null) {
        $query = "SELECT (COUNT(*) as ?cnt) WHERE {";
        $query .= "GRAPH unify_data: { ?s unify:copy_of ?o . } .\n";
        if ($baseUri) {
            $query .= "FILTER (strStarts(str(?o), \"" . $baseUri . "\")) .\n";
        }
        $query .= "}";

        return new SparqlQuery($query, ['cnt'], UnifyOntology::getNamespaces());
    }




    // Updates


    /**
     * Query for inserting links between matching uri pairs
     * 
     * @param $selectedUris
     * The array containing the Uri pairs
     * One Uri pair should have the form:
     * array(
     *    "local" => $localUri,
     *    "external" => externalUri,
     *  )
     * 
     * @return SparqlQuery
     *  The query info
     */
    static function insertLinks($selectedUris) {
        $f = function ($conflict) {
            return $conflict['local'] . ' unify:copy_of ' . $conflict['external'] . ' . ';
        };
        $triples = array_map($f, $selectedUris);

        $query = "INSERT DATA { GRAPH unify_data: {\n";
        $query .= implode('', $triples);
        $query .= "} }";

        return new SparqlQuery($query, [], UnifyOntology::getNamespaces());
    }

    static function insertLink($localUri, $externalUri) {
        $query = "INSERT DATA { GRAPH unify_data: {\n";
        $query .= "<$localUri> unify:copy_of  <$externalUri> . ";
        $query .= "} }";

        return new SparqlQuery($query, [], UnifyOntology::getNamespaces());
    }

    /*
   * Query for deleting copy_of links.
   *
   * @param string $baseUri
   *  The unescaped base URI of the external WissKi URI 
   *  to which the copy_of link should be deleted.
   *
   * @return array
   *  The query info
   */
    static function deleteLinks(string $baseUri = null): SparqlQuery {
        $query = "DELETE { ?s unify:copy_of ?o } WHERE ";
        $query .= "{ GRAPH unify_data: { ?s unify:copy_of ?o . } .\n";
        if ($baseUri) {
            $query .= "FILTER (strStarts(str(?o), \"" . $baseUri . "\")) .\n";
        }
        $query .= "}";

        return new SparqlQuery($query, [], UnifyOntology::getNamespaces());
    }

    /**
     * Builds a filter segment for a SPARQL query.
     *
     * @param string $varName
     *  The name of the variable that should be filtered
     * @param array filterItems
     *  The values that should be filtered against
     * @param string $op
     *  The operator that should be used for comparing
     * @param string/array
     *  The escape character/s for escaping the values
     *
     * @return string
     *  A SPARQL query filter segment
     */
    private static function buildFilters(string $varName, array $filterItems, string $op = '=', $esc = '\'') {
        if (!is_array($esc)) {
            $esc = array($esc, $esc);
        }
        $filterString = "FILTER(";
        $f = function ($i) use ($varName, $op, $esc) {
            return "\n" . $varName . " " . $op . " " . $esc[0] . $i . $esc[1] . " ";
        };
        $filters = array_map($f, $filterItems);
        $filterString .= implode('||', $filters) . ") . ";
        return $filterString;
    }
}
