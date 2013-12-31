<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Stream;

use OC\OCRFS\Manager;
use OC\OCRFS\Helper;
use OC\OCRFS\Log;
/**
 * a stream wrappers for ownCloud's virtual filesystem
 */
class OCRFS {
	const FS_SHEME = "ocrfs://";
	protected $fileSource = null;
	protected $mode = null;
	protected $path = null;
	protected $buffer = "";
	protected $wait = false;
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
	
	public function ignorePath($path) {
	    $array = array("mount.php", "mount.json");
	    $fname = basename($path);
	    return (in_array($fname, $array));
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
    		    $this->sc = Manager::getInstance()->getCollection(array());
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
	    Log::debug("$_path $mode");
		$bin = strpos($mode,"b") !== false ? "b" : "";
		
		$this->wait = false;
		if($mode == "r$bin") {
		    $this->sc = $this->getCollection(true);
		}
		else {
		    $this->sc = $this->getCollection(false);
		    if(strpos($mode,"+") === false) {
		        $this->wait = true;
		    }
		}
		
	    $this->path = $path;
	    $this->mode = $mode;

		if(!$this->wait) {
    		$this->fileSource = $this->sc->fopen($path, $mode, true);
		    if ($this->fileSource) {
		    	$this->meta = $this->sc->getMetaData($this->fileSource);
	    	}
    		return $this->fileSource > 0 ? true : false;
		}
		else {
		    return true;
		}
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
	    if(is_resource($this->fileSource)) {
	        return fseek($this->fileSource, $offset, $whence);
	    }
        $this->sendBuffer();
		return $this->sc->fseek($this->fileSource, $offset, $whence);
	}

	public function stream_tell() {
	    if(is_resource($this->fileSource)) {
	        return ftell($this->fileSource);
	    }
        $this->sendBuffer();
		return $this->sc->ftell($this->fileSource);
	}

	public function stream_read($count) {
	    if(is_resource($this->fileSource)) {
	        return fread($this->fileSource, $count);
	    }
		return $this->sc->fread($this->fileSource, $count);
	}
	
	protected function sendBuffer($close = false) {
	    if($this->wait) {
	        $this->wait = false;
	        if($close) {
	            $this->fileSource = $this->sc->fopen($this->path, $this->mode, true, $this->buffer, true, time(), time());
	        }
	        else {
    	        $this->fileSource = $this->sc->fopen($this->path, $this->mode, true, $this->buffer);
    		    if ($this->fileSource) {
    		    	$this->meta = $this->sc->getMetaData($this->fileSource);
    	    	}
	        }
	    }
	    else if(strlen($this->buffer) > 0) {
	        $this->sc->fwrite($this->fileSource, $this->buffer);
        }
        $this->buffer = "";
	}

	public function stream_write($data) {
	    if(is_resource($this->fileSource)) {
	        return fwrite($this->fileSource, $data);
	    }
	    if($this->wait) {
	        $post_max_size = ((int)(str_replace('M', '', ini_get('post_max_size')))) * 1024 * 1024 / 2;
	        
	        $this->buffer .= $data;
	        if(strlen($this->buffer) >= $post_max_size) {
	            Log::debug("buffer full ".strlen($this->buffer)." >= ".$post_max_size);
	            $this->sendBuffer();
	        }
	        return strlen($data);
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
        $this->sendBuffer();
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
            return call_user_func_array(array($this->sc,"touch"),$args);
        }
        return false;
    }
    
    public function disk_free_space($_path) {
        $path = Manager::getInstance()->getDataDirectory()."/".$this->getRealPath($_path);
        return @\disk_free_space($path);
    }

	public function stream_stat() {
	    if(is_resource($this->fileSource)) {
	        return fstat($this->fileSource);
	    }
        $this->sendBuffer();
		return $this->sc->fstat($this->fileSource);
	}

	public function stream_lock($mode) {
	    if(is_resource($this->fileSource)) {
	        return flock($this->fileSource,$mode);
	    }
        $this->sendBuffer();
		return $this->sc->flock($this->fileSource, $mode);
	}

	public function stream_flush() {
	    if(is_resource($this->fileSource)) {
	        return flush($this->fileSource);
	    }
	    if(!$this->wait) {
            $this->sendBuffer();
    		return $this->sc->fflush($this->fileSource);
	    }
	}

	public function stream_eof() {
	    if(is_resource($this->fileSource)) {
	        return feof($this->fileSource);
	    }
        $this->sendBuffer();
		return $this->sc->feof($this->fileSource);
	}

	public function url_stat($_path) {
	    if($this->ignorePath($_path)) {
	        return false;
	    }
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
	    if($this->wait) {
	        $this->sendBuffer(true);
	        return true;
	    }
	    
	    $bin = strpos($this->mode,"b") !== false ? "b" : "";
		
		if($this->mode != "r$bin" || strpos($this->mode,"+") !== false) {
    		return $this->sc->fclose($this->fileSource, time(), time());
		}
		else {
    		return $this->sc->fclose($this->fileSource);
		}
	}

	public function unlink($path) {
		$path = $this->getRealPath($path);
	    $this->sc = $this->getCollection(false);
	    $res = $this->sc->unlink($path);
	    Log::debug("$path = ".($res ? "true" : "false"));
		return $res;
	}

	public function dir_opendir($path, $options) {
		$path = $this->getRealPath($path);
		
		$this->sc = $this->getCollection(true);

		if ($this->dirSource = $this->sc->opendir($path, true)) {
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
		$res = $this->sc->rmdir($path);
	    Log::debug("$path = ".($res ? "true" : "false"));
		return $res;
	}
}
