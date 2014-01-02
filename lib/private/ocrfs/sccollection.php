<?php
namespace OC\OCRFS;

class SCCollection implements StateCacheRFS {
    protected $serverId = null;
    protected $collection = null;
    protected $cache = null;
    protected $rm = null;

    public function __construct($serverId, array $collection, $rm, $row = null) {
        $this->collection = array();
        foreach($collection as $sc) {
            $this->collection[$sc->getServerId()] = $sc;
        }
        $this->serverId = $serverId;
        $this->rm = $rm;
        $this->cache = array();
        
        if($row != null) {
            $this->cache[$row["freplicate_id"]] = array("fp" => null, "path" => $row["path"], "seek" => $row["seek"], "mode" => $row["mode"], "local" => false, "ltime" => $row["ltime"]);
            $this->checkLTime($row["freplicate_id"]);
        }
    }
    
    public function setRM($rm) {
    }

    public function getServerId() {
        return $this->serverId;
    }
    
    public function checkPath($path) {
        foreach($this->collection AS $serverId => $sc) {
            $sc->checkPath($path);
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
            $needUpdate = false;
            while($row = $result->fetchRow()) {
                $row["path"] = unserialize($row["path"]);
                $this->cache[$id] = $row;
            }
            
            if(array_key_exists($id,$this->cache)) {
                $this->checkLTime($id);
                return $this->cache[$id];
            }
        }
        
        throw new Exception("FP-ID not found. (id:".$id.")");
    }
    
    protected function callAll($collection,$func,$args,$single = false) {
        $res = array();
        if(!is_array($collection)) {
            $fpinfo = $this->getFPInfo($collection);
            foreach($fpinfo["path"] AS $serverId => $id) {
                $args[0] = $id;
                $oldRM = $this->collection[$serverId]->setRM($this->rm);
                $res[$serverId] = call_user_func_array(array($this->collection[$serverId],$func), $args);
                $this->collection[$serverId]->setRM($oldRM);
            }
            
            if($single) {
                $res = array_key_exists($this->serverId,$res) ? $res[$this->serverId] : array_pop($res);
            }
        }
        else {
            foreach($collection AS $serverId => $sc) {
                $oldRM = $sc->setRM($this->rm);
                $res[$serverId] = call_user_func_array(array($sc,$func), $args);
                $sc->setRM($oldRM);
            }

            if($single) {
                $res = array_key_exists($this->serverId,$res) ? $res[$this->serverId] : array_pop($res);
            }
        }
        return $res;
    }
    
    public function fopen($path,$mode,$onlyLocal = false, $data = null, $close = false, $time = null, $atime = null) {
        $tmode = str_replace("b","",$mode);
        if(strpos($tmode,"+")) {
            throw new Exception("Collection paralel read/write not implemented.");
        }
        else if($tmode == "r") {
            throw new Exception("Collection read not implemented.");
        }
        
        $seek = -1;
        $path = $this->callAll($this->collection,"fopen", func_get_args(), $close);
        
        if($close) {
            return $path;
        }

        if($onlyLocal) {
                while(array_key_exists($id = mt_rand(), $this->cache)) {
                }
                $this->cache[$id] = array("fp" => null, "path" => $path, "seek" => $seek, "mode" => $mode, "local" => true, "ltime" => time());
                return $id;
        }
        else {
    		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`,`ltime`) VALUES (?,?,?,?,?)");
    		if($result = $query->execute(array(serialize($path),$seek,$mode,$this->serverId,time()))) {
        	    $id = \OCP\DB::insertid();
        	    if(is_numeric($id)) {
        	        $id = $id/1;
        	    }
    //	        Log::debug("insert: " . $result . " id: " . $id);
        	    $this->cache[$id] = array("fp" => null, "path" => $path, "seek" => $seek, "mode" => $mode, "local" => false, "ltime" => time());
    			return $id;
    		}
        }
        return null;
    }
    
    public function fread($id,$size) {
        throw new Exception("Collection read not implemented. (id:".$id.")");
    }

    public function fwrite($id,$data) {
        return $this->callAll($id,"fwrite", func_get_args(), true);
    }

    public function fstat($id) {
        return $this->callAll($id,"fstat", func_get_args(), true);
    }

    public function feof($id) {
        return $this->callAll($id,"feof", func_get_args(), true);
    }

    public function fflush($id) {
        return $this->callAll($id,"fflush", func_get_args(), true);
    }

    public function fclose($id, $time = null, $atime = null) {
        $this->callAll($id,"fclose", func_get_args());

		$query = \OCP\DB::prepare("DELETE FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
		$query->execute(array($id,$this->serverId));
        unset($this->cache[$id]);
        return true;
    }
    
    public function opendir($path,$onlyLocal = false) {
        throw new Exception("Collection read not implemented.");
    }

    public function readdir($id) {
        throw new Exception("Collection read not implemented. (id:".$id.")");
    }

    public function rewinddir($id) {
        throw new Exception("Collection read not implemented. (id:".$id.")");
    }

    public function closedir($id) {
        throw new Exception("Collection read not implemented. (id:".$id.")");
    }
    
    public function getMetaData($id) {
        return $this->callAll($id,"getMetaData", func_get_args(), true);
    }

    public function url_stat($path) {
        throw new Exception("Collection read not implemented.");
    }
    
    public function touch($path, $time = null, $atime = null) {
        $args = array("path" => $path);
        if($time != null) {
            $args["time"] = $time;
        }
        if($atime != null) {
            $args["atime"] = $atime;
        }
        return array_pop($this->callAll($this->collection,"touch", $args));
    }

    public function mkdir($path, $time = null, $atime = null) {
        return $this->callAll($this->collection,"mkdir", func_get_args(), true);
    }
    
    public function unlink($path) {
        return $this->callAll($this->collection,"unlink", func_get_args(), true);
    }

    public function rmdir($path, $recursive = false) {
        return $this->callAll($this->collection,"rmdir", func_get_args(), true);
    }
    
    public function remove($path, $recursive = false) {
        return $this->callAll($this->collection,"remove", func_get_args(), true);
    }
};