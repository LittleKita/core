<?php
namespace OC\OCRFS;

class SCLocal implements StateCacheRFS {
    protected $serverId;
    protected $datadirectory;
    protected $cache;
    
    protected static $oldErrorHandler = null;
    protected static $oldExceptionHandler = null;
    
    public function __construct($serverId, $datadirectory) {
        $this->serverId = $serverId;
        $this->datadirectory = $datadirectory;
        $this->cache = array();
        
        \OC\TryCatch::dummy();
    }
    
    public function setRM($rm) {
    }

    public function getServerId() {
        return $this->serverId;
    }
    
    public function checkPath($path) {
        $dirname = explode("/", dirname($path));
        $curPath = $this->datadirectory;
        for($i=0;$i<count($dirname);$i++) {
            $curPath .= $dirname[$i]."/";
            if(!file_exists($curPath)) {
                error_log("checkPath-mkdir: " . $curPath);
                mkdir($curPath);
            }
        }
    }
    
    protected function checkLTime($id) {
        if($this->cache[$id]["ltime"]+10 < time()) {
            $query = \OCP\DB::prepare("UPDATE *PREFIX*freplicate set ltime=? WHERE freplicate_id=? AND server_id=?");
            $query->execute(array(time(),$id,$this->serverId));
            $this->cache[$id]["ltime"] = time();
        }
    }

