<?php
/*
 * Copyright 2008 LightFrame Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

require_once('tags.php');
require_once('filters.php');

class Template {
	private $context;
	private $html;
	private $template;
	private $templateNodes;
	private $templateFile;
	private $builtin;
	private $settingsChecked;

	/**
	 * Template constructor.
	 *
	 * @param [optional]mixed $template
	 * @param [optional]array $context
	 * @param [optional]bool $builtin search the template from built-in directories
	 */
	function __construct($template = null, $context=null, $builtin=false) {
		$this->context = $context;
		$this->html = '';
		$this->template = '';
		$this->templateNodes = array();
		$this->templateFile = '';
		$this->builtin = $builtin;
		$this->settingsChecked = false;

		if ($this->builtin || (strlen($template) < 100 && strtolower(substr($template, -5)) === '.html')) {
			$this->templateFile = $template;
		}
		elseif (is_array($template)) {
			$this->templateNodes = $template;
		}
		elseif ($template !== null) {
			// there is no filename, we can skip the rest
			$this->template = $template;
			return;
		}
		else {
			trigger_error('invalid template argument');
		}

		// sanitize the template filename
		if (strpos($this->templateFile, '..') !== false) {
			trigger_error('Template filename cannot contain ".."');
		}


		if ($this->templateFile[0] === '/') {
			trigger_error('Template filename cannot start with "/"');
		}

		// the template is built in. Those templates know what they are doing
		if ($this->builtin) {
			if (($file = LF_TEMPLATES_PATH.'/built-in/'.$this->templateFile) && (is_readable($file))) {
				$this->templateFile = $file;
			}
			elseif (($file = LF_LIGHTFRAME_PATH.'/templates/'.$this->templateFile) && (is_readable($file))) {
				$this->templateFile = $file;
			}
			else {
				trigger_error('Built-in template \''.$this->templateFile.'\' not found. Check your installation!');
			}
		}

		// assume default template location - [templates]/[app]/[view].html
		elseif ($this->templateFile === '' && $this->template === '') {
			if (($file = LF_TEMPLATES_PATH.'/'.$GLOBALS['view'].'.html') && (is_readable($file))) {
				$this->templateFile = $file;
			}
			else {
				trigger_error('No template file defined and default template not found in view '.$GLOBALS['view']);
			}
		}

		// A defined direct path - [templates]/[arg]
		elseif (($file = LF_TEMPLATES_PATH.'/'.$this->templateFile) && (is_readable($file))) {
			$this->templateFile = $file;
		}

		// Assumed to be under the app's template path - [templates]/[app]/[arg]
		elseif (($file = LF_TEMPLATES_PATH.'/'.$GLOBALS['app'].'/'.$this->templateFile) && (is_readable($file))) {
			$this->templateFile = $file;
		}

		else {
			trigger_error('Template \''.$this->templateFile.'\' in view \''.$GLOBALS['view'].'\' could not be found');
		}
	}

	/**
	 * Extend the current template if needed
	 *
	 * Checks the first line of the TOM, and if the template needs extending it
	 * scans (top-down) the extending template for block tags and when found,
	 * it scans the current template for matching tags, and replaces the block
	 * contents in the extending templates with the contents in the current
	 * template. If the current template doesn't have a matching block, the contents
	 * in the extending template is used.
	 *
	 */
	private function extend() {
		$template = $this->templateNodes;
		$absolutePath = false;
		$parentTemplate = null;

		// nothing to extend
		if (count($template) < 1 || strpos($template[0], '{% extends ') !== 0) {
			return;
		}

		preg_match('!{% extends "([^"]+)"!', array_shift($template), $matches);
		$file = $matches[1];

		if ($file[0] === '/') {
			$absolutePath = true;
			$file = substr($file, 1);
		}

		if ($absolutePath) {
			if (is_readable(LF_TEMPLATES_PATH.'/'.$file) && is_file(LF_TEMPLATES_PATH.'/'.$file)) {
				 // Is it a file in the user's files? This also overrides built-in
				 // templates and is by design
				$parentTemplate = LF_TEMPLATES_PATH.'/'.$file;
			}
			else {
				$parentTemplate = LF_LIGHTFRAME_PATH.'/templates/'.$file;
			}
		}
		else {
			if (strpos($this->templateFile, LF_TEMPLATES_PATH) === 0 ||
					strpos($this->templateFile, LF_LIGHTFRAME_PATH.'/templates') === 0) {
				$parentTemplate = dirname($this->templateFile).'/'.$file;
			}
			else {
				die('abnormal template location error. If you see this, '.
					'please report it and how you achieved it to the '.
					'LightFrame community.');
			}
		}

		if (!is_file($parentTemplate) || !is_readable($parentTemplate)) {
			trigger_error('invalid template file "'.$file.'"');
		}

		$parentTemplate = new Template(file_get_contents($parentTemplate));
		$parentTemplate = $parentTemplate->getNodes();
		$resultTemplate = array();
		$parentSize = count($parentTemplate);
		$currentSize = count($template);

		/*
		 * Scan the template for verbatim-tags. block-tags inside these need to be
		 * escaped.
		 */

		$escapeMap = array();
		$verbatimDepth = 0;   
		foreach ($template as $row => $node) {
			if ($node === '{% verbatim %}') {
				$verbatimDepth++;
			} elseif ($node === '{% endverbatim %}') {
				$verbatimDepth--;
			}
			
			elseif ($verbatimDepth > 0
					&& (strpos($node, '{% block ') === 0
						|| strpos($node, '{% endblock ') === 0)) {
				$uniqid = '[['.uniqid(md5(time()), true).']]';
				$escapeMap[$uniqid] = $node;
				$template[$row] = $uniqid;
			}
		}

		/*
		 * Seek the parent template for blocks and their names. Replace the block
		 * in the parent template with the corresponding block from the current
		 * template. If no such block is found in the current template, remove the
		 * tags in the parent tags, and use the default content.
		 */
		for ($i = 0; $i < $parentSize; $i++) {
			$pNode = $parentTemplate[$i];

			if (strpos($pNode, '{% block ') === false) {
				$resultTemplate[] = $pNode;
			}

			else {
				preg_match('!{% block (.+) %}!', $pNode, $matches); // no syntax checking here
				$block = $matches[1];
				$found = false;
				//$tSize = count($template);

				for ($j=0; $j < $currentSize; $j++) {
					$cNode = $template[$j];

					if ($cNode !== '{% block '.$block.' %}') {
						continue;
					}

					$found = true;

					// copy the contents of the current template in the result template until {% endblock %}
					while($j < $currentSize-1 && strpos($template[$j+1], '{% endblock ') !== 0) {
						$resultTemplate[] = $template[++$j];
					}

					// If we hit the end of the file before finding an appropriate endblock
					if ($j+1 === $currentSize) {
						trigger_error('An uneven count of blocks/endblocks!');
					}

					// scroll the parent template to the next endblock
					do {
						$i++;
					}
					while (strpos($parentTemplate[$i], '{% endblock ') !== 0);
				}

				// the block was not found in the current template, copy the parent template's contents
				if (!$found) {
					while ($i<$parentSize-1 && strpos($parentTemplate[$i+1], '{% endblock ') === false) {
						$resultTemplate[] = $parentTemplate[++$i];
					}
					$i++;
				}
			}
		}

		/*
		 * Replace back the blocks that we hid
		 */
		while (count($escapeMap) > 0) {
			$originalString = current($escapeMap);
			$hash = key($escapeMap);
			unset($escapeMap[$hash]);

			foreach ($resultTemplate as $row => $node) {
				if ($node === $hash) {
					$resultTemplate[$row] = $originalString;
				}
			}
		}

		$this->templateNodes = $resultTemplate;
	}

	/**
	 * Break the template file into elements of text, tags, variables and comments
	 * and extend the template afterwards.
	 *
	 */
	private function renderNodes() {
		if (count($this->templateNodes) === 0) {
			if (!$this->template) {
				$this->template = file_get_contents($this->templateFile);
			}

			$this->templateNodes = preg_split('/({% .+ %}|{{ .+ }}|{#.*#})/U', $this->template, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
			$this->extend();
		}
	}

	/**
	 * Evaluate the tags (and the variables within)
	 *
	 */
	private function renderHTML() {
		if ($this->html === '') {
			$this->renderNodes();
			$this->html = (string) new TOM($this->templateNodes, $this->context);
		}
	}

	/**
	 * Return the contents of the template file
	 *
	 * @return string
	 */
	protected function getTemplate() {
		$this->renderNodes();
		return $this->template;
	}

	/**
	 * Return the broken-apart and evaluated template elements
	 *
	 * @return array
	 */
	protected function getNodes() {
		$this->renderNodes();
		return $this->templateNodes;
	}

	/**
	 * Make the template into a HTML document
	 *
	 * @return string
	 */
	function compile() {
		$this->renderHTML();
		return $this->html;
	}
}

