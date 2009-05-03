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

/**
 * The class that outputs stuff to the browser.
 * 
 * The Response object can take either a plain HTML body, or mash a context
 * together with a template. A context includes all variables a view wants to
 * pass on to a template. The template is defined relative to the user's
 * template path.
 * 
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 * 
 */
class Response {
	public $header;
	private $body = null;
	private $template = '';
	private $context = array();
	private $private = false;
	private $isRedirected = false;
	
	/**
	 * Construct
	 * 
	 * Faux overloading of the constructor. If the first argument is a string,
	 * it is treated as the document body. If the first argument is an array,
	 * it is treated as a template context. The second argument is always
	 * treated as a template name, but ignored if the first argument is a string.
	 *
	 * @param mixed[optional] $arg1 if string, body. If array, context
	 * @param string[optional] $arg2 template
	 */
	function __construct($arg1 = null, $arg2 = null, $builtin = false) {
		$this->header = new HTTPHeaders();
		
		// first argument is the body. Discard the second argument
		if (is_string($arg1)) {
			$this->body = $arg1;
		}
		
		// first argument is the context array
		elseif (is_array($arg1)) {
			$this->context = $arg1;
			
			// second argument is possibly the template
			if (is_string($arg2)) {
				$this->template = $arg2;
			}
		}
		
		$this->builtin = $builtin;
	}	
	
	/**
	 * Add to html body
	 *
	 * @param string $html
	 * @throws LightFrameException
	 */
	function add($html) {
		if ($this->isRedirected) {
			throw new LightFrameException("Can't modify body anymore, response is set to redirect");
		}
		$this->body .= $html;
	}
	
	/**
	 * Clear the HTML body
	 * 
	 * @throws LightFrameException
	 */
	function reset() {
		if ($this->isRedirected) {
			throw new LightFrameException("Can't modify body anymore, response is set to redirect");
		}
		$this->body = '';
	}
	
	/**
	 * Define or check template file
	 *
	 * @param string[optional] $templateFile which template to use
	 * @return string old template file
	 * @throws LightFrameException
	 */
	function template($templateFile = false) {
		
		$temp = $this->template;
		
		if ($templateFile !== false) {
			if ($this->isRedirected) {
				throw new LightFrameException("Can't modify body anymore, response is set to redirect");
			}
			$this->template = $templateFile;
		}
		
		return $temp;
	}
	
	/**
	 * Define or check the context array
	 *
	 * @param array $context an array containing variables to be passed to the template
	 * @return array current context
	 * @throws LightFrameException
	 */
	function context($context = false) {
		$temp = $this->context;
		if ($context !== false) {
			if ($this->isRedirected) {
				throw new LightFrameException("Can't modify context anymore, response is set to redirect");
			}
			$this->context = $context;
		}
		return $temp;
	}
	
	/**
	 * Have the response redirect the browser to another URL
	 * 
	 * After redirect is set, the response cannot be modified in any other way
	 * than redefining the redirection
	 *
	 * @param string $URL
	 */
	function redirect($URL) {
		$this->isRedirected = true;
		$this->header->Location = $URL;
		$this->body = "";
		$this->template = null;
		$this->context = null;
	}

	/**
	 * Respond to the client.
	 *
	 */
	function commit() {
		// for future references: headers_list() catches all headers to be sent.
		if ($this->body === null && !$this->template) {
			trigger_error('Template was not defined and body was not defined.');
		}
				
		$this->header->send();
		
		if ($this->body === null) {
			$template = new Template($this->template, $this->context, $this->builtin);
			return (string)$template->compile();
		}
		else {
			return $this->body;
		}
	}
}

/**
 * HTTP header abstraction
 */
class HTTPHeaders {
	/**
	 * The requested resource could not be found but may be available again in the
	 * future. Subsequent requests by the client are permissible.
	 */
	const NOT_FOUND = 404;

	/**
	 * A generic error message, given when no more specific message is suitable.
	 */
	const INTERNAL_ERROR = 500;

	/** This and all future requests should be directed to the given URI. */
	const MOVED = 301;

	/**
	 * The response to the request can be found under another URI using a GET
	 * method. When received in response to a PUT, it should be assumed that the
	 * server has received the data and the redirect should be issued with a
	 * separate GET message.
	 */
	const SEE_OTHER = 303;

	/**
	 * The request was a legal request, but the server is refusing to respond to
	 * it. Unlike a 401 Unauthorized response, authenticating will make no
	 * difference.
	 */
	const FORBIDDEN = 403;

	/**
	 * Similar to 403 Forbidden, but specifically for use when authentication is
	 * possible but has failed or not yet been provided.
	 */
	const UNAUTHORIZED = 401;
	
	private $status = '';
	private $headers = array();
	
	/**
	 * Change the X-Powered-By header to include LightFrame info
	 */
	function __construct() {
		$poweredBy = '';
		if (LF_EXPOSE_LIGHTFRAME) {
			$poweredBy = LF_NAME.'/'.LF_VERSION;
			if (ini_get('expose_php'))
				$poweredBy .= ' (PHP/'.PHP_VERSION.')';
		}
			
		$this->headers['X-Powered-By'] = $poweredBy;
		$this->headers['Content-Type'] = LF_DEFAULT_CONTENT_TYPE;
	}
	
	/**
	 * Send headers
	 * 
	 * Convert the $headers array into headers() function call
	 */
	function send() {
		if ($this->status)
			header($this->status);
		if ($this->headers)
			foreach ($this->headers as $header => $value)
				header($header.': '.$value);
	}
	
	/**
	 * Magic set function
	 * 
	 * Enables $foo.<header> = <value> behaviour. The header 'status' is specially
	 * treated as the HTTP/1.1 status header. (defaults to 'HTTP/1.1 200 OK')
	 *
	 * @param string $header
	 * @param mixed $value
	 */
	function __set($header, $value) {
		if ($header == 'status')
			$this->setStatus($value);
		else
			$this->headers[$header] = (string)$value;
	}
	
	/**
	 * Set the status header
	 * 
	 * Give one of the HTTPHeaders status constants, or http status code numbers as argument.
	 *
	 * @param int $status
	 */
	function setStatus($status) {
		$s = 'HTTP/1.1 ';
		switch($status) {
			case HTTPHeaders::NOT_FOUND: $s .= $status.' Not Found'; break;
			case HTTPHeaders::INTERNAL_ERROR: $s .= $status.' Internal Error'; break; 
			case HTTPHeaders::MOVED: $s .= $status. ' Moved Permanently'; break;
			case HTTPHeaders::SEE_OTHER: $s .= $status. ' See Other'; break;
			default: '200 OK';
		}
		$this->status = $s;
	}
	
	/**
	 * Magic method for getting header values
	 * 
	 * Accessing $foo.<header>'s value
	 *
	 * @param string $header
	 * @return string
	 */
	function __get($header) {
		return $this->headers[$header];
	}
	
	/**
	 * Is a header set?
	 *
	 * @param string $header
	 * @return boolean
	 */
	function __isset($header) {
		return isset($this->headers[$header]);
	}
	
	/**
	 * Delete a header
	 *
	 * @param string $header
	 */
	function __unset($header) {
		if (isset($this->headers[$header]))
			unset($this->headers[$header]);
	}
	
	/**
	 * Shortcuts to often-used header sets
	 * 
	 * For stuff, like $foo.redirectTo(<url>), that would modify the headers
	 * to redirect the browser. Another one might be for disabling cache for a
	 * certain page...
	 *
	 * @param string $name
	 * @param array $args
	 */
	function __call($name, $args) {
		// TODO: do some shortcuts (or make them as Helpers)
	}

}