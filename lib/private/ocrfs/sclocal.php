<?php
namespace OC\OCRFS;

class SCLocal implements StateCacheRFS {
    protected $serverId;
    protected $datadirectory;
    protected $cache;
    
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
                Log::debug("mkdir: " . $curPath);
                mkdir($curPath);
            }
        }
    }
    
    protected function checkLTime($id) {
        if(!$this->cache[$id]["local"] && $this->cache[$id]["ltime"]+10 < time()) {
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
                    \OC\tryCatch()->c($fp = opendir($this->datadirectory."/".$row["path"]));
                    if(is_resource($fp)) {
                        for($i=0;$i<$row["seek"];$i++) {
                            if(readdir($fp) === false) {
                                break;
                            }
                        }
                    }
                }
                else {
                    if($row["mode"] == "r$bin") {
                        $fp = fopen($this->datadirectory."/".$row["path"], "r$bin");
                    }
                    else {
                        $fp = fopen($this->datadirectory."/".$row["path"], "c$bin");
                    }
                    fseek($fp, $row["seek"]);
                }
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

    public function fopen($path,$mode,$onlyLocal = false, $data = null, $close = false, $time = null, $atime = null) {
        Log::debug("($path,$mode) datadirectory=".$this->datadirectory);

        \OC\tryCatch()->c($fp = fopen($this->datadirectory."/".$path, $mode));
        if(is_resource($fp)) {
            if($data !== null) {
                Log::debug("fast with data(".strlen($data).") close: ".($close*1));
                if(fwrite($fp, $data) != strlen($data)) {
                    fclose($fp);
                    return false;
                }
                else if($close) {
                    $res = fclose($fp);
                    if($res && $time !== null) {
                        $args = array($this->datadirectory."/".$path, $time);
                        if($atime !== null) {
                            $args[] = $atime;
                        }
                        call_user_func_array("touch", $args);
                    }
                    return $res;
                }
            }
            $seek = ftell($fp);
            // Nur lokales FS, kein StateLess
            if($onlyLocal) {
                while(array_key_exists($id = mt_rand(), $this->cache)) {
                }
                $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => true, "ltime" => time());
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
//			        Log::debug("insert: " . $result . " id: " . $id);
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
        $feof = feof($fpinfo["fp"]);
        if(!$feof) {
            $seek = ftell($fpinfo["fp"]);
            if($seek == $fpinfo["seek"]) {
                fseek($fpinfo["fp"], 0, SEEK_END);
                if($seek === ftell($fpinfo["fp"])) {
                    $feof = true;
                }
                else {
                    fseek($fpinfo["fp"], $seek, SEEK_SET);
                }
            }
        }
        return $feof;
    }
    
    public function fflush($id) {
        $fpinfo = $this->getFPInfo($id);
        return fflush($fpinfo["fp"]);
    }

    public function fclose($id, $time = null, $atime = null) {
        $fpinfo = $this->getFPInfo($id);
        if(!$this->cache[$id]["local"]) {
    	    $query = \OCP\DB::prepare("DELETE FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
	    	$query->execute(array($id,$this->serverId));
        }
        $res = fclose($fpinfo["fp"]);
        
        if($res && $time !== null) {
            $args = array($this->datadirectory."/".$fpinfo["path"], $time);
            if($atime !== null) {
                $args[] = $atime;
            }
            call_user_func_array("touch", $args);
        }
        
        Log::debug("(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
        $bin = strpos($row["mode"],"b") !== false ? "b" : "";
        if($row["mode"] != "r$bin") {
            HashManager::getInstance()->updateHashByPath($fpinfo["path"]);
        }
        unset($this->cache[$id]);
        return $res;
    }
    
    public function opendir($path,$onlyLocal = false) {
        Log::debug("($path) datadirectory=".$this->datadirectory);
        \OC\tryCatch()->c($fp = opendir($this->datadirectory."/".$path));
        $mode = "d";
        $seek = 0;

        if(is_resource($fp)) {
            $seek = ftell($fp);
            // Nur lokales FS, kein StateLess
            if($onlyLocal) {
                while(array_key_exists($id = mt_rand(), $this->cache)) {
                }
                $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => true, "ltime" => time());
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
//			        Log::debug("insert: " . $result . " id: " . $id);
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
        while(($res = readdir($fpinfo["fp"])) === ".ocrfs" || $res === ".ocrfsTrash") {
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
        \OC\tryCatch()->c($res = closedir($fpinfo["fp"]));
        Log::debug("(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
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
        if($_path == "//cnotzon/files") {
//            error_log(print_r($_SERVER, true));
            exit;
        }
        $msg = "($_path) datadirectory=".$this->datadirectory;
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            $stat = stat($path);
//            $msg .= " = ".print_r($stat, true);
            Log::debug($msg);
            return $stat;
        }
        Log::debug($msg." = false");//\n".Helper::getLastCallFunc());
        return false;
    }
    
    public function touch($_path, $time = null, $atime = null) {
        $msg = "($_path, $time, $atime) datadirectory=".$this->datadirectory;
        $path = $this->datadirectory."/".$_path;

        $args = array($path);
        if($time > 0) {
            $args[]= $time;
        }
        if($atime > 0) {
            $args[]= $atime;
        }
        \OC\tryCatch()->c($res = call_user_func_array("touch", $args));
        Log::debug($msg." = ".$res);
        return $res;
    }
    
    public function mkdir($_path, $time = null, $atime = null) {
        $path = $this->datadirectory."/".$_path;
        if(!file_exists($path)) {
            $ok = \OC\tryCatch()->c(mkdir($path));
            if($ok) {
                if($time !== null) {
                    $args = array($path,$time);
                    if($atime !== null) {
                        $args[] = $atime;
                    }
                    
                    clearstatcache(true, $path);
                    call_user_func_array("touch", $args);
                }
                
                $dirname = dirname($_path);
                HashManager::getInstance()->updateHashByPath($dirname);
            }
            return $ok;
        }
        return false;
    }

    public function unlink($_path) {
        Log::debug("($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            $md5 = md5(microtime()."");
            mkdir($this->datadirectory."/.ocrfsTrash/".$md5);
            $ok = \OC\tryCatch()->c(rename($path, $this->datadirectory."/.ocrfsTrash/".$md5."/".basename($path)));
            if($ok) {
                HashManager::getInstance()->updateHashByPath($_path);
            }
            return $ok;
//            return \OC\tryCatch()->c(unlink($path));
        }
        return false;
    }
    
    public function rmdir($_path, $recursive = false) {
        Log::debug("($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            if($_path != "/" && $recursive) {
                $md5 = md5(microtime()."");
                mkdir($this->datadirectory."/.ocrfsTrash/".$md5);
                $ok = \OC\tryCatch()->c(rename($path, $this->datadirectory."/.ocrfsTrash/".$md5."/".basename($path)));
                if($ok) {
                    HashManager::getInstance()->updateHashByPath($_path);
                }
                return $ok;
                
                /*
                $paths = array($path);
                for($i=0;$i<count($paths);$i++) {
                    $dir = dir($this->datadirectory."/".$paths[$i]);
                    if(is_object($dir)) {
                        while(($entry = $dir->read())) {
                            if($entry == "." || $entry == "..") continue;
                            $p = $this->datadirectory."/".$paths[$i]."/".$entry;
                            if(is_dir($p)) {
                                $paths[] = $paths[$i]."/".$entry;
                            }
                            else {
                                unlink($p);
                            }
                        }
                        $dir->close();
                        rmdir($this->datadirectory."/".$paths[$i]);
                    }
                }
                
                return \OC\tryCatch()->c(rmdir($path));
                */
            }
            else {
                return \OC\tryCatch()->c(rmdir($path));
            }
        }
        return false;
    }
    
    public function remove($_path, $recursive = false) {
        $path = $this->datadirectory."/".$_path;
        if(is_dir($path)) {
            return $this->rmdir($_path);
        }
        return $this->unlink($_path);
    }
};