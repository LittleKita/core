<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt dev@bernhard-posselt.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\OCRFS;

use OCA\AppFramework\Http\Response;
use OCA\AppFramework\Http\Http;

/**
 * A renderer for JSON calls
 */
class PlainResponse extends Response {

	protected $data;
	protected $isData;


	/**
	 * @param array|object $data the object or array that should be transformed
	 * @param int $statusCode the Http status code, defaults to 200
	 */
	public function __construct($data, $statusCode=Http::STATUS_OK) {
		$this->data = $data;
		$this->setStatus($statusCode);
		$this->addHeader('X-Content-Type-Options', 'nosniff');
		$type = gettype($data);
		if($type == "string") {
		    $this->addHeader('Content-type', 'application/octet-stream');
		}
		else if($type == "integer" || $type == "double") {
    		$this->addHeader('Content-type', 'singletype');
		}
		else if($type == "boolean") {
    		$this->addHeader('Content-type', 'singletype');
    		if($data === true) {
    		    $this->data = "t";
    		}
    		else if($data === false) {
    		    $this->data = "f";
    		}
    		else {
    		    throw new \Exception("Unknonw type ".$data);
    		}
		}
		else if($type == "NULL") {
    		$this->addHeader('Content-type', 'singletype');
    		$this->data = "n";
		}
		else {
		    throw new \Exception("Unknonw type ".$data);
		}
	}

	/**
	 * Returns the rendered json
	 * @return string the rendered json
	 */
	public function render(){
		return $this->data;
	}

	/**
	 * Sets values in the data json array
	 * @param array|object $params an array or object which will be transformed
	 *                             to JSON
	 */
	public function setData($data){
		$this->data = $data;
	}


	/**
	 * Used to get the set parameters
	 * @return array the data
	 */
	public function getData(){
		return $this->data;
	}

}
