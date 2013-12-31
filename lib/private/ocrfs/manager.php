<?php
namespace OC\OCRFS;

class Manager {
    protected static $instance = null;
    
    protected $dataDirectory = null;
	protected $replicationType = null;
	protected $replicationServerId = null;
	
	protected $replicationServer = null;
	protected $replicationServerFull = null;
    
	protected $localServer = null;
	
	protected $rand = null;

    protected function __construct() {
		$this->dataDirectory = \OC_Config::getValue("replicationdatadirectory", \OC::$SERVERROOT . '/data');
		$this->replicationType = \OC_Config::getValue("replicationtype", "master");
    	$this->replicationServerId = \OC_Config::getValue("replicationserverid", "-1")/1;
    	
    	$this->replicationServer = array();
    	
    	if(!$this->isClient()) {
        	$this->localServer = new SCLocal($this->replicationServerId,$this->dataDirectory);
    	}
    	
		$query = \OCP\DB::prepare("SELECT * FROM *PREFIX*server WHERE disabled!=1");
		$result = $query->execute(array());
		$this->replicationServer = array();
		$this->replicationServerFull = array();
		while ($row = $result->fetchRow()) {
		    $scremote = new SCRemote($row["server_id"], $row["url"], $row["type"], $row["secret"], $this->replicationServerId);
		    if($row["server_id"] != $this->replicationServerId) {
    		    $this->replicationServer[] = $scremote;
		    }
		    $this->replicationServerFull[] = $scremote;
    	}
    	
    	// Alle fileHandles die aelter als 60 Sekunden sind loeschen:
    	$query = \OCP\DB::prepare("DELETE FROM *PREFIX*freplicate WHERE ltime<?");
    	if(false && $query->execute(array(time()-360))) {
        	$query = \OCP\DB::prepare("SELECT count(*) AS c FROM *PREFIX*freplicate");
        	$result = $query->execute(array());
        	$count = -1;
        	while ($row = $result->fetchRow()) {
        	    $count = $row["c"];
        	}
        	
        	if($count == 0) {
        	    Log::debug("TRUNCATE freplicate");
        	    $query = \OCP\DB::prepare("TRUNCATE TABLE *PREFIX*freplicate");
        	    $query->execute(array());
        	}
    	}
    }
    
    public function getServerList() {
        return $this->replicationServerFull;
    }
    
    public function getDataDirectory() {
        return $this->dataDirectory;
    }
    
    public function getReplicationServerId() {
        return $this->replicationServerId;
    }
    
    public static function hasInstance() {
        return self::$instance !== null;
    }
    
    public static function getInstance() {
        if(self::$instance == null) {
            self::$instance = new Manager();
        }
        
        return self::$instance;
    }
    
    public function getLocal() {
        return $this->localServer;
    }
    
    public function isMaster() {
        return $this->replicationType == "master";
    }
    
    public function isClient() {
        return $this->replicationType == "client";
    }

    public function getRandomMaster($ignore = array()) {
        if($this->rand == null || count($ignore) > 0) {
            $col = array();
            foreach($this->replicationServer AS $server) {
                if(!in_array($server->getServerId(),$ignore) && $server->getType() == "master") {
                    $col[] = $server;
                }
            }
            
            $this->rand = $col[mt_rand(0,count($col)-1)];
            return $this->rand;
        }
        else {
            return $this->rand;
        }
    }

    public function getCollection($ignore) {
        $col = array();

        if(!in_array($this->replicationServerId,$ignore)) {
            $col[] = $this->localServer;
        }

        foreach($this->replicationServer AS $server) {
            if(!in_array($server->getServerId(),$ignore) && $server->getType() != "client") {
                $col[] = $server;
            }
        }
        
        return new SCCollection($this->replicationServerId, $col, -1);
    }
    
    public function getCollectionById($id) {
		$query = \OCP\DB::prepare("SELECT * FROM *PREFIX*freplicate WHERE freplicate_id=? AND server_id=?");
		if($result = $query->execute(array($id,$this->replicationServerId))) {
		    $res = null;
            while($row = $result->fetchRow()) {
                if($row["seek"] == -1) {
                    $col = array();
                    $row["path"] = unserialize($row["path"]);
                    foreach($row["path"] AS $serverId => $id) {
                        if($serverId == $this->replicationServerId) {
                            $col[] = $this->getLocal();
                        }
                        for($i=0;$i<count($this->replicationServer);$i++) {
                            if($this->replicationServer[$i]->getServerId() == $serverId) {
                                $col[] = $this->replicationServer[$i];
                            }
                        }
                    }
                    
                    $res = new SCCollection($this->replicationServerId, $col, "-1", $row);
                }
                else {
                    $res = $this->getLocal();
                }
            }
            
            if($res !== null) {
                return $res;
            }
        }
        
        throw new Exception("FP-ID not found. (id:".$id.")");
    }
};