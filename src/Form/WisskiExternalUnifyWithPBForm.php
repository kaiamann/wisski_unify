<?php

/**
 * @file
 *
 */

namespace Drupal\wisski_unify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\State;
use Drupal\wisski_unify\Query\StaticQueries;
use Drupal\wisski_unify\Render\MarkupGenerator;
use Drupal\wisski_unify\Utils;
use Symfony\Component\DependencyInjection\ContainerInterface;

const GROUP_SELECTION = "group_selection";
const PATH_SELECTION = "path_selection";
const PATH_DISPLAY = "path_display";

// path to the rest API
const REST_PATH = "/wisski/unify/rest?_format=json";
// name of the credentials config
const CONFIG = 'wisski_unify.credentials';
const credentials_key = 'wisski.unify.credentials';

/**
 * Overview form for ontology handling
 *
 * @return form
 *   Form for the Duplication detection
 * @author Kai Amann
 */
class WisskiExternalUnifyWithPBForm extends FormBase {


	/**
	 * The Drupal State API
	 * 
	 * @var Drupal\Core\State
	 */
	protected $state;

	/**
	 * The entity type manager.
	 *
	 * @var \Drupal\Core\Entity\EntityTypeManagerInterface
	 */
	protected $entityTypeManager;

	protected $wisskiUrl;
	protected $credentials;

