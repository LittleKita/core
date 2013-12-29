<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Stream;

use OC\OCRFS\Manager;

/**
 * a stream wrappers for ownCloud's virtual filesystem
 */
class OCRFS {
	const FS_SHEME = "ocrfs://";
	protected $fileSource = null;
	protected $sc = null;
	
	public static function init() {
	    echo "init";
	}

	public function getRealPath($path) {
		$rpath = substr($path, strlen(self::FS_SHEME));
/*
		while(strpos($rpath,"//") !== false) {
			$rpath = str_replace("//","/",$rpath);
		}
*/
		return $rpath;
	}
	
	public function directAccess($path) {
	    $dirname = dirname($path);
	    if(!$dirname || $dirname == "/") {
	        return true;
	    }
	    return false;
	}
	
	protected function getCollection($readOnly) {
	    if($readOnly) {
		    if(Manager::getInstance()->isClient()) {
    		    $this->sc = Manager::getInstance()->getRandomMaster();
		    }
		    else {
    		    $this->sc = Manager::getInstance()->getLocal();
		    }
	    }
	    else {
		    if(Manager::getInstance()->isMaster()) {
    		    $this->sc = Manager::getInstance()->getCollection(array(), -1);
		    }
		    else {
    		    $this->sc = Manager::getInstance()->getRandomMaster();
		    }
	    }
	    
	    return $this->sc;
	}

	public function stream_open($_path, $mode, $options, &$opened_path) {
		$path = $this->getRealPath($_path);
	    if($this->directAccess($path)) {
	        $this->fileSource = fopen(Manager::getInstance()->getDataDirectory() . "/" . $path, $mod);
	        $this->meta = stream_get_meta_data($this->fileSource);
	        return is_resource($this->fileSource);
	    }
//	    error_log("stream_open $_path $mode");
		$bin = strpos($mode,"b") !== false ? "b" : "";
		
		if($mode == "r$bin") {
		    $this->sc = $this->getCollection(true);
		}
		else {
		    $this->sc = $this->getCollection(false);
		}
		$this->fileSource = $this->sc->fopen($path, $mode);

		if ($this->fileSource) {
			$this->meta = $this->sc->getMetaData($this->fileSource);
		}
		return $this->fileSource > 0 ? true : false;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
	    if(is_resource($this->fileSource)) {
	        return fseek($this->fileSource, $offset, $whence);
	    }
		return $this->sc->fseek($this->fileSource, $offset, $whence);
	}

	public function stream_tell() {
	    if(is_resource($this->fileSource)) {
	        return ftell($this->fileSource);
	    }
		return $this->sc->ftell($this->fileSource);
	}

	public function stream_read($count) {
	    if(is_resource($this->fileSource)) {
	        return fread($this->fileSource, $count);
	    }
		return $this->sc->fread($this->fileSource, $count);
	}

	public function stream_write($data) {
	    if(is_resource($this->fileSource)) {
	        return fwrite($this->fileSource, $data);
	    }
		return $this->sc->fwrite($this->fileSource, $data);
	}

    /*
	public function stream_set_option($option, $arg1, $arg2) {
		switch ($option) {
			case STREAM_OPTION_BLOCKING:
				stream_set_blocking($this->fileSource, $arg1);
				break;
			case STREAM_OPTION_READ_TIMEOUT:
				stream_set_timeout($this->fileSource, $arg1, $arg2);
				break;
			case STREAM_OPTION_WRITE_BUFFER:
				stream_set_write_buffer($this->fileSource, $arg1, $arg2);
		}
	}
	*/
	
	public function stream_get_meta_data() {
	    return $this->meta;
	}
	
	function stream_metadata($_path, $option, $var) {
        if($option == STREAM_META_TOUCH) {
            $path = $this->getRealPath($_path);
		    $this->sc = $this->getCollection(true);
		    $args = array($path);
		    for($i=0;$i<count($var) && is_array($var);$i++) {
		        $args[] = $var[$i];
		    }
	        error_log("stream_touch($_path, ".print_r($var, true).")");
            return call_user_func_array(array($this->sc,"touch"),$args);
        }
        return false;
    }

	public function stream_stat() {
	    if(is_resource($this->fileSource)) {
	        return fstat($this->fileSource);
	    }
		return $this->sc->fstat($this->fileSource);
	}

	public function stream_lock($mode) {
	    if(is_resource($this->fileSource)) {
	        return flock($this->fileSource,$mode);
	    }
		$this->sc->flock($this->fileSource, $mode);
	}

	public function stream_flush() {
	    if(is_resource($this->fileSource)) {
	        return flush($this->fileSource);
	    }
		return $this->sc->fflush($this->fileSource);
	}

	public function stream_eof() {
	    if(is_resource($this->fileSource)) {
	        return feof($this->fileSource);
	    }
		return $this->sc->feof($this->fileSource);
	}

	public function url_stat($_path) {
		$path = $this->getRealPath($_path);
		$tpath = Manager::getInstance()->getDataDirectory() . "/" . $path;
	    if($this->directAccess($path) && !is_dir($tpath)) {
	        if(file_exists($tpath)) {
	            $res = stat($tpath);
	        }
	        else {
	            return false;
	        }
	    }
	    else {
		    $this->sc = $this->getCollection(true);
    	    $res = $this->sc->url_stat($path);
	    }
	    
	    if(is_array($res) && array_key_exists("uri", $res)) {
	        $res["uri"] = self::FS_SHEME.$res["uri"];
	    }

		return $res; 
	}

	public function stream_close() {
	    if(is_resource($this->fileSource)) {
	        return fclose($this->fileSource);
	    }
		return $this->sc->fclose($this->fileSource);
	}

	public function unlink($path) {
		$path = $this->getRealPath($path);
	    $this->sc = $this->getCollection(false);
		return $this->sc->unlink($path);
	}

	public function dir_opendir($path, $options) {
		$path = $this->getRealPath($path);
		
		$this->sc = $this->getCollection(true);

		if ($this->dirSource = $this->sc->opendir($path)) {
			$this->meta = $this->sc->getMetaData($this->dirSource);
			return true;
		}
		return false;
	}

	public function dir_readdir() {
		return $this->sc->readdir($this->dirSource);
	}

	public function dir_closedir() {
		return $this->sc->closedir($this->dirSource);
	}

	public function dir_rewinddir() {
		return $this->sc->rewinddir($this->dirSource);
	}

	public function mkdir($path) {
		$path = $this->getRealPath($path);
		$this->sc = $this->getCollection(false);
		return $this->sc->mkdir($path);
	}
	
	public function rmdir($path) {
		$path = $this->getRealPath($path);
		$this->sc = $this->getCollection(false);
		return $this->sc->rmdir($path);
	}
}
