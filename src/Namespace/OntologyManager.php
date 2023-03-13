<?php

namespace Drupal\wisski_unify\Ontology;

use Drupal\Core\Url;
use Drupal\Core\Link;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;


/**
 */
class OntologyManager {
  /**
   * The Drupal config factory
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Drupal entity type manager
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entity_type_manager;

  /**
   * The loaded ontologies
   * 
   * @var array[]
   */
  protected $ontologies;

  /**
   * The constructor.
   */
  public function __construct(
      EntityTypeManagerInterface $entity_type_manager,
      ConfigFactoryInterface $config,
      ) {
    $this->entity_type_manager = $entity_type_manager;
    $this->config = $config;
    $this->ontologies = $this->loadFromConfig();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('config.factory'),
        );
  }

  private function loadFromConfig($configName = 'wisski_unify.ontology') : array {
    $ontologies = $this->config->get($configName)->getRawData();
    unset($ontologies['_core']);
    return $ontologies;
  }

  public function get($key){
    if(empty($this->ontologies) && ! $this->loadFromConfig()){
        return null;
    }

    if(!array_key_exists('base', $this->ontologies[$key])){
      return $this->ontologies[$key];
    }

    $ontology = $this->ontologies[$key];
    $uriBaseArray = [$ontology['base']];
    unset($ontology['base']);
    
    // add the path if present
    if(array_key_exists('path', $ontology)){
      $uriBaseArray[] = $ontology['path'];
      unset($ontology['path']);
    }

    // build complete base
    $uriBase = implode('', $uriBaseArray);
    foreach($ontology as $k => $v){
        $ontology[$k] = escape("{$uriBase}{$v}");
    }

    return $ontology;
  }

}