    protected function &getFPInfo($id) {
        if(array_key_exists($id,$this->cache)) {
            $this->checkLTime($id);
            return $this->cache[$id];
        }

		$query = \OCP\DB::prepare("SELECT * FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
        if($result = $query->execute(array($id,$this->serverId))) {
            while($row = $result->fetchRow()) {
                $row["local"] = false;
                $bin = strpos($row["mode"],"b") !== false ? "b" : "";

                if($row["mode"] == "d") {
                    $fp = opendir($this->datadirectory."/".$row["path"]);
                    for($i=0;$i<count($row);$i++) {
                        if(readdir($fp) === false) {
                            break;
                        }
                    }
                }
                else if($row["mode"] == "r$bin") {
                    $fp = fopen($this->datadirectory."/".$row["path"], "r$bin");
                }
                else {
                    $fp = fopen($this->datadirectory."/".$row["path"], "c$bin");
                }
                fseek($fp, $row["seek"]);
                $row["fp"] = $fp;
                $this->cache[$id] = $row;
            }

            if(array_key_exists($id,$this->cache)) {
                $this->checkLTime($id);
                return $this->cache[$id];
            }
        }
        throw new \Exception("FP-ID not found. (id:".$id.")");
    }
    
    protected function updateSeek($id,$seek = -1) {
        $seek = $seek == -1 ? ftell($this->cache[$id]["fp"]) : $seek;
        
        $this->cache[$id]["seek"] = $seek;

        if(!$this->cache[$id]["local"]) {
        	$query = \OCP\DB::prepare("UPDATE *PREFIX*freplicate set seek=?, ltime=? WHERE freplicate_id=? AND server_id=?");
        	$query->execute(array($seek,time(),$id,$this->serverId));
        }
    }
    
    public function exceptionHandler(\Exception $e) {
        error_log($e->getMessage());
        if(self::$oldExceptionHandler != null) {
            call_user_func_array(self::$oldExceptionHandler, func_get_args());
        }
    }

    public function errorHandler($errno , $errstr , $errfile = null, $errline = null, $errcontext = null) {
        error_log($errfile.":".$errline." ($errno)$errstr");
        if(self::$oldErrorHandler != null) {
            call_user_func_array(self::$oldErrorHandler, func_get_args());
        }
    }

    public function fopen($path,$mode,$onlyLocal = false) {
        error_log($this->serverId."\tSCLocal::fopen($path,$mode) datadirectory=".$this->datadirectory);

        \OC\tryCatch()->c($fp = fopen($this->datadirectory."/".$path, $mode));
        if(is_resource($fp)) {
            $seek = ftell($fp);
            // Nur lokales FS, kein StateLess
            if($onlyLocal) {
                while(array_key_exists($id = mt_rand(), $this->cache)) {
                }
                $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "local" => true, "ltime" => time());
                return $id;
            }
            // StateLess 
            else {
	    		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`,`ltime`) VALUES (?,?,?,?,?)");
    			if($result = $query->execute(array($path,$seek,$mode,$this->serverId,time()))) {
		    	    $id = \OCP\DB::insertid();
    			    if(is_numeric($id)) {
    			        $id = $id/1;
    			    }
//			        error_log("insert: " . $result . " id: " . $id);
		    	    $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => false, "ltime" => time());
        			return $id;
    			}
            }
        }
        return false;
    }

    public function fread($id,$size) {
        $fpinfo = $this->getFPInfo($id);
        $data = fread($fpinfo["fp"], $size);
        $this->updateSeek($id);
        return $data;
    }

    public function fwrite($id,$data) {
        $fpinfo = $this->getFPInfo($id);
        $res = fwrite($fpinfo["fp"],$data);
        $this->updateSeek($id);
        return $res;
    }
    
    public function fstat($id) {
        $fpinfo = $this->getFPInfo($id);
        return fstat($fpinfo["fp"]);
    }
    
    public function feof($id) {
        $fpinfo = $this->getFPInfo($id);
        return feof($fpinfo["fp"]);
    }
    
    public function fflush($id) {
        $fpinfo = $this->getFPInfo($id);
        return fflush($fpinfo["fp"]);
    }

    public function fclose($id) {
        $fpinfo = $this->getFPInfo($id);
        if(!$this->cache[$id]["local"]) {
    	    $query = \OCP\DB::prepare("DELETE FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
	    	$query->execute(array($id,$this->serverId));
        }
        $res = fclose($fpinfo["fp"]);
        error_log($this->serverId."\tSCLocal::fclose(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
        unset($this->cache[$id]);
        return $res;
    }
    
    public function opendir($path,$onlyLocal = false) {
        error_log($this->serverId."\tSCLocal::opendir($path) datadirectory=".$this->datadirectory);
        \OC\tryCatch()->c($fp = opendir($this->datadirectory."/".$path));
        $mode = "d";
        $seek = 0;

        if(is_resource($fp)) {
            $seek = ftell($fp);
            // Nur lokales FS, kein StateLess
            if($onlyLocal) {
                while(array_key_exists($id = mt_rand(), $this->cache)) {
                }
                $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "local" => true);
                return $id;
            }
            // StateLess 
            else {
	    		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`,`ltime`) VALUES (?,?,?,?,?)");
    			if($result = $query->execute(array($path,$seek,$mode,$this->serverId,time()))) {
		    	    $id = \OCP\DB::insertid();
            	    if(is_numeric($id)) {
            	        $id = $id/1;
            	    }
//			        error_log("insert: " . $result . " id: " . $id);
		    	    $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => false, "ltime" => time());
        			return $id;
    			}
            }
        }
        return null;
    }

    public function readdir($id) {
        $fpinfo = $this->getFPInfo($id);
        $seekp = 1;
        while(($res = readdir($fpinfo["fp"])) === ".ocrfs") {
            $seekp++;
        }
        $this->updateSeek($id,$this->cache[$id]["seek"]+$seekp);
        return $res;
    }

    public function rewinddir($id) {
        $fpinfo = $this->getFPInfo($id);
        $res = rewinddir($fpinfo["fp"]);
        $this->updateSeek($id,0);
        return $res;
    }

    public function closedir($id) {
        $fpinfo = $this->getFPInfo($id);
        if(!$this->cache[$id]["local"]) {
	    	$query = \OCP\DB::prepare("DELETE FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
    		$query->execute(array($id,$this->serverId));
        }
        $res = closedir($fpinfo["fp"]);
        error_log($this->serverId."\tSCLocal::closedir(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
        unset($this->cache[$id]);
        return $res;
    }

    
    public function getMetaData($id) {
        $fpinfo = $this->getFPInfo($id);
        $res = stream_get_meta_data($fpinfo["fp"]);
        if(array_key_exists("uri", $res)) {
            $res["uri"] = substr($res["uri"], strlen($this->datadirectory));
            if(substr($res["uri"],0,1) !== "/") {
                $res["uri"] = "/".$res["uri"];
            }
        }
        return $res;
    }
    
    public function url_stat($_path) {
        $msg = $this->serverId."\tSCLocal::url_stat($_path) datadirectory=".$this->datadirectory;
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            $stat = stat($path);
//            $msg .= " = ".print_r($stat, true);
            error_log($msg);
            return $stat;
        }
        error_log($msg." = false");//\n".Helper::getLastCallFunc());
        return false;
    }
    
    public function touch($_path, $time = null, $atime = null) {
        $msg = $this->serverId."\tSCLocal::touch($_path, $time, $atime) datadirectory=".$this->datadirectory;
        $path = $this->datadirectory."/".$_path;

        $args = array($path);
        if($time > 0) {
            $args[]= $time;
        }
        if($atime > 0) {
            $args[]= $atime;
        }
        \OC\tryCatch()->c($res = call_user_func_array("touch", $args));
        error_log($msg." = ".$res);
        return $res;
    }
    
    public function mkdir($_path) {
        $path = $this->datadirectory."/".$_path;
        if(!file_exists($path)) {
            return \OC\tryCatch()->c(mkdir($path));
        }
        return false;
    }

    public function unlink($_path) {
        error_log($this->serverId."\tSCLocal::unlink($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            return \OC\tryCatch()->c(unlink($path));
        }
        return false;
    }
    
    public function rmdir($_path) {
        error_log($this->serverId."\tSCLocal::rmdir($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            return \OC\tryCatch()->c(rmdir($path));
        }
        return false;
    }
};