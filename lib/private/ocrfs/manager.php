<?php
namespace OC\OCRFS;

class Manager {
    protected static $instance = null;
    
    protected $dataDirectory = null;
	protected $replicationType = null;
	protected $replicationServerId = null;
	
	protected $replicationServer = null;
    
	protected $localServer = null;

    protected function __construct() {
		$this->dataDirectory = \OC_Config::getValue("replicationdatadirectory", \OC::$SERVERROOT . '/data');
		$this->replicationType = \OC_Config::getValue("replicationtype", "master");
    	$this->replicationServerId = \OC_Config::getValue("replicationserverid", "-1")/1;
    	
    	$this->replicationServer = array();
    	
    	$this->localServer = new SCLocal($this->replicationServerId,$this->dataDirectory);
    	
		$query = \OCP\DB::prepare("SELECT * FROM *PREFIX*server WHERE disabled!=1");
		$result = $query->execute(array());
		$this->replicationServer = array();
		while ($row = $result->fetchRow()) {
		    if($row["server_id"] != $this->replicationServerId) {
    		    $this->replicationServer[] = new SCRemote($row["server_id"], $row["url"], $row["type"], $row["secret"]);
		    }
    	}
    }
    
    public function getDataDirectory() {
        return $this->dataDirectory;
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
        $col = array();
        foreach($this->replicationServer AS $server) {
            if(!in_array($server->getServerId(),$ignore) && $server->getType() == "master") {
                $col[] = $server;
            }
        }
        
        $master = $col[mt_rand(0,count($col)-1)]; 
        if(!in_array($this->replicationServerId,$ignore) && !in_array(-1,$ignore)) {
            return new SCCollection($this->replicationServerId, array($this->localServer,$master));
        }
        return $master;
    }

    public function getCollection($ignore = array()) {
        $col = array();

        if(!in_array($this->replicationServerId,$ignore)) {
            $col[] = $this->localServer;
        }

        foreach($this->replicationServer AS $server) {
            if(!in_array($server->getServerId(),$ignore)) {
                $col[] = $server;
            }
        }
        
        if(count($col) == 1) {
            return $col[0];
        }
        return new SCCollection($this->replicationServerId, $col);
    }
};