<?php

use Drupal\wisski_adapter_sparql11_pb\Plugin\wisski_salz\Engine\Sparql11EngineWithPB;
use Drupal\wisski_unify\Ontology\OntologyManager;
use Drupal\wisski_unify\Plugin\rest\resource\UnifyResource;
use Drupal\wisski_unify\Query\SparqlQuery;
use Drupal\wisski_unify\Query\StaticQueries;
use Drupal\wisski_unify\Query\UnifyOntology;
use Drupal\wisski_unify\Utils;
use EasyRdf\RdfNamespace;

function isEscapedTest(){
    return isEscaped("false") ? "true" : "false";
}

function OntologyManagerTest(){
    $container = \Drupal::getContainer();
    $ontology = OntologyManager::create($container);
    var_dump($ontology->get('ecrm'));
}


function SimpleQueryTest(){
    $query = "SELECT ?s WHERE {
        GRAPH ecrm: { ?s ?p ?o }
    }";
    $variables = ['s'];
    //RdfNamespace::set('ecrm', 'http://this.is.bullshit/');
    $queryObj = new SparqlQuery($query, $variables);
    var_dump($queryObj->execute());
}


function linkedEntitiesTest(){
    $query = StaticQueries::linkedEntities();
    $result = $query->execute();

    if(!empty($result)){
        throw new \AssertionError("Not empty");
    }
    insertTestData();
    $result = $query->execute();
    if(count($result) !== 1){
        throw new \AssertionError("More than one link");
    }
    deleteTestData();
    $result = $query->execute();
    if(count($result) !== 0){
        throw new \AssertionError("Did not delete test data");
    }
    $funcName = __FUNCTION__;
    print("Passed $funcName\n");
}

function insertTestData(){
    $dataGraph = UnifyOntology::dataGraph();
    $copyOf = UnifyOntology::get('copy_of');
    $query = "insert data {
        GRAPH <$dataGraph> { <http://test.com/test1> <$copyOf> <http://test.com/test2> }
    }";
    $variables = [];
    $sparqlQuery = new SparqlQuery($query, $variables);
    $sparqlQuery->update();
}

function deleteTestData(){
    $dataGraph = UnifyOntology::dataGraph();
    $copyOf = UnifyOntology::get('copy_of');
    $query = "delete data {
        GRAPH <$dataGraph> { <http://test.com/test1> <$copyOf> <http://test.com/test2> }
    }";
    $variables = [];
    $sparqlQuery = new SparqlQuery($query, $variables);
    $sparqlQuery->update();

}

function ecrmVersionTest(){
    var_dump(StaticQueries::ecrmVersion());
}

function queryTestSuite(){
    linkedEntitiesTest();

}

//deleteTestData();
//queryTestSuite();

//ontologyTest();

//OntologyManagerTest();
//return ontologyExtractorTest();



function testGetResource(){
    $url = "https://kai.wisski.data.fau.de/wisski/unify/rest/test?_format=json";

    $credentials = ['kaiamann','ThisIsThePassword'];
    $headers = array(
      'Application' => 'application/json',
      'Authorization' => 'Basic ' . base64_encode(implode(':', $credentials))
    );
    $response = Utils::get($url, $headers);
    $pbs = [];
    $normalizedPbs = $response['body'];
    //$pb = Utils::deserializeEntity($response['body'], "Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity");
    //var_dump($pb->getAllGroups());
    
    foreach($normalizedPbs as $normalizedPb){
        $pb = Utils::denormalizeEntity($normalizedPb, "Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity");
        $pbs[] = $pb;
        var_dump($pb->getName());
    }
}


function testDocuments(){
    $query = \Drupal::entityQuery('node');
    $nodes = $query->execute();
    foreach($nodes as $node){
        //var_dump(\Drupal::entityTypeManager()->getStorage('node')->load($node));

    $query = \Drupal::database()->select('path_alias', 'a');
    $query->addField('a', 'path');
    $query->condition('a.alias', "/test");
    var_dump($query->execute()->fetchAll());

    $deleteQuery = \Drupal::database()->delete('path_alias');
    $deleteQuery->condition('alias', '/test');
    $deleteQuery->execute();
}

testDocuments();

// testGetResource();