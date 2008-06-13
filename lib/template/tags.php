<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

class DummytagTag extends TOM {
	function evaluate() {
		$this->expectsTag();
		$this->result = '{dummytag}';
	}
}

class DummyblockTag extends TOM {
	function evaluate() {
		$this->result = '{dummyblock}';
		parent::evaluate();
	}
}

class CommentTag extends TOM {
	function evaluate() {
		$this->expectsNodes();
	}
}

class LowercaseTag extends TOM {
	function evaluate() {
		$this->expectsNodes();
		parent::evaluate();
		$this->result = strtolower($this->result);
	}
}

class UppercaseTag extends TOM {
	function evaluate() {
		$this->expectsNodes();
		parent::evaluate();
		$this->result = strtoupper($this->result);
	}
}

class TransformTag extends TOM {
	function evaluate() {
		$this->expectsNodes();
		parent::evaluate();
		switch ($this->args[0]) {
			case 'spacesunderscores': $this->result = strtr($this->result, ' ', '_'); break;
			default: trigger_error('Unknown argument: '.$this->args[0]); break;
		}
	}
}

class IfTag extends TOM {
	protected $found = false;
	
	function evaluate() {
		$eval = null;
		$var = $this->evaluateVariable(current($this->args));
		$a = &$this->args;
		
		switch (key($this->args)) {
			case 'istrue': 
				$eval = ($var === true); 
				break;
				
			case 'isfalse': 
				$eval = ($var === false); 
				break;
				
			case 'equals': 
				$eval = ($var == $this->evaluateVariable($a['to'])); 
				break;
				
			case 'notequals':
				$eval = ($var != $this->evaluateVariable($a['to']));
				break;
				
			case 'isgreater': 
				if (isset($a[2]) && $a[2] == 'orequals') {
					$eval = ($var >= $this->evaluateVariable($a['than']));
				}
				else {
					$eval = ($var > $this->evaluateVariable($a['than']));
				}
				break;
				
			case 'isless': 
				if (isset($a[2]) && $a[2] == 'orequals') {
					$eval = ($var <= $this->evaluateVariable($a['than']));
				}
				else {
					$eval = ($var < $this->evaluateVariable($a['than']));
				}
				break;
				
			case 'exists':
				$eval = ((string)$var != ''); 
				break;
				
			case 'empty':
				if (is_array($var)) {
					$eval = (count($var) == 0); 
				} elseif (is_object($var)) {
					if (method_exists($var,"count")) {
						$eval = ($var->count() == 0);
					} else {
						trigger_error('object '.get_class($var).' does not have a \'count\' method');
					}
				} else {
					trigger_error('variable '.$a.' is not countable');
				}
				break;
				
			case 'notempty':
				if (is_array($var)) {
					$eval = (count($var) != 0); 
				} elseif (is_object($var)) {
					if (method_exists($var,"count")) {
						$eval = ($var->count() != 0);
					} else {
						trigger_error('object '.get_class($var).' does not have a \'count\' method');
					}
				} else {
					trigger_error('variable '.$a.' is not countable');
				}
				break;
				
			
			default: trigger_error('invalid comparison method for if clause');
		}
		
		// the evaluation was true, capture the first block.
		if ($eval === true) {
			while (($step = $this->step()) !== false) {
				if ($step === '{% %}') {
					break;
				}
				
				$this->result .= $step;
			}
		}
		
		// the evaluation was false, seek out other blocks to evaluate
		else {
			$nested = 0;
			// scroll forwards, over the first block, discarding nodes as we go
			while (($node = array_shift($this->nodes)) !== null ) {
				// take into account nested ifs
				if (strpos($node, '{% if') !== false) {
					$nested++;
					continue;
				}
				elseif (strpos($node, '{% endif') !== false && $nested > 0) {
					$nested--;
					continue;
				}
				
				// elseif was encountered, take the rest of the nodes and pass them to a new if-tag
				elseif ($nested === 0 && strpos($node, '{% elseif') !== false) {
					$this->result = (string) new IfTag(&$this->nodes, &$this->context, substr($node, 10,-3));
					break;
				}
				// else was encountered, show everything until the end.
				elseif ($nested === 0 && $node === '{% else %}') {
					while (($step = $this->step()) !== false) {
						$this->result .= $step;
					}
					break;
				}
			}
		}
	}
}

class ElseTag extends TOM {
	function evaluate() {
		$this->result = '{% %}';
	}
}

class ElseifTag extends TOM {
	function evaluate() {
		$this->result = '{% %}';
	}
}

class ForeachTag extends TOM {
	function evaluate() {
		$this->expectsNodes();
		$f = &$this->context;
		
		list($for, $as) = each($this->args);
		$var = $this->evaluateVariable($for);
		
		if (!is_array($var) && !is_object($var)) {
			trigger_error('\''.$for.'\' is an invalid variable for foreach');
		}
		if (isset($f[$as])) {
			trigger_error('\''.$as.'\' exists already in context, can\'t overwrite');
		}
		
		$f['_foreach'][$as] = $var; // save the array
		$f[$as] = $this->reset($f['_foreach'][$as]);
		$nodes = $this->nodes; // backup the nodes
		$loops = count($var);
		
		for ($i=0; $i<$loops; $i++) {
			$this->result .= parent::evaluate();
			$this->nodes = $nodes; // refill the exhausted nodes
			$f[$as] = $this->next($f['_foreach'][$as]);
		}
		
		 // to preserve memory
		unset($f[$as], $f['_foreach'][$as]);
		if (count($f['_foreach']) === 0) {
			unset($f['_foreach']);
		}
	}
	
	function reset(&$var) {
		if (is_object($var)) {
			if ($var->count() === 0) {
				return null;
			}
			return $var->rewind();
		}
		else {
			return reset($var);
		}
	}
	
	function current(&$var) {
		if (is_object($var)) {
			if ($var->count() === 0) {
				return null;
			}
			return $var->current();
		}
		else {
			return current($var);
		}
	}
	
	function next(&$var) {
		if (is_object($var)) {
			return $var->next();
		}
		else {
			return next($var);
		}
	}
}

class DebugTag extends TOM {
	function evaluate() {
		foreach ($this->args as $arg) {
			echo $arg.": \n";
			var_dump($this->evaluateVariable($arg));
		}
		die();
	}
}
?>