/**
 * Template Object Model
 *
 * This acts as a basis for Tags
 *
 */
class TOM {
	protected $nodes;
	protected $result;
	protected $context;
	protected $args;
	protected $arg;

	/**
	 * Construct the TOM and parse arguments, if they exist. Finally evaluate
	 * the TOM nodes
	 *
	 * @param array $nodes
	 * @param array $context
	 * @param string $args
	 */
	final function __construct($nodes, $context, $args='') {
		$this->nodes = $nodes;
		$this->result = '';
		$this->context = $context;
		$this->arg = $args;

		$arguments = array();

		// parse arguments into an array
		if ($args !== '') {

			/*
			 * Replace each quoted (thus possibly spaced) argument with a unique
			 * identifier (microtime + some id), remember the translations, and finally
			 * apply them back to the actual values.
			 */

			preg_match_all('/ ?(.+:)?(?<val>".+")/U', $args, $matches);

			$dict = array();
			foreach ($matches['val'] as $value) {
				$dict['"'.microtime(true).uniqid(true).'"'] = $value;
			}

			$args = str_replace($dict, array_flip($dict), $args);

			foreach (explode(' ', trim($args)) as $i => $arg) {
				$temp = explode(':', $arg, 2);
				$argname = $temp[0];
				$argvalue = isset($temp[1]) ? $temp[1] : true;

				if ($argvalue !== true) {
					$arguments[$argname] = isset($dict[$argvalue]) ? $dict[$argvalue] : $argvalue;
					$arguments[$i] = $arguments[$argname];
				}
				else {
					$arguments[$i] = isset($dict[$argname]) ? $dict[$argname] : $argname;
				}
			}
		}

		$this->args = $arguments;

		$this->evaluate();
	}

