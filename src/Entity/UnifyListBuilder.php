<?php

namespace Drupal\wisski_unify\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class UnifyListBuilder extends EntityListBuilder {

    protected $limit = 10;

    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type = null) {
        $wisskiEntityType = \Drupal::entityTypeManager()->getDefinitions()['wisski_individual'];
        return parent::createInstance($container, $wisskiEntityType);
    }

    public function __construct() {
        $wisskiEntityType = \Drupal::entityTypeManager()->getDefinitions()['wisski_individual'];
        $storage = \Drupal::entityTypeManager()->getStorage('wisski_individual');
        return parent::__construct($wisskiEntityType, $storage);
    }

    public function getEntityIds() {

        $query = \Drupal::entityQuery($this->entityTypeId);
        $query->pager($this->limit);
        $header = $this->buildHeader();
        $query->tableSort($header);

        $request = \Drupal::request();
        $foo = $request->get('bundle') ?? 0;
        dpm($foo);
        if ($foo) {
            $query->condition('bundle', [$foo]);
        }

        $bar = $request->get('name') ?? 0;
        if ($bar) {
            $query->condition('name', $bar);
        }

        if ($this->limit) {
            $query->pager($this->limit);
        }

        return $query->execute();
    }


    public function buildHeader() {
        $header['uri'] = "Uri";
        $header['name'] = "Name";

        return $header; // + parent::buildHeader();
    }

    public function buildRow(EntityInterface $entity) {
        $row['uri'] = $entity->wisski_uri->value;
        $row['name'] = "asdasd";// $entity->f33e0f6ee55a9c495e61e9a360c339e3->value;
        return $row;
    }

    public function render() {
        // $build['form'] = \Drupal::formBuilder()->getForm('\Drupal\wisski_unify\Form\TestForm');
        $build = parent::render();
        return $build;
    }
}