	/**
	 * The constructor.
	 */
	public function __construct(
		State $state,
		EntityTypeManagerInterface $entity_type_manager,
	) {
		$this->state = $state;
		$this->entityTypeManager = $entity_type_manager;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function create(ContainerInterface $container) {
		return new static(
			$container->get('state'),
			$container->get('entity_type.manager'),
		);
	}

	/**
	 * {@inheritdoc}.
	 * The Id of every WissKI form is the name of the form class
	 */
	public function getFormId() {
		return self::class;
	}


	public function getExternalGroups() {
		$headers = array(
			'Application' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode(implode(':', $this->credentials))
		);

		$restUrl = $this->wisskiUrl . REST_PATH;
		$response = Utils::get($restUrl, $headers);
		// TODO: add error messages
		if ($response['code'] != 200) {
			dpm($response);
			return [];
		}

		return $response['body'];
	}

	public function getPathsForExternalGroupId($groupId) {
		$headers = array(
			'Application' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode(implode(':', $this->credentials))
		);

		$restUrl = $this->wisskiUrl . REST_PATH . "&group=$groupId";
		$response = Utils::get($restUrl, $headers);
		// TODO: add error messages
		if ($response['code'] != 200) {
			dpm($response);
			return [];
		}

		return $response['body'];
	}

	public function getExternalEntitiesForPathId($pathId) {
		$headers = array(
			'Application' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode(implode(':', $this->credentials))
		);
		$restUrl = $this->wisskiUrl . REST_PATH . "&path=$pathId";
		$response = Utils::get($restUrl, $headers);
		// TODO: add error messages
		if ($response['code'] != 200) {
			dpm($response);
			return [];
		}

		return $response['body'];
	}

	function getExternalEntity($uri) {
		$headers = array(
			'Application' => 'application/json',
			'Authorization' => 'Basic ' . base64_encode(implode(':', $this->credentials))
		);
		$restUrl = $this->wisskiUrl . REST_PATH . "&uri=$uri";

		$response = Utils::get($restUrl, $headers);
		// TODO: add error messages
		if ($response['code'] != 200) {
			dpm($response);
			return [];
		}
		return $response['body'];
	}




	static function buildHeader($context, $level) {
		// return [[['data' => new FormattableMarkup("<b>:context</b>", [':context' => $context]), 'colspan' => 3]]];
		// return [[['data' => new FormattableMarkup("<b>:context</b>", [':context' => $context]), 'colspan' => 3]]];
		$markup = self::buildMarkup($context, $level);
		$markup['#wrapper_attributes'] = ['colspan' => 3];
		$markup['#prefix'] = "<b>";
		$markup['#suffix'] = "</b>";
		return [[$markup]];
	}

	static function buildMarkup($html, $level, $class = null) {
		$markup = array(
			'#markup' => $html,
			// TODO: make horizontal spacing work somehow
			// '#attributes' => array(
			// 	'style' => ["margin-left: {$level}em"]
			// ),
		);
		if($class){
			$markup['#prefix'] = "<div class=\"$class\">";
			$markup['#suffix'] = "</div>";
		}
		return $markup;
	}

	static function buildRow($local, $external, $context, $level) {
		if (!$local || !$external) {
			$class = "key_diff";
			$local = $local ?? "";
			$external = $external ?? "";
		}

		$class = $class ?? ($local == $external ? "match" : "conflict");
		// don't style if no other value was found
		$localValueMarkup = self::buildMarkup($local, $level, $class);
		$externalValueMarkup = self::buildMarkup($external, $level, $class);
		$contextMarkup = self::buildMarkup($context, $level);
		return [[$localValueMarkup, $contextMarkup, $externalValueMarkup]];
		// return [[$localValueMarkup, $contextMarkup, $externalValueMarkup]];
	}


	/**
	 * It takes two arrays and returns a table of the differences between them
	 * 
	 * @param local The local data to compare
	 * @param external The external data source.
	 * 
	 */
	static function buildRows($local, $external, $context = "", $level = -1) {
		if (!is_array($local) && !is_array($external)) {
			return self::buildRow($local, $external, $context, $level);
		}
		if (!is_array($local) && is_array($external)) {
			$rows = [];
			foreach ($external as $key => $ev) {
				$rows = array_merge($rows, self::buildRows($local, $ev, $key, $level));
			}
			$header = self::buildHeader($context, $level+1);
			// dpm("array, value");
			return array_merge($header, $rows);
		}
		if (is_array($local) && !is_array($external)) {
			$rows = [];
			foreach ($local as $key => $lv) {
				$rows = array_merge($rows, self::buildRows($lv, $external, $key, $level));
			}
			$header = self::buildHeader($context, $level+1);
			// dpm("array, value");
			return array_merge($header, $rows);
		}

		$rows = [];
		$matches = [];
		$header = $context ? self::buildHeader($context, $level) : [];
		foreach ($local as $key => $value) {
			// item_list
			$externalValue = array_key_exists($key, $external) ? $external[$key] : null;
			if ($externalValue) {
				$matches[] = $key;
			}

			$newContext = $context ? $key . " ($context)" : $key;
			$rows = array_merge($rows, self::buildRows($value, $externalValue, $key, $level+1));
		}

		foreach ($external as $key => $externalValue) {
			if (in_array($key, $matches)) {
				continue;
			}
			// item_list

			$newContext = $context ? $key . " ($context)" : $key;
			$value = array_key_exists($key, $local) ? $local[$key] : null;
			$rows = array_merge($rows, self::buildRows($value, $externalValue, $key, $level+1));
		}

		// return array_values($rows);
		// $rows[] = [['data' => new FormattableMarkup("<b>:context</b>", [':context' => $key]), 'rowspan' => count($childRows)+1]];
		return array_merge($header, $rows);
	}





	protected function getAvailableWissKIs() {
		$wisskiUrls = array_keys($this->state->get(credentials_key));
		return array_combine($wisskiUrls, $wisskiUrls);
	}

	protected function getCredentials($url){
		return $this->state->get(credentials_key)[$url];
	}

	/**
	 * {@inheritdoc}.
	 * 
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		// attach library for styling
		$form['#attached']['library'][] = 'wisski_unify/unify-theme';

		// clear messenger backlog
		\Drupal::messenger()->deleteAll();

		// get session for selection
		$session = \Drupal::request()->getSession();

		// get available WissKIs from config tab
		$wisskis = $this->getAvailableWissKIs();

		// read WissKI URL from session
		$wisskiUrl = $session->get('wisski_url', 0);

		// build WissKI selection dropdown
		$form['wisski_selection'] = array(
			'#title' => 'Select the WissKI that you wish to query for duplicates.',
			'#type' => 'select',
			'#default_value' => $wisskiUrl,
			'#options' => ['0' => 'Please select'] + $wisskis,
			'#attributes' => array('onchange' => 'this.form.submit();'),
		);

		// hidden submit form
		// this enables submitting when the 
		// dropdown is changed
		$form['submit'] = array(
			'#type' => 'submit',
			'#attributes' => array(
				'style' => 'display: none;',
			),
		);


		if (!$wisskiUrl) {
			return $form;
		}

		$this->wisskiUrl = $wisskiUrl;
		$this->credentials = $this->getCredentials($wisskiUrl);

		$localPbs = $this->entityTypeManager->getStorage('wisski_pathbuilder')->loadMultiple();

		// build selection for local and external paths
		$selection = array(
			'#type' => 'table',
			'#header' => array(
				'Local',
				'External'
			),
		);
		$selection[0]['local'] = $this->buildSelection($form_state, $localPbs, 'local');
		$selection[0]['external'] = $this->buildSelection($form_state, [], 'external');

		// attach selection
		$form['selection'] = $selection;

		// read the selection from session
		$localPath = $session->get('local_' . PATH_SELECTION, 0);
		$externalPath = $session->get('external_' . PATH_SELECTION, 0);
		if ($localPath == 0 || $externalPath == 0) {
			return $form;
		}

		$alwaysLoad = false;
		// TODO: figure out why some paths return nothing e.g. Sammlungsobjekt->neue Sammlung
		// TODO: add a button to make this "cache" clearable
		// cacheing the data from the remote WissKI to avoid unneccessary traffic
		$cacheData = $this->state->get('wisski.unify.cache', []);
		if (array_key_exists($wisskiUrl, $cacheData) 
			&& array_key_exists($externalPath, $cacheData[$wisskiUrl]) 
			&& !empty($cacheData[$wisskiUrl][$externalPath])
			&& !$alwaysLoad) {
			\Drupal::messenger()->addMessage("Loading from cache");
			$externalValueMap = $cacheData[$wisskiUrl][$externalPath];
		} else {
			\Drupal::messenger()->addMessage("Loading from remote");
			$externalValueMap = $this->getExternalEntitiesForPathId($externalPath);
			$cacheData[$wisskiUrl][$externalPath] = $externalValueMap;
			$this->state->set('wisski.unify.cache', $cacheData);
		}
		$externalValues = array_keys($externalValueMap);

		// do nothing if nothing came from the remote
		// TODO: display notice to user
		if (empty($externalValues)) {
			\Drupal::messenger()->addStatus("Did not find any matching entities");
			return $form;
		}

		// get bundleId and fieldId of the selected path
		$pbPath = Utils::getPbPathsForPbs($localPbs)[$localPath];
		$bundleId = $pbPath['bundle'];
		$fieldId = $pbPath['field'];


		// get all local entities that have matching values
		// in the selected path
		$query = \Drupal::entityQuery('wisski_individual');
		$query->condition('bundle', [$bundleId]);
		$query->condition($fieldId, $externalValues, "IN");
		$query->pager(1);
		$eids = $query->execute();

		$numEntities = \Drupal::service('pager.manager')->getPager()->getTotalPages();
		\Drupal::messenger()->addMessage("Found $numEntities matches");

		// load entities from storage
		$entities = $this->entityTypeManager->getStorage('wisski_individual')->loadMultiple($eids);

		// attach pager
		$form['pager'] = array(
			'#type' => 'pager'
		);


		$matches = [];
		foreach ($entities as $entity) {
			$html = Utils::renderEntity($entity);
			$localData = Utils::HTMLToArray($html);

			// TODO: see if this uri retreival process runs into problems
			$localUri = $entity->wisski_uri->value;
			$value = $entity->$fieldId->value;

			// this should not happen but sanity check anyway
			if (!array_key_exists($value, $externalValueMap)) {
				continue;
			}

			$externalUris = $externalValueMap[$value];
			$externalData = array();
			foreach ($externalUris as $idx => $uri) {
				$externalData = $this->getExternalEntity($uri);
				
				$rows = $this->buildRows($localData, $externalData);
				$header = [t("Unify"), t("Local Value"), t("Field Name"), t("External Value")];
				$table = [
					'#type' => 'table',
					'#header' => $header,
					'#empty' => t('No users found'),
				];

				$checkbox = array(
					'#type' => 'checkbox', 
					'#wrapper_attributes' => array(
						'rowspan' => count($rows)+1
					),
				);
				$table['checkbox'] = [$checkbox];

				$matches[$idx] = array(
					'local_uri' => $localUri,
					'external_uri' => $uri,
				);

				foreach($rows as $row){
					$table[] = $row;
				}
				$form[$idx] = $table;
			}
		}


		if(empty($matches)){
			return $form;
		}

		$form['values'] = array(
			'#type' => 'value',
			'#value' => $matches,
		);

		$form['unify'] = array(
			'#type' => 'submit',
			'#value' => t("Unify"),
			'#id' => 'unify',
		);

		return $form;
	}



	public function buildSelection($form_state, $pbs, $id) {
		$session = \Drupal::request()->getSession();

		$groupNames = [];
		if ($id == "external") {
			$groupNames = $this->getExternalGroups();
		} else {
			$groupNames = Utils::getGroupsForPbs($pbs);
		}

		$selectedGroup = $session->get($id . "_" . GROUP_SELECTION, 0);
		$selection[GROUP_SELECTION] = array(
			'#type' => 'select',
			'#title' => t('Select the :id Bundle', [':id' => $id]),
			'#default_value' => $selectedGroup,
			// DO NOT USE array_merge HERE... 
			// this will result in wrong group_ids if groups_ids are purely numerical
			'#options' => [0 => 'Please select'] + $groupNames,
			'#attributes' => array('onchange' => 'this.form.submit();'),
		);

		if ($selectedGroup == 0) {
			return $selection;
		}

		// get paths for group
		$pathInfo = array();
		if ($id == "local") {
			$pathInfo = Utils::getPathsForGroupId($pbs, $selectedGroup);
		} else {
			$pathInfo = self::getPathsForExternalGroupId($selectedGroup);
		}

		$pathNames = $pathInfo['path_names'];
		$pathArrays = $pathInfo['path_arrays'];

		$selectedPath = $session->get($id . "_" . PATH_SELECTION, 0);

		// check if the selected path from the session actually fits for the bundle
		// this resets the path selection if another bundle is chosen
		if (!array_key_exists($selectedPath, $pathNames)) {
			$selectedPath = 0;
		}
		$selection[PATH_SELECTION] = array(
			'#type' => 'select',
			'#title' => t('Select the :id path', [':id' => $id]),
			'#default_value' => $selectedPath,
			'#options' => [0 => 'Please select'] + $pathNames,
			'#attributes' => array('onchange' => 'this.form.submit();'),
		);

		if ($selectedPath == 0) {
			return $selection;
		}

		// display path_array for selected path
		$pathArray = $pathArrays[$selectedPath];
		$selection[PATH_DISPLAY] = array(
			'#markup' => implode("<br>&#8594;", $pathArray),
		);
		return $selection;
	}

	public function submitForm(array &$form, FormStateInterface $form_state) {
		$session = \Drupal::request()->getSession();

		// store the user selection in the session

		// TODO: unset the path values when Bundle is changed
		$wisskiUrl = $form_state->getValue('wisski_selection', 0);
		$session->set('wisski_url', $wisskiUrl);

		$oldLocalGroup = $session->get('local_' . GROUP_SELECTION);
		$localGroup = $form_state->getValue(['selection', 0, "local", GROUP_SELECTION]) ?? 0;
		$session->set('local_' . GROUP_SELECTION, $localGroup);

		$oldExternalGroup = $session->get('external_' . GROUP_SELECTION);
		$externalGroup = $form_state->getValue(['selection', 0, "external", GROUP_SELECTION]) ?? 0;
		$session->set('external_' . GROUP_SELECTION, $externalGroup);

		// reset path if group is changed
		$localPath = $oldLocalGroup == $localGroup ? $form_state->getValue(['selection', 0, "local", PATH_SELECTION]) : 0;
		$session->set('local_' . PATH_SELECTION, $localPath);

		// reset path if group is changed
		$externalPath = $oldExternalGroup == $externalGroup ? $form_state->getValue(['selection', 0, "external", PATH_SELECTION]) : 0;
		$session->set('external_' . PATH_SELECTION, $externalPath);

		// check if the "Unify" button was pressed
		$triggeringElement = $form_state->getTriggeringElement();
		if($triggeringElement['#id'] !== 'unify'){
			return;
		}

		// actual unify
		$values = $form_state->getValues();
		foreach($values['values'] as $idx => $uris){
			// this should never happen, sanity check anyways
			if(!array_key_exists($idx, $values)){
				continue;
			}

			// check if the match should be unified
			$checked = $values[$idx]['checkbox'][0] === "1";
			if(!$checked){
				continue;
			}

			$localUri = $uris['local_uri'];
			$externalUri = $uris['external_uri'];

			// insert into triplestore
			$query = StaticQueries::insertLink($localUri, $externalUri);
			$query->update();
		}

		$form_state->setRedirect('wisski.wisski_unify.external.pb');
		// $form_state->setRebuild(false);
	}

	// public function validateForm(array &$form, FormStateInterface $form_state)
	// {
	// 	$wisskiUrl = $form_state->getValue('wisski_selection');

	// 	dpm($form_state->getValues());
	// 	if(!$wisskiUrl){
	// 		$form_state->setErrorByName('wisski_selection', "Please select a WissKI");
	// 	}
	// 	elseif(!array_key_exists('selection',$form_state->getValues())) {
	// 		return;
	// 	}

	// 	$localGroupSelection = ['selection', 0, "local", GROUP_SELECTION];
	// 	$localGroup = $form_state->getValue($localGroupSelection);
	// 	if(!$localGroup){
	// 		$form_state->setErrorByName(implode('][',$localGroupSelection), "Please select a local group");
	// 	}
	// 	else {
	// 		$localPathSelection = ['selection', 0, "local", PATH_SELECTION];
	// 		$localPath = $form_state->getValue($localPATH_SELECTION);
	// 		if(!$localPath){
	// 			$form_state->setErrorByName(implode('][',$localPATH_SELECTION), "Please select a local path");
	// 		}
	// 	}
	// 	$externalGroupSelection = ['selection', 0, "external", GROUP_SELECTION];
	// 	$externalGroup = $form_state->getValue(['selection', 0, "external", GROUP_SELECTION]);
	// 	if(!$externalGroup){
	// 		$form_state->setErrorByName(implode('][',$externalGroupSelection), "Please select an external group");
	// 	}
	// 	else {
	// 		$externalPathSelection = ['selection', 0, "external", PATH_SELECTION];
	// 		$externalPath = $form_state->getValue(['selection', 0, "external", PATH_SELECTION]);
	// 		if(!$externalPath){
	// 			$form_state->setErrorByName(implode('][',$externalPATH_SELECTION), "Please select an external path");
	// 		}
	// 	}
	// }


	public function resetForm(array $form, FormStateInterface &$form_state) {
		$form_state->setRedirect('wisski.wisski_unify.external.pb');
	}

	public static function findMatches($local, $external, $linkedEntities = []) {
		$nonExistentEntities = [];
		$matchUris = [];
		$matchHtml = [];

		// iterate over external return values
		foreach ($external as $value => $externalEntities) {
			if (!array_key_exists($value, $local)) {
				continue;
			}
			// iterate over the external Entiies for this return value
			foreach ($externalEntities as $externalUri => $externalHTML) {
				foreach ($local[$value] as $localUri => $localHTML) {
					// TODO: see if this is still needed
					// $id = AdapterHelper::getDrupalIdForUri($localUri);
					// WisskiCacheHelper::flushCallingBundle($id);

					if (!$localHTML) {
						$nonExistentEntities[$localUri] = $value;
						continue;
					}

					$match = array(
						'local' => $localUri,
						'external' => $externalUri
					);

					// filter already linked duplicates
					if (in_array($match, $linkedEntities)) {
						continue;
					}

					// extract Data from HTML
					$localData = Utils::HTMLToArray($localHTML);
					$externalData = Utils::HTMLToArray($externalHTML);

					// find matching/conflicting Data
					$m1 = Utils::compare($localData, $externalData);
					$m2 = Utils::compare($externalData, $localData);


					$matchUris[] = $match;
					$matchHtml[] = array(
						'local' => MarkupGenerator::generateHTML($localData, $m1),
						'external' => MarkupGenerator::generateHTML($externalData, $m2),
					);
				}
			}
		}
		return array(
			'uris' => $matchUris,
			'html' => $matchHtml,
		);
	}

	public static function buildComparisonTable($matches) {
		$matchUris = $matches['uris'];
		$matchHtml = $matches['html'];
		$comparisonTable = array(
			'#type' => 'table',
			'#header' => array(
				t("Link"),
				t("Rendered Local Entity"),
				t("Rendered Remote Entity")
			),
		);

		for ($i = -1; $i < count($matchUris); $i++) {
			// checkboxes
			$comparisonTable[$i]['checkbox'] = array(
				'#type' => 'checkbox',
			);

			// render Entities
			$comparisonTable[$i]['local'] = array(
				'#type' => 'markup',
				'#markup' => $matchHtml[$i]['local']
			);
			$comparisonTable[$i]['external'] = array(
				'#type' => 'markup',
				'#markup' => $matchHtml[$i]['external']
			);
		}

		$comparisonTable['conflict_uris'] = array(
			#'value' instead of 'hidden' type to avoid "Warning: Array to string conversion"
			'#type' => 'value',
			'#value' => $matchUris,
		);
		return $comparisonTable;
	}
}
