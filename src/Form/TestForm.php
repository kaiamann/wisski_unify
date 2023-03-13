<?php

namespace Drupal\wisski_unify\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\wisski_unify\Entity\UnifyListBuilder;
use Drupal\wisski_unify\Utils;

class TestForm extends FormBase {

    public function getFormId() {
        return self::class;
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

		$query = \Drupal::entityQuery('wisski_individual');
        $bundle = 'ba2bc22ddf55cddfcdc37110f4a757ae';
        $field = 'f33e0f6ee55a9c495e61e9a360c339e3';
		// $query->condition('bundle', [$bundle]);
		$query->condition($field, ['Amberger, Christoph', "Bassano, Jacopo (da Ponte)"], 'IN');

        $value = "Bassano, Jacopo (da Ponte)";
        $unpacked = unpack('H*hex', $value);
        $externalValues = [];
        // $query->condition(, "Bassano, Jacopo (da Ponte)");
		// $query->condition('f33e0f6ee55a9c495e61e9a360c339e3', $externalValues, "IN");

		// $query->pager(1);
		$eids = $query->execute();
        dpm($eids);
        // $values = [];

		// $entities = \Drupal::entityTypeManager()->getStorage('wisski_individual')->loadMultiple($eids);
        // foreach($entities as $entity){
        //     $values[] = $entity->$field->value;
        // }

        // $query2 = \Drupal::entityQuery('wisski_individual');
		// $query2->condition('bundle', [$bundle]);
		// $query2->condition('f33e0f6ee55a9c495e61e9a360c339e3', $values, "IN");
        // dpm($query2->execute());
        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        dpm("submit");
        $query = [];

        $foo = $form_state->getValue('bundle') ?? 0;
        if ($foo) {
            $query['bundle'] = $foo;
        }

        $bar = $form_state->getValue('name') ?? 0;
        if ($bar) {
            $query['name'] = $bar;
        }

        $form_state->setRedirect('wisski.wisski_unify.test', $query);
    }


}
