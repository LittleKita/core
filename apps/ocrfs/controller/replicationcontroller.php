<?php

/**
* ownCloud - App Template Example
*
* @author Bernhard Posselt
* @copyright 2012 Bernhard Posselt nukeawhale@gmail.com 
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

namespace OCA\OCRFS\Controller;

use OCA\AppFramework\Controller\Controller;
use OCA\OCRFS\PlainResponse;
use OC\OCRFS\Manager;
use OCA\AppFramework\Http\JSONResponse;

class ReplicationController extends Controller {
	private static $FS_NAME = "ocrfs://";
	private $itemMapper;

	/**
	 * @param Request $request: an instance of the request
	 * @param API $api: an api wrapper instance
	 * @param ItemMapper $itemMapper: an itemwrapper instance
	 */
	public function __construct($api, $request, $itemMapper){
		parent::__construct($api, $request);
		$this->itemMapper = $itemMapper;
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fopen() {
		$mode = $this->params('mode');
		$path = $this->params('path');
		$rm = $this->params('rm');
		$close = $this->params('close');
		
		$bin = strpos($mode,"b") !== false ? "b" : "";

        if(!$rm/1) {
            throw new \Exception("Wrong rm($rm).");
        }
        
        $rm = $rm/1;
        
        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        $master = Manager::getInstance()->isMaster();
        
        if($mode == "r$bin" && !$master) {
            throw new \Exception("Is Slave.");
        }

		if($mode == "r$bin" || $rm == -1 || !$master) {
		    $sc = Manager::getInstance()->getLocal();
		}
		else {
		    $sc = Manager::getInstance()->getCollection(array($rm));
		}
		
		$data = "";
		
		if($mode != "r$bin" || strpos($mode,"+") !== false) {
    		$fp = fopen("php://input","r");
	    	if(is_resource($fp)) {
			    while(!feof($fp)) {
				    $data .= fread($fp,1024);
			    }
			    fclose($fp);
	    	}
		}
		
		$sc->checkPath($path);
		
		if(strlen($data) > 0) {
		    if($close) {
        		$res = $sc->fopen($path, $mode, false, $data, true);
		    }
		    else {
        		$res = $sc->fopen($path, $mode, false, $data);
		    }
		}
		else {
    		$res = $sc->fopen($path, $mode, false);
		}
		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fread() {
		$id   = $this->params('id');
		$size = $this->params('size');
		
		if($size === null) {
		    throw new \Exception("$size === null");
		}

        $sc = Manager::getInstance()->getCollectionById($id);
        
        $data = $sc->fread($id,$size);

		return new PlainResponse($data);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fwrite() {
		$id = $this->params('id');

		$fp = fopen("php://input","r");
		if(is_resource($fp)) {
			$data = "";
			while(!feof($fp)) {
				$data .= fread($fp,1024);
			}
			fclose($fp);

			if($data === "" || $data === null || $data === false) {
			    throw new \Exception("No data found.");
			}
		}
		else {
		    throw new \Exception("No data found.");
		}
		
        $sc = Manager::getInstance()->getCollectionById($id);
        
        $res = $sc->fwrite($id,$data);

		return new PlainResponse($res);
	}
	
		/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fflush() {
		$id = $this->params('id');

        $sc = Manager::getInstance()->getCollectionById($id);
        
        $res = $sc->fflush($id);

		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function metadata() {
		$id = $this->params('id');
		
		$sc = Manager::getInstance()->getCollectionById($id);
		
		$res = $sc->getMetaData($id);

		return new JSONResponse($res);
	}
	
	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fstat() {
		$id = $this->params('id');
		
		$sc = Manager::getInstance()->getCollectionById($id);
		
		$res = $sc->fstat($id);

		return new JSONResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function feof() {
		$id = $this->params('id');
		
		$sc = Manager::getInstance()->getCollectionById($id);
		
		$res = $sc->feof($id);

		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function fclose() {
		$id = $this->params('id');
		$args = array($id);
		$time = $this->params('time');
		if($time !== null) {
		    $args[] = $time;
		    $atime = $this->params('atime');
		    if($atime !== null) {
		        $args[] = $atime;
		    }
		}

		$sc = Manager::getInstance()->getCollectionById($id);

        $res = call_user_func_array(array($sc,"fclose"), $args);

		return new PlainResponse($res);
	}
	
	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function mkdir() {
		$path = $this->params('path');
		$rm = $this->params('rm');
		
        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        if($rm == -1) {
            $sc = Manager::getInstance()->getLocal();
        }
        else {
    	    $sc = Manager::getInstance()->getCollection(array($rm));
        }

		$sc->checkPath($path);
		$res = $sc->mkdir($path);
		return new PlainResponse($res);
	}
	
	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function unlink() {
		$path = $this->params('path');
		$rm = $this->params('rm');
		
        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        if($rm == -1) {
            $sc = Manager::getInstance()->getLocal();
        }
        else {
    	    $sc = Manager::getInstance()->getCollection(array($rm));
        }

		$sc->checkPath($path);
		$res = $sc->unlink($path);
		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function rmdir() {
		$path = $this->params('path');
		$rm = $this->params('rm');
		
        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        if($rm == -1) {
            $sc = Manager::getInstance()->getLocal();
        }
        else {
    	    $sc = Manager::getInstance()->getCollection(array($rm));
        }

		$sc->checkPath($path);
		$res = $sc->rmdir($path);
		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function urlstat() {
		$path = $this->params('path');

        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        if(!Manager::getInstance()->isMaster()) {
            throw new \Exception("Is Slave.");
        }

        $sc = Manager::getInstance()->getLocal();

		$res = $sc->url_stat($path);
		return new JSONResponse($res);
	}
	
		/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function opendir() {
		$path = $this->params('path');

        if(Manager::getInstance()->isClient()) {
            throw new \Exception("Is Client.");
        }
        
        if(!Manager::getInstance()->isMaster()) {
            throw new \Exception("Is SLave.");
        }

	    $sc = Manager::getInstance()->getLocal();

		$sc->checkPath($path);
		
		$res = $sc->opendir($path);
		return new PlainResponse($res);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function readdir() {
		$id   = $this->params('id');

        $sc = Manager::getInstance()->getLocal();
        
        $data = $sc->readdir($id);

		return new PlainResponse($data);
	}
	
	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function rewinddir() {
		$id   = $this->params('id');

        $sc = Manager::getInstance()->getLocal();
        
        $data = $sc->rewinddir($id);

		return new PlainResponse($data);
	}

	/**
	 * ATTENTION!!!
	 * The following comments turn off security checks
	 * Please look up their meaning in the documentation!
	 *
	 * @CSRFExemption
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @IsLoggedInExemption
	 *
	 * @brief renders the index page
	 * @return an instance of a Response implementation
	 */
	public function closedir() {
		$id = $this->params('id');
		
		$sc = Manager::getInstance()->getLocal();

        $res = $sc->closedir($id);

		return new PlainResponse($res);
	}
}