	/**
	 * return $this->result as string
	 *
	 * @return string
	 */
	final function __toString() {
		return (string)$this->result;
	}

	/**
	 * Process one step of nodes
	 *
	 * One step can include a plaintext string (returns it as-is), a comment
	 * (returns blank), variable (evaluates the variable and filters) or
	 * a tag (does one of two things, see TOM::evaluateTag()).
	 *
	 * @return string
	 */
	final protected function step() {
		// there are no nodes left, return false
		if (count($this->nodes) === 0 || is_string($this->nodes)) {
			return false;
		}

		$node = array_shift($this->nodes);

		/*
		 * as false and '' equal loosely (==) the stepping process requires a '!== false'.
		 * TODO: Make a easy-to-use wrapper so that comparison errors are avoided in
		 * a while-loop.
		 */
		// the node is a comment, return blank
		if (strpos($node, '{#') !== false) {
			return '';
		}

		// the node is a variable, evaluate it
		elseif (strpos($node, '{{ ') !== false) {
			$evaluated = $this->evaluateVariable(substr($node, 3, -3));

			// make sure that objects' __toString() methods are also html-escaped
			if (is_object($evaluated)) {
				$evaluated = htmlentities($evaluated, ENT_QUOTES);
			}

			return $evaluated;
		}

		// the node is a tag
		elseif (strpos($node, '{% ') !== false) {
			$return = $this->evaluateTag($node);
			return $return;
		}
		else {
			return $node;
		}
	}

	/**
	 * Seek for an ending pair. If there is none, it's a
	 * simple tag, and is processed along with the arguments. Otherwise it's
	 * a block tag, and everything between beginning and end tags is sent to the
	 * tag for processing
	 *
	 * @param string $node
	 * @return string
	 */
	final protected function evaluateTag($node) {
		preg_match('!^{% (?P<tag>.+) (?P<args>.*)%}$!U', $node, $matches);
		$tag = $matches['tag'];
		$args = $matches['args'];
		$endtag = '{% end'.$tag.' %}';

		$tempNodes = array();
		$argNodes = array();
		//$arguments = array();
		$nests = 0;

		// try to seek the end pair.
		foreach ($this->nodes as $n) {
			if (strpos($n, '{% '.$tag) === 0) {
				$nests++;
			}
			elseif ($n === $endtag && $nests > 0) {
				$nests--;
			}
			elseif ($n === $endtag && $nests === 0) {
				$argNodes = $tempNodes;
				break;
			}
			$tempNodes[] = $n;
		}

		$anCount = count($argNodes);

		// No endtag found, it's a simple tag
		if ($anCount === 0) {
			$argNodes = $node;
		}

		// it's a block - scroll over it
		else {
			for ($i=0; $i <= $anCount; $i++) {
				array_shift($this->nodes);
			}
		}


		$class = ucfirst($tag).'Tag';
		if (!class_exists($class, false)) {
			trigger_error($tag.' is not a valid tag');
		}

		return (string) new $class($argNodes, &$this->context, $args);
	}

