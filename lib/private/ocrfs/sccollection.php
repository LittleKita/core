<?php
namespace OC\OCRFS;

class SCCollection implements StateCacheRFS {
    protected $serverId = null;
    protected $collection = null;
    protected $cache = null;

    public function __construct($serverId, array $collection) {
        $this->collection = array();
        foreach($collection as $sc) {
            $this->collection[$sc->getServerId()] = $sc;
        }
        $this->serverId = $serverId;
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
                $row["path"] = unserialize($row["path"]);
                $this->cache[$id] = $row;
            }
            
            if(array_key_exists($id,$this->cache)) {
                return $this->cache[$id];
            }
        }
        
        throw new Exception("FP-ID not found. (id:".$id.")");
    }
    
    public function fopen($path,$mode) {
        $seek = 0;
        $path = array();
        
        foreach($this->collection AS $serverId => $sc) {
            $id = $sc->fopen($path,$mode);
            $path[$serverId] = $id;
        }

		$query = \OCP\DB::prepare("INSERT INTO *PREFIX*freplicate (`path`,`seek`,`mode`,`server_id`) VALUES (?,?,?,?)");
		if($result = $query->execute(array(serialize($path),$seek,$mode,$this->serverId))) {
    	    $id = \OCP\DB::insertid();
	        error_log("insert: " . $result . " id: " . $id);
    	    $this->cache[$id] = array("fp" => $fp, "path" => $path, "seek" => $seek, "mode" => $mode);
			return $id;
		}
        return null;
    }

    public function fread($id,$size) {
        throw new Exception("Collection read not implemented. (id:".$id.")");
    }

    public function fwrite($id,$data) {
        $fpinfo = $this->getFPInfo($id);
        foreach($fpinfo["path"] AS $serverId => $id) {
            $res = $this->collection[$serverId]->fwrite($id,$data);
        }
        return $res;
    }

    public function fclose($id) {
        $fpinfo = $this->getFPInfo($id);
        foreach($fpinfo["path"] AS $serverId => $id) {
            $res = $this->collection[$serverId]->fclose($id);
        }

		$query = \OCP\DB::prepare("DELETE FROMN *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
		$query->execute(array($id,$this->serverId));
        unset($this->cache[$id]);
    }
};