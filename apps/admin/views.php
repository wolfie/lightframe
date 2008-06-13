<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

function crud($args) {
	if (!isset($args['app'])) {
		trigger_error("CRUD needs argument 'app'.");
	}
	if (!isset($args['context'][1]) || 
			!isset($args['context'][2])) {
		trigger_error('The CRUD url should look like "^(keyword/)(.*)/$"');
	}

	$crudURL = $args['context'][1];
	
	// this SHOULD be done in a urls.php, but we don't have the luxury (at this time)
	//
	// (crudURL/)(model)/(verb)/(subject)/
	// e.g. www.domain.com/CRUD/Person/delete/31/
	
	$data    = explode('/', $args['context'][2],3);
	$model   = $data[0];
	$verb    = isset($data[1]) ? $data[1] : false;
	$subject = isset($data[2]) ? $data[2] : false;
	
	$filename = LF_APPS_PATH.$args['app'].'/models.php';
	
	if (!is_readable($filename) || 
			!is_file($filename)) {
		trigger_error("App {$args['app']} doesn't have a readable models.php file");
	}

	require_once($filename);
	
	$models = array();
	$modelnames = array();
	
	foreach (file($filename) as $line) {
		if (preg_match("/^class (?P<class>.+?) extends Model/i", $line, $matches)) {
			$models[$matches['class']] = new $matches['class']();
			$modelnames[] = $matches['class'];
		}
	}
	
	$args['context']['models'] = $models;
	$args['context']['modelnames'] = $modelnames;
	$args['context']['url'] = $crudURL;
	$args['context']['fields'] = isset($models[$model]) ? $models[$model]->asArray() : "";
	$args['context']['thismodel'] = $model;
	
	/*
	 * If no action is specified and a model is defined, list model entries
	 */
	 
	if (!$verb && !$subject) {
		if (isset($models[$model])) {
			$entries = new Entries(get_class($models[$model]));
		}
		else {
			$entries = null;
		}
		
		$args['context']['entries'] = $entries;
		
		if (in_array($model, $modelnames) || !$model) {
			return new Response($args['context'], 'admin/crud.html', true);
		}
		else {
			// No such model, redirect to main page
			$redirect = $GLOBALS['env']['site_path'].$args['context'][1];
			
			$response = new Response();
			$response->header->setStatus(HTTPHeaders::MOVED);
			$response->header->Location = $redirect;
			$response->add("");
			
			return $response;
		}
	}
	
	/*
	 * Delete entry
	 */
	elseif ($verb == "del") {
		// The subject needs to be the object's id
		if (is_numeric($subject)) {
			$m = new $model();
			$m->id = (int)$subject;
			$m->delete();
		}
		
		$response = new Response();
		$response->header->setStatus(HTTPHeaders::MOVED);
		$response->header->Location = $GLOBALS['env']['site_path'].$args['context'][1].$model.'/';
		$response->add("");
		return $response;
	}
	
	/*
	 * Create entry
	 */
	elseif ($verb == "add") {
		$m = new $model();

		$args['context']['addform'] = _getCrudEditor($m);
		return new Response($args['context'], "admin/crud.html", true);
	}
	
	/*
	 * Update entry
	 */
	elseif ($verb == "edit") {
		if (is_numeric($subject)) {
			$m = new $model();
			$m->get((int)$subject);
			$args['context']['addform'] = _getCrudEditor($m);
			return new Response($args['context'], "admin/crud.html", true);
		}
		else {
			$response = new Response();
			$response->header->setStatus(HTTPHeaders::MOVED);
			$response->header->Location = $GLOBALS['env']['site_path'].$args['context'][1].$model.'/';
			$response->add("");
			return $response;
		}
	}

	/*
	 * Order entries
	 */
	
	elseif ($verb == 'sort') {
		list($order, $field) = explode('/', $subject, 2);

		if ($order === 'asc' ) {
			$orderString = $field;
		} elseif ($order === 'desc') {
			$orderString = '-'.$field;
		} else {
			throw new Exception("Sort order $order not allowed");
		}
		
		$entries = new Entries(get_class($models[$model]));
		$entries->orderBy($orderString);
		
		$args['context']['sortby']['order'] = $order;
		$args['context']['sortby']['field'] = $field;
		$args['context']['entries'] = $entries;

		return new Response($args['context'], 'admin/crud.html', true);
	}
	
	else {
		// No such model, redirect to main page
		$redirect = $GLOBALS['env']['site_path'].$args['context'][1];
		
		$response = new Response();
		$response->header->setStatus(HTTPHeaders::MOVED);
		$response->header->Location = $redirect;
		$response->add("");
		
		return $response;
	}
}

function _getFormElement($field) {
	if ($field instanceof IntField || $field instanceof CharField) {
		return "<input type=text>";
	}
	elseif ($field instanceof BoolField) {
		return "<input type=checkbox>";
	}
	else {
		trigger_error("Can't get HTML input form for ".get_class($field));
	}
}

function _getCrudEditor(Model $model) {
	$result = array();
	foreach ($model->asArray() as $key) {
		$result[] = $key.": ".$model->$key;
	}
	return $result;
}
?>