	/**
	 * Evaluate a variable into a string
	 *
	 * Separates filters from the variable itself. If the variable contains periods,
	 * it's considered 'multipart', meaning it is either an array or object.
	 *
	 * format: partA.partB|filter1:arg1|filter2
	 *
	 * @param string $var
	 * @return string
	 */
	final protected function evaluateVariable($var) {
		$var = explode('|', $var);
		$result = array_shift($var);
		$filters = $var;
		$e = null;

		// it's a numeral
		if (is_numeric($result)) {
			$result = (int)$result;
		}

		// it's a quoted string
		elseif (($result[0] === '\'' && $result[(strlen($result)-1)] === '\'') ||
		        ($result[0] === '"'  && $result[(strlen($result)-1)] === '"')) {
			$result = substr($result, 1, -1);
		}

		// it's an index within the context
		elseif (isset($this->context[$result])) {
			$result = $this->context[$result];
		}

		elseif ($result === '/') {
			$result = $GLOBALS['env']['site_path'];
		}

		// multipart
		elseif (count($parts = explode('.', $result)) > 1) {
			$result = array_shift($parts);
			// hard code some superglobals
			if ($result === 'GET') {
				$result = $_GET;
			}
			elseif ($result === 'POST') {
				$result = $_POST;
			}
			elseif ($result === 'SESSION') {
				$result = $_SESSION;
			}
			elseif ($result === 'ENV') {
				$result = $GLOBALS['env'];
			}
			elseif (isset($this->context[$result])) {
				$result = $this->context[$result];
			}
			else {
				$result = null;
			}


			foreach ($parts as $part) {

				// It's fair to assume that if a part is strictly numbers, it's an integer
				if (is_numeric($part)) {
					$part = (int)$part;
				}

				// if it's a field, replace it immediately with the real value
				if ($result instanceof Field) {
					$result = $result->get();
				}

				if (is_array($result) || ($result instanceof ArrayAccess && isset($result[$part])) ) {
					if (isset($result[$part])) {
						$result = $result[$part];
					} else {
						$result = null;
						break;
					}
				}
				elseif (is_object($result)) {
					if (isset($result->$part)) {
						$result = $result->$part;

					} elseif (method_exists($result, $part)
							|| method_exists($result, '__call')) {

						try {
							$result = $result->$part();
						} catch (BadMethodCallException $e) {
							// __call() didn't support this method
							$result = null;
							break;
						} catch (InvalidArgumentException $e) {
							// __call() wanted some other arguments
							$result = null;
							break;
						}

					} else {
						$result = null;
						break;
					}
				}
				else {
					$result = null;
					break; // no hit, break the loop
				}
			}
		}
		else {
			$result = null;
		}

		if (is_string($result)) {
			$result = htmlspecialchars((string)$result, ENT_QUOTES);
		}

		if ($filters) {
			foreach ($filters as $filter) {
				$data = explode(':',$filter,2);
				$filter = $data[0];
				$value = null;

				if (isset($data[1])) {
					$value = $data[1];
				}
				else {
					$value = false;
				}

				$function = 'lf_filter_'.strtolower($filter);
				if (!function_exists($function)) {
					trigger_error('"'.$filter.'" is not a valid filter');
				}
				$result = $function((string)$result, $value);
			}
		}

		return $result;
	}

	/**
	 * Marks that the Tag expects nodes (a block tag) and triggers an error, if
	 * it receives a single tag.
	 */
	final protected function expectsNodes() {
		if (!is_array($this->nodes)) {
			trigger_error(get_class($this).' expects nodes as an argument');
		}
	}

	/**
	 * Marks that the Tag expects a tag (a simple tag) and triggers an error, if
	 * it receives a block of nodes.
	 */
	final protected function expectsTag() {
		if (!is_string($this->nodes)) {
			trigger_error(get_class($this).' expects a single tag as an argument');
		}
	}

	/**
	 * The default evaluation method
	 *
	 * scroll over the nodes and evaluate all variables and tags. Many tags
	 * take usage of this by calling parent::evaluate() and then manipulates the
	 * result in $this->result.
	 *
	 * The result of the tag must be stored in $this->result
	 *
	 */
	public function evaluate() {
		while (($step = $this->step()) !== false) {
			$this->result .= $step;
		}
	}
}