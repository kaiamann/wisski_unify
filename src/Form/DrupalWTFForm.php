<?php
/**
 * @file
 *
 */

namespace Drupal\wisski_unify\Form;

use Drupal\wisski_salz\Entity\Adapter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

use Drupal\wisski_merge\Merger;
use Drupal\wisski_salz\AdapterHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
# import these for namespace resolution
use Drupal\wisski_core\Entity\WisskiEntity;
use Drupal\wisski_pathbuilder\Entity\WisskiPathEntity;
use Drupal\wisski_unify\Utils;



	const groupSelection = "group_selection";
	const pathSelection = "path_selection";
	const pathDisplay = "path_display";

/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the Duplication detection
 * @author Kai Amann
 */

class DrupalWTFForm extends FormBase {

	/**
	 * {@inheritdoc}.
	 * The Id of every WissKI form is the name of the form class
	 */
	public function getFormId() {
		return self::class;
	}

	public function getGroupsForPbs(array $pbs) : array {
    	$groups = array();
		$group_names = array();
		foreach ($pbs as $pb) {
			$groups = array_merge($groups, $pb->getAllGroups());
		}
		foreach ($groups as $group) {
			$group_names[$group->id()] = $group->label();
		}
		return $groups;
	}

	public function getExternalPbs($url = "https://kai.wisski.data.fau.de/wisski/unify/rest/test?_format=json", $credentials =['kaiamann','ThisIsThePassword']){
		$headers = array(
		'Application' => 'application/json',
		'Authorization' => 'Basic ' . base64_encode(implode(':', $credentials))
		);

		$response = Utils::get($url, $headers);
		// TODO: add error messages
		if($response['code'] != 200){
			return [];
		}

		$pbs = [];
		$normalizedPbs = $response['body'];
		
		foreach($normalizedPbs as $normalizedPb){
			$pbs[] = Utils::denormalizeEntity($normalizedPb, "Drupal\wisski_pathbuilder\Entity\WisskiPathbuilderEntity");
		}
		return $pbs;
	}

