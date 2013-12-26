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
    }
    
    public function getServerId() {
        return $this->serverId;
    }

    protected function &getFPInfo($id) {
        if(array_key_exists($id,$this->cache)) {
            return $this->cache[$id];
        }

		$query = \OCP\DB::prepare("SELECT * FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
        if($result = $query->execute(array($id,$this->serverId))) {
            while($row = $result->fetchRow()) {
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
                return $this->cache[$id];
            }
        }
        throw new \Exception("FP-ID not found. (id:".$id.")");
    }
    
    protected function updateSeek($id,$seek = -1) {
        $seek = $seek == -1 ? ftell($this->cache[$id]["fp"]) : $seek;
        
        $this->cache[$id]["seek"] = $seek;

        if(!$this->cache[$id]["local"]) {
        	$query = \OCP\DB::prepare("UPDATE *PREFIX*freplicate set seek=? WHERE freplicate_id=? AND server_id=?");
        	$query->execute(array($seek,$id,$this->serverId));
        }
    }
    
    public function fopen($path,$mode,$onlyLocal = false) {
        error_log("SCLocal::fopen($path,$mode) datadirectory=".$this->datadirectory);

        $fp = fopen($this->datadirectory."/".$path, $mode);
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
	    		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`) VALUES (?,?,?,?)");
    			if($result = $query->execute(array($path,$seek,$mode,$this->serverId))) {
		    	    $id = \OCP\DB::insertid();
			        error_log("insert: " . $result . " id: " . $id);
		    	    $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => false);
        			return $id;
    			}
            }
        }
        return null;
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
        error_log("SCLocal::fclose(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
        unset($this->cache[$id]);
        return $res;
    }
    
    public function opendir($path,$onlyLocal = false) {
        error_log("SCLocal::opendir($path) datadirectory=".$this->datadirectory);
        $fp = opendir($this->datadirectory."/".$path);
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
	    		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`) VALUES (?,?,?,?)");
    			if($result = $query->execute(array($path,$seek,$mode,$this->serverId))) {
		    	    $id = \OCP\DB::insertid();
			        error_log("insert: " . $result . " id: " . $id);
		    	    $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode, "serverId" => $this->serverId, "local" => false);
        			return $id;
    			}
            }
        }
        return null;
    }

    public function readdir($id) {
        $fpinfo = $this->getFPInfo($id);
        $res = readdir($fpinfo["fp"]);
        $this->updateSeek($id,$this->cache[$id]["seek"]+1);
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
        error_log("SCLocal::closedir(".$fpinfo["path"].") datadirectory=".$this->datadirectory.", id=".$id);
        unset($this->cache[$id]);
        return $res;
    }

    
    public function getMetaData($id) {
        $fpinfo = $this->getFPInfo($id);
        return stream_get_meta_data($fpinfo["fp"]);
    }
    
    public function url_stat($_path) {
        $msg = "SCLocal::url_stat($_path) datadirectory=".$this->datadirectory;
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
        $msg = "SCLocal::touch($_path, $time, $atime) datadirectory=".$this->datadirectory;
        $path = $this->datadirectory."/".$_path;

        $args = array($path);
        if($time > 0) {
            $args[]= $time;
        }
        if($atime > 0) {
            $args[]= $atime;
        }
        $res = call_user_func_array("touch", $args);
        error_log($msg." = ".$res);
        return $res;
    }
    
    public function mkdir($_path) {
        $path = $this->datadirectory."/".$_path;
        if(!file_exists($path)) {
            return mkdir($path);
        }
        return false;
    }

    public function unlink($_path) {
        error_log("SCLocal::unlink($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
    
    public function rmdir($_path) {
        error_log("SCLocal::rmdir($_path) datadirectory=".$this->datadirectory);
        $path = $this->datadirectory."/".$_path;
        if(file_exists($path)) {
            return rmdir($path);
        }
        return false;
    }
};