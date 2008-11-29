<?php
/**
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

/**
 * A superclass for all LightFrame's exceptions
 */
class LightFrameException extends Exception {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg, $code);
	}
}

class EmptyResultException extends LightFrameException {
	function __construct($model=null, $code = 0) {
		$msg = null;
		
		if ($model === null) {
			$msg = 'The result was empty';
		}
		else {
			$msg = 'The result of '.$model.' was empty';
		}
		parent::__construct($msg, $code);
	}
}

class InvalidFormatException extends LightFrameException {
	function __construct($data=null, $code = 0) {
		$msg = null;

		if ($data === null) {
			$msg = 'The value was invalid';
		}
		else {
			$msg = 'The value "'.$data[0].'" ('.gettype($data[0]).') was invalid for '.$data[1];
		}
		
		parent::__construct($msg, $code);
	}
}

class NullNotAllowedException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		if (!$msg) {
			$msg = 'Field tried to save null even if it is not allowed';
		}
		parent::__construct($msg,$code);
	}
}

class FieldNotFoundException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg,$code);
	}
}

class UnsupportedRDBMSException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg,$code);
	}
}

class DBConnectionException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg,$code);
	}
}

class QueryErrorException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg,$code);
	}
}

class TemplateNotFoundException extends LightFrameException {
	function __construct($msg=null, $code=0) {
		parent::__construct($msg,$code);
	}
}

class ReadOnlyException extends LightFrameException {}