	/**
	 * {@inheritdoc}.
	 * 
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{

		// TODO: dependency injection
		$localPbs = \Drupal::entityTypeManager()->getStorage('wisski_pathbuilder')->loadMultiple();
		$externalPbs = $this->getExternalPbs();


		$table = array(
			'#type' => 'table',
			'#header' => array(
				'Local',
				'External'
			),
		);

		$localSelection = $this->buildSelection2($form, $form_state, $localPbs, 'local');
		$externalSelection = $this->buildSelection2($form, $form_state, $externalPbs, 'external');

		$table[0]['local'] = $localSelection;
		$table[0]['external'] = $externalSelection;

		$form['table'] = $table;
		return $form;
	}

	public function buildSelection2($form, $form_state, $pbs, $id){
		$groupSelectionId = groupSelection . $id;
		$pathSelectionId = pathSelection . $id;
		$pathDisplayId = pathDisplay . $id;

		$bogus = ["fuck", "this", "shit"];
		$selection[groupSelection] = array(
			'#type' => 'select',
			'#title' => 'Select the Group to check for duplicates',
			'#default_value' => '0',
			'#options' => array_merge(['0' => 'Please select'], $bogus),
			'#idt' => $id, 
			'#id_prefix' => groupSelection,
			'#ajax' => array(
				'callback' => '::getElement',
				'wrapper' => $pathSelectionId,
				'event' => 'change',
				//'effect' => 'slide',
			),
		);

		$selection[pathSelection] = array(
			'#prefix' => "<div id=\"$pathSelectionId\">",
			'#suffix' => '</div>',
		);

		$selection[pathDisplay] = array(
			'#prefix' => "<div id=\"$pathDisplayId\">",
			'#suffix' => '</div>',
		);


		if(empty($form_state->getValue(['table',0,$id,groupSelection]))){
			return $selection;
		}


		// $selection[pathDisplay] = array(
		// 	'#prefix' => "<div id=\"$pathDisplayId\">",
		// 	'#suffix' => '</div>',
		// );

		\Drupal::logger("?????")->notice("??????");
		\Drupal::messenger()->addMessage("???????");
		dpm("?????");
		$selection[pathSelection] = array(
			'#type' => 'select',
			'#title' => 'Select the Path to check for duplicates',
			'#default_value' => '0',
			'#options' => array_merge(['0' => 'Please select'], $bogus),
			'#idt' => $id, 
			'#id_prefix' => pathSelection,
			'#ajax' => array(
				'callback' => '::getElement',
				'wrapper' => $pathDisplayId,
				'event' => 'change',
				//'effect' => 'slide',
			),
		);



		if(empty($form_state->getValue(['table',0,$id,pathSelection]))){
			return $selection;
		}

		$selection[pathDisplay] = array(
			'#markup' => "FUck this",
			'#prefix' => "<div id=\"$pathDisplayId\">",
			'#suffix' => '</div>',
		);

		return $selection;

	}


	public function getElement($form, FormStateInterface $form_state){
		$triggeringElement = $form_state->getTriggeringElement();
		$id = $triggeringElement['#idt'];
		$idPrefix = $triggeringElement['#id_prefix'];

		switch($idPrefix){
			case groupSelection: {
				\Drupal::logger("wetasd")->notice(groupSelection);
				return $form['table'][0][$id][pathSelection];
			}
			case pathSelection: {
				\Drupal::logger("wetasd")->notice(pathSelection);
				return $form['table'][0][$id][pathDisplay];
			}
		}
	}


















































































	public function buildSelection(&$form, $form_state, $pbs, $id){
		$groups = $this->getGroupsForPbs($pbs);
		$getNames = function($group){
			return $group->label();
		};
		$groupNames = array_map($getNames, $groups);

		$groupSelectionId = 'group_selection_' . $id;
		$pathSelectionId  = 'path_selection_' . $id;

		$selection[$groupSelectionId] = array(
			'#type' => 'select',
			'#title' => 'Select the Group to check for duplicates',
			'#default_value' => '0',
			'#options' => array_merge(['0' => 'Please select'], $groupNames),
			'#groups' => $groups,
			'#id' => $id,
			'#pbs' => $pbs,
			'#ajax' => array(
				'callback' => '::buildPathSelection',
				'wrapper' => $pathSelectionId,
				'event' => 'change',
				//'effect' => 'slide',
			),
		);

		$pathDisplayId = "path_display_" . $id;
		$selection[$pathSelectionId] = array(
			'#type' => 'select',
			'#options' => ["w", "t", "f"],
			'#prefix' => "<div id=\"$pathSelectionId\">",
			'#suffix' => '</div>',
			'#ajax' => array(
				'callback' => '::wtf',
				'wrapper' => $pathDisplayId,
				'event' => 'change',
				#'effect' => 'slide',
			),
		);

		$selection[$pathDisplayId] = array(
			'#prefix' => "<div id=\"$pathDisplayId\">",
			'#suffix' => '</div>',
		);


   		if (empty($form_state->getValue($pathSelectionId))) {
			return $selection;
		}

		$selectedGroup = $form_state->getValue($pathSelectionId);


		return $selection;

	}

	public static function buildPathSelection(array $form, FormStateInterface $form_state){

		$triggeringElement = $form_state->getTriggeringElement();
		$groups = $triggeringElement['#groups'];
		$id = $triggeringElement['#id'];
		$pbs = $triggeringElement['#pbs'];
		$selectedGroupIndex = $triggeringElement['#value'];
		$pathSelectionId = "path_selection_" . $id;

		if($selectedGroupIndex === "0"){
			return array(
				'#prefix' => "<div id=\"$pathSelectionId\">",
				'#suffix' => '</div>',
			);
		}
		// decrement because of default option
		$selectedGroupIndex--;


		$selectedGroup = $groups[$selectedGroupIndex];


		$paths = array();

		foreach ($pbs as $pb) {
			$paths = array_merge($paths, $pb->getAllPathsForGroupId($selectedGroup->getID(), TRUE));
		}

		foreach ($paths as $pathId => $path) {
			$pathNames[$pathId] = $path->label();
			$pathArrays[$pathId] = $path->getPathArray();
		}


		// return array(
		// 	'#type' => 'markup',
		// 	'#markup' => serialize($pathArrays),
		//  	'#prefix' => "<div id=\"$pathSelectionId\">",
		//  	'#suffix' => '</div>',
		// );

		$pathDisplayId = "path_display_" . $id;
      	$pathSelection = array(
			'#type' => 'select',
			'#title' => t('Select the Path which you want to merge data for duplicates.'),
			'#default_value' => 0,
			'#options' => array_merge(array("0" => 'Please select.'), $pathNames),
			'#paths' => $paths,
			'#id' => $id, 
			'#ajax' => array(
				'callback' => '::wtf',
				'wrapper' => $pathDisplayId,
				'event' => 'change',
				#'effect' => 'slide',
			),
		);

		$pathDisplay = array(
			'#markup' => "HUH",
			'#prefix' => "<div id=\"$pathDisplayId\">",
			'#suffix' => '</div>',
		);

		$form = array(
			'#prefix' => "<div id=\"$pathSelectionId\">",
			'#suffix' => '</div>',
		);
		$form['wtf'] = $pathSelection;
		$form['ftw'] = $pathDisplay;

		$response = new AjaxResponse();
		$response->addCommand(new ReplaceCommand('#'.$pathSelectionId, $pathSelection));
		return $response;
		return $form;
	}

	public static function wtf(array $form, FormStateInterface $form_state){
		\Drupal::logger("asda")->notice("asdas");
	}

	public static function huh(array $form, FormStateInterface $form_state){
		\Drupal::logger("asda")->notice("asdas");
		$triggeringElement = $form_state->getTriggeringElement();

		$paths = $triggeringElement['#paths'];
		$selectedPathIndex = $triggeringElement['#value'];
		$id = $triggeringElement['#id'];
		$path = $paths[$selectedPathIndex];

		$pathDisplayId = "path_display_" . $id;

		return array(
			'#markup' => serialize($path->getPathArray()),
			'#prefix' => "<div id=\"$pathDisplayId\">",
			'#suffix' => '</div>',
		);
	}



	public function validateForm(array &$form, FormStateInterface $form_state) {
		#   dpm('hello1');
	}


	public function submitForm(array &$form, FormStateInterface $form_state) {

	}

}
