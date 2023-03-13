<?php

namespace Drupal\wisski_unify\Query;

abstract class UnifyOntology
{
    /**
     * The base URI of the ontology
     * 
     * @var string $base
     */
    static $base = 'http://unify/';

    /**
     * The path to the ontology
     * 
     * @var string $ontology
     */
    static $ontology = 'ontology/';

    /**
     * The abbreviation for the ontology URI
     * 
     * @var string $prefix
     */
    static $prefix = 'unify';

    /** 
     * The path to the data graph where links are stored
     * 
     * @var string $data
     */
    static $data = 'data/';


    /**
     * The definitions of the Ontology
     * 
     * @var array $defs
     */
    static $defs = [
        'copy_of' => 'copy_of',
        'wisski' => 'WissKI',
        'has_label' => 'has_label',
        'has_url' => 'has_url',
        'has_uri_prefix' => 'has_uri_prefix',
    ];

    static function get($def){
        if(array_key_exists($def, self::$defs)){
            return self::$base . self::$ontology . self::$defs[$def];
        }
        return null;
    }

    static function ontologyGraph(){
        return self::$base . self::$ontology;
    }

    static function dataGraph(){
        return self::$base . self::$data;
    }

    static function getNamespaces(){
        return [
            self::$prefix => self::ontologyGraph(),
            self::$prefix . "_data" => self::dataGraph(),
        ];
    }

    static function getPrefix(){
        return self::$prefix;
    }
}