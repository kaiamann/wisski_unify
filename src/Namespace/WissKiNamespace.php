<?php

namespace Drupal\wisski_unify\Ontology;

use \EasyRdf\RdfNamespace;

/**
 * This class serves as a rudimentary representation of an ontology
 * 
 * Only provides the URIs of the defined Classes/Predicates 
 */
class WissKiNamespace extends RdfNamespace {
    private static $baseNsConfig = 'wisski.base_namespaces';
    private static $nsConfig = 'wisski.namespaces';

    protected static $initialBaseNamespaces = array(
        'ecrm' => 'http://erlangen-crm/',
        'oin'  => 'http://objekte-im-netz.fau.de/',
    );

    protected static $baseNamespaces = null;

    public static function baseNamespaces(){
        $baseNamespaces = \Drupal::state()->get(self::$baseNsConfig);
        if(empty($baseNamespaces)){
            self::resetBaseNamespaces();
        }
        return self::$baseNamespaces;
    }

    public static function resetBaseNamespaces(){
        \Drupal::state()->set(self::$baseNsConfig, self::$initialBaseNamespaces);
    }

    public static function setBaseNamespace($prefix, $long){
        parent::set($prefix, $long);
        $baseNamespaces = \Drupal::state()->get(self::$baseNsConfig);
        $baseNamespaces[$prefix] = $long;
        \Drupal::state()->set(self::$baseNsConfig, $baseNamespaces);
    }

    public static function getBaseNamespace($prefix){
        $baseNamespaces = \Drupal::state()->get(self::$baseNsConfig);
        if(array_key_exists($prefix, $baseNamespaces)){
            return $baseNamespaces[$prefix];
        }
        return null;
    }

}