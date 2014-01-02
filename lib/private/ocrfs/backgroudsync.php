<?php
namespace OC\OCRFS;

class BackgroudSync extends \OC\BackgroundJob\TimedJob {
    public function __construct() {
    }
    
    protected function run($argument) {
        /*
        Log::debug("BackgroudSync run");
        if(!Manager::getInstance()->isMaster()) {
            $master = Manager::getInstance()->getRandomMaster();
            $master->startBackgroudSync();
        }
        else {
            $this->startBackgroudSync();
        }
        */
    }
    
    protected function mergeList($lists) {
        $newList = array();
        foreach($lists AS $key => $list) {
            for($i=0;$i<count($list);$i++) {
                $entry = HashManager::createEntryByLine($list[$i]);
                $entry["index"] = $key;
                
                if(!array_key_exists($entry["name"], $newList)) {
                    $newList[$entry["name"]] = array($entry);
                }
                else {
                    $newList[$entry["name"]][] = $entry;
                }
            }
        }
        
        return $newList;
    }
    
    protected function getNewestEntry($entries) {
        $entry = $entries[0];
        for($i=1;$i<count($entries);$i++) {
            if($entries[$i]["time"] < $entry["time"]) {
                $entry = $entries[$i];
            }
        }
        
        return $entry;
    }
    
    public function syncFile($path, $newest, $srcServer, $dstServer) {
        Log::debug("sync $path");
        if(!$dstServer->syncFile($path, $srcServer->getServerId(), $time)) {
            Log::debug("sync failed");
            exit;
        }
        return true;
    }
    
    protected function cmpPath($path, $serverList) {
        $lists = array();
        for($i=0;$i<count($serverList);$i++) {
            if($serverList[$i]->getType() != "client") {
                $list = $serverList[$i]->getHashList($path);
                if($list) {
                    $lists[$i] = explode("\n", $list);
                }
                else {
                    $lists[$i] = array();
                }
            }
        }
        
        $newList = $this->mergeList($lists);
        $change = false;
        $hash = $newList["."][0]["hash"];
        
        foreach($newList AS $epath => $entries) {
            if($epath === ".") {
                foreach($entries AS $entry) {
                    if($hash != $entry["hash"]) {
                        Log::debug("Hash missmatch, directory has changes. ".$hash." != ".$entry["hash"]);
                        $change = true;
                    }
                }
            }
        }
        
        if(!$change) {
            Log::debug("No changes found.");
            return;
        }

        foreach($newList AS $epath => $entries) {
            $newest = $this->getNewestEntry($entries);
            if($epath === "." && $newest["type"] == "s") continue;
            Log::debug("check $epath");
            
            $changeDir = false;

            for($i=0;$i<count($serverList);$i++) {
                if($serverList[$i]->getType() != "client") {
                    $found = null;
                    foreach($entries AS $entry) {
                        if($entry["index"] == $i) {
                            $found = $entry;
                        }
                    }
                    
                    $rm = $serverList[$i]->setRM(-1);
                    if($found === null) {
                        if($newest["type"] != "r") {
                            Log::debug("Missing $path/$epath @ ".$serverList[$i]->getUrl());
                            if($newest["type"] == "d") {
                                if(!$serverList[$i]->mkdir($path."/".$epath, $newest["time"], $newest["time"])) {
                                    Log::debug("Failed mkdir");
                                    exit;
                                }
                                $changeDir = true;
                            }
                            else if($newest["type"] == "f") {
                                if(!$this->syncFile($path."/".$epath, $newest, $serverList[$newest["index"]], $serverList[$i])) {
                                    Log::debug("Copy file");
                                    exit;
                                }
                            }
                            else {
                                throw new \Exception("Unknown type ".$newest["type"]);
                            }
                        }
                    }
                    else if($newest["type"] == "r") {
                        if($found["type"] != "r") {
                            if($found["type"] == "d") {
                                Log::debug("Rmdir $path/$epath @ ".$serverList[$i]->getUrl());
                                if(!$serverList[$i]->rmdir($path."/".$epath, true)) {
                                    Log::debug("Failed rmdir");
                                    exit;
                                }
                            }
                            else if($found["type"] == "f") {
                                Log::debug("Unlink $path/$epath @ ".$serverList[$i]->getUrl());
                                if(!$serverList[$i]->unlink($path."/".$epath)) {
                                    Log::debug("Failed unlink");
                                    exit;
                                }
                            }
                            else {
                                throw new \Exception("Unknown type ".$found["type"]);
                            }
                        }
                    }
                    else {
                        if($found["type"] != $newest["type"]) {
                            if($found["type"] == "d") {
                                Log::debug("Rmdir $path/$epath @ ".$serverList[$i]->getUrl());
                                if(!$serverList[$i]->rmdir($path."/".$epath, true)) {
                                    Log::debug("Failed rmdir");
                                    exit;
                                }
                                if(!$this->syncFile($path."/".$epath, $newest, $serverList[$newest["index"]], $serverList[$i])) {
                                    Log::debug("Copy file");
                                    exit;
                                }
                            }
                            else if($found["type"] == "f") {
                                Log::debug("Unlink $path/$epath @ ".$serverList[$i]->getUrl());
                                if(!$serverList[$i]->unlink($path."/".$epath)) {
                                    Log::debug("Failed unlink");
                                    exit;
                                }
                                if(!$serverList[$i]->mkdir($path."/".$epath, $newest["time"], $newest["time"])) {
                                    Log::debug("Failed mkdir");
                                    exit;
                                }
                                $changeDir = true;
                            }
                            else {
                                throw new \Exception("Unknown type ".$found["type"]);
                            }
                        }
                        else if($newest["type"] == "d") {
                            $changeDir = true;
                        }
                        else if($newest["type"] == "f") {
                            if($newest["hash"] != $found["hash"]) {
                                Log::debug("file hash missmatch ".$path."/".$epath);
                                exit;
/*
                                if(!$this->syncFile($path."/".$epath, $newest, $serverList[$newest["index"]], $serverList[$i])) {
                                    Log::debug("Copy file");
                                    exit;
                                }
*/
                            }
                        }
                        else {
                            throw new \Exception("Unknown type ".$newest["type"]);
                        }
                    }
                    $serverList[$i]->setRM($rm);
                }
            }
            
            if($changeDir) {
                $this->cmpPath($path."/".$epath, $serverList);
            }
        }
    }
    
    public function startBackgroudSync() {
        Log::debug("startBackgroudSync");
        set_time_limit(0);
        
//        $serverId = Manager::getInstance()->getReplicationServerId();
        $serverList = Manager::getInstance()->getServerList();
        
        $this->cmpPath("/", $serverList);
    }
    
    public function testRun() {
        $this->run(null);
    }
}