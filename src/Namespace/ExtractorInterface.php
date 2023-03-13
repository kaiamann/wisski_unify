<?php

namespace Drupal\wisski_unify\Namespace;

interface ExtractorInterface {
    const baseNamespaces = array(
        'ecrm' => 'http://erlangen-crm/',
        'oin'  => 'http://objekte-im-netz.fau.de/',
    );

    public static function extract() : array;
    public static function getNamespaces() : array;
}
