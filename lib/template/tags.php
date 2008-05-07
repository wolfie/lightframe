<?php

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
			
			default: die('invalid comparison method');
		}
		
		if ($eval === true) {
			while (($step = $this->step()) !== false) {
				if ($step === '{% %}') {
					break;
				}
				
				$this->result .= $step;
			}
		}
		else {
			while (($node = array_shift($this->nodes)) !== null ) {
				if (strpos($node, '{% elseif') !== false) {
					$this->result = (string) new IfTag(&$this->nodes, &$this->context, substr($node, 10,-3));
					break;
				}
				elseif ($node === '{% else %}') {
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
			trigger_error('\''.$as.'\' exists already, can\'t overwrite');
		}
		
		$f['_foreach'][$as] = $var; // save the array
		$f[$as] = $this->current($var);
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
?>