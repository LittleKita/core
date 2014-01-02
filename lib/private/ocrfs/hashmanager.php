<?php
namespace OC\OCRFS;

class HashManager implements OCRFSSync {
    protected static $instance = null;
    
    protected $dataDirectory = null;
    
    protected function __construct($dataDirectory) {
        $this->dataDirectory = $dataDirectory;
    }
    
    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new HashManager(Manager::getInstance()->getDataDirectory());
        }
        return self::$instance;
    }
    
    public function getHashList($dir) {
        $path = $this->dataDirectory."/".Helper::normalizePath($dir)."/.ocrfs";
        $fp = @fopen($path,"r");
        $data = null;
        if(is_resource($fp)) {
            $data = "";
            while(!feof($fp)) {
                $data .= fread($fp,1024);
            }
            fclose($fp);
        }
        return $data;
    }
    
    public static function createEntryByLine($line) {
        list($etype,$hash,$time,$ename) = explode("\t", str_replace("\n","",$line), 4);
        return array("hash" => $hash, "type" => $etype, "time" => $time, "name" => $ename, "check" => false);
    }
    
    protected function getHashByDirAndName($dir, $name, $all = false) {
        $path = $this->dataDirectory."/".$dir."/.ocrfs";
        if(is_readable($path)) {
            $fp = fopen($path,"r");
            if(is_resource($fp)) {
                $res = array();
                $first = true;
                while(($entry = fgets($fp)) !== false) {
                    list($etype,$hash,$time,$ename) = explode("\t", str_replace("\n","",$entry), 4);
                    if($first && $ename != ".") {
                        throw new \Exception("Read: First entry is not '.'! ($ename)");
                    }
                    $first = false;
                    if($all) {
                        $res[] = array("hash" => $hash, "type" => $etype, "time" => $time, "name" => $ename, "check" => false);
                    }
                    else if(!$all && $ename === $name) {
                        fclose($fp);
                        return array("hash" => $hash, "type" => $etype, "time" => $time, "name" => $ename, "check" => false);
                    }
                }
                fclose($fp);
                
                if(count($res) > 0) {
                    if($res[0]["name"] != ".") {
                        throw new \Exception("ReadRes: First entry is not '.'! (".$res[0]["name"].")");
                    }
                    return $res;
                }
            }
        }
        else {
            Log::debug("$path not found");
        }
        return null;
    }
    
    public function hash_sort($a, $b) {
        if($a["name"] === $b["name"]) {
            throw new \Exception("Double name ".$a["name"]);
        }
        if($a["name"] == ".") {
            return -1;
        }
        else if($b["name"] == ".") {
            return +1;
        }
        else {
            return strcmp($a["name"], $b["name"]);
        }
    }
    
    protected function writeDirectoryHashes($dir, &$hashes) {
        $dp = $this->dataDirectory."/".$dir;
        $p = $dp."/.ocrfs";
        if($hashes[0]["name"] != ".") {
            throw new \Exception("Write First entry is not '.'! (".$hashes[0]["name"].")");
        }
        usort($hashes, array($this,"hash_sort"));
        if($hashes[0]["name"] != ".") {
            throw new \Exception("WriteSort First entry is not '.'! (".$hashes[0]["name"].")");
        }

        $statTime = $this->getStatTime($dir);

        $fp = fopen($p, "w");
        if(is_resource($fp)) {
            // Create SUM-HASH for alle Children:
            {
                $md5 = "";
                for($i=0;$i<count($hashes);$i++) {
                    // Only existing Directories and Files
                    if($hashes[$i]["type"] == "d" || $hashes[$i]["type"] == "f") {
                        $md5 .= $hashes[$i]["hash"];
                    }
                }
                $md5 = md5($md5);
                $hashes[0]["hash"] = $md5;
                $hashes[0]["time"] = $statTime;
            }
            
            // Write hashes to .ocrfs File
            for($i=0;$i<count($hashes);$i++) {
                fwrite($fp, $hashes[$i]["type"]."\t".$hashes[$i]["hash"]."\t".$hashes[$i]["time"]."\t".$hashes[$i]["name"]);
                if($i+1 < count($hashes)) {
                    fwrite($fp, "\n");
                }
            }
            fflush($fp);
            fclose($fp);

            touch($p, $statTime, $statTime);
            touch($dp, $statTime, $statTime);

            return $hashes[0];
        }
        return null;
    }
    
    protected function updateHashByDirAndNameAndHash($dir, $name, $hash) {
        $oldHash = $this->getHashByDirAndName($dir, $name, true);
        $found = null;
        for($i=0;$i<count($oldHash);$i++) {
            if($oldHash[$i]["name"] === $name) {
                $found = &$oldHash[$i];
                break;
            }
        }
        $change = false;
        if($found === null) {
            $oldHash[] = $hash;
            $change = true;
            if($hash["hash"] === null) {
                throw new \Exception("Missing hash $dir, $name");
            }
        }
        else if($found["hash"] !== $hash["hash"] || $found["type"] !== $hash["type"]) {
            if($hash["hash"] === null) {
                $hash["hash"] = $found["hash"];
            }
            $found = $hash;
            $change = true;
        }
        
        if($change) {
            return $this->writeDirectoryHashes($dir, $oldHash);
        }

        return null;
    }
    
    protected function updateHashByDirAndFile($dir, $name) {
        if(file_exists($this->dataDirectory."/".$dir."/".$name)) {
            $md5 = md5_file($this->dataDirectory."/".$dir."/".$name);
            $type = "f";
            $time = $this->getStatTime($dir."/".$name);
        }
        else {
            $md5 = null;
            $type = "r";
            $time = time();
            throw new \Exception("Unimplemented");
        }
        
        $hash = array("hash" => $md5, "type" => $type, "time" => $time, "name" => $name);

        return $this->updateHashByDirAndNameAndHash($dir, $name, $hash);
    }
    
    protected function getStatTime($path) {
        $path = $this->dataDirectory."/".Helper::normalizePath($path);
        clearstatcache(true, $path);
        $stat = stat($path);
        Log::debug("getStatTime $path ".date("r", $stat["mtime"])." ".date("r", $stat["atime"]). " ".$stat["mtime"]." ".$stat["atime"]);
        return $stat["mtime"];
    }
    
    public function getHashByPath($path) {
        $p = $this->dataDirectory."/".Helper::normalizePath($path);

        if(is_file($p)) {
            $dirname = dirname($path);
            $basename = basename($path);
            return getHashByDirAndName($dirname, $basename);
        }
        else if(is_dir($p)) {
            return getHashByDirAndName($path, ".");
        }
        else {
            throw new \Exception("File not found $path");
        }
    }

    public function getHashListByDir($path) {
        $path = $this->dataDirectory."/".Helper::normalizePath($path);
        
        if(is_dir($path)) {
            return getHashByDirAndName($path, ".", true);
        }
        else {
            throw new \Exception("Direction '$path' not found.");
        }
    }
    
    public function clearOCRFS($path = "/") {
        $path = Helper::normalizePath($path);
        $paths = array($path);
        for($i=0;$i<count($paths);$i++) {
            $dir = dir($this->dataDirectory."/".$paths[$i]);
            if(is_object($dir)) {
                while(($entry = $dir->read()) !== false) {
                    $p = $paths[$i]."/".$entry;
                    if($entry == "." || $entry == ".." || !is_dir($this->dataDirectory."/".$p)) continue;
                    $paths[] = $p;
                }
                $dir->close();
                @unlink($this->dataDirectory."/".$paths[$i]."/.ocrfs");
            }
        }
    }
    
    public function updateHashByPath($path, $recursive = false) {
        Log::debug("check $path ".$recursive*1);
        $path = Helper::normalizePath($path);
        $dirname = dirname($path);
        $basename = basename($path);
        $isRoot = (dirname($path) === "/");
        $realpath = $this->dataDirectory."/".$path;

        if(is_file($realpath)) {
            $dirHash = $this->updateHashByDirAndFile(dirname($path), $basename);
            if($dirHash !== null) {
                Log::debug("upd fhash $dirname ................................ ".$basename);
                Log::debug("upd dhash $dirname ".$dirHash["hash"]." ".$dirHash["name"]);
            }
            while(!$isRoot && $dirHash !== null) {
                $basename = basename($dirname);
                $dirname = Helper::normalizePath($dirname."/..");
                $isRoot = ($dirname === "/");
                $dirHash["name"] = $basename;

                $dirHash = $this->updateHashByDirAndNameAndHash($dirname, $basename, $dirHash);
                if($dirHash !== null) {
                    Log::debug("upd dhash $dirname ................................ ".$basename);
                    Log::debug("upd dhash $dirname ".$dirHash["hash"]." ".$dirHash["name"]);
                }
            }
            return null;
        }
        else if(is_dir($realpath)) {
            $hashes = $this->getHashByDirAndName($path, ".", true);
            $dir = dir($realpath);
            if(is_object($dir)) {
                if($hashes === null) {
                    $statTime = $this->getStatTime($path);
                    $change = true;
                    $md5 = md5("");
                    $hashes = array(array("hash" => $md5, "type" => "s", "time" => $statTime, "name" => ".", "check" => true));
                    Log::debug("new start $path $md5 ".$hashes[0]["name"]);
                }
                else {
                    $hashes[0]["check"] = true;
                    $change = false;
                }
                
                while(($entry = $dir->read()) !== false) {
                    if($entry == "." || $entry == ".." || $entry == ".ocrfs" || $entry == ".ocrfsTrash") continue;

                    $found = false;
                    for($i=0;$i<count($hashes);$i++) {
                        if($hashes[$i]["name"] === $entry) {
                            $hashes[$i]["check"] = true;
                            $found = $i;
                            break;
                        }
                    }
                    $p = $realpath."/".$entry;
                    $statTime = $this->getStatTime($path."/".$entry);
                    if($found === false) {
                        $change = true;
                        if(is_file($p)) {
                            // No root files include!
                            if($path !== "/") {
                                $md5 = md5_file($p);
                                $hash = array("hash" => $md5, "type" => "f", "time" => $statTime, "name" => $entry, "check" => true);
                                $hashes[] = $hash;
                                Log::debug("new fhash $path $md5 ".$hash["name"]);
                            }
                        }
                        else if(is_dir($p)) {
                            $hash = $this->getHashByDirAndName($path."/".$entry,".");
                            if($hash === null || $recursive) {
                                $hash = $this->updateHashByPath($path."/".$entry, $recursive);
                            }
                            $hash["type"] = "d";
                            $hash["name"] = $entry;
                            $hash["check"] = true;
                            $hash["time"] = $statTime;

                            Log::debug("new dhash $path ".$hash["hash"]." ".$hash["name"]);
                            $hashes[] = $hash;
                        }
                        else {
                            throw new \Exception("Unknonw file type $p");
                        }
                    }
                    else {
                        if(is_file($p)) {
                            if($statTime > $hashes[$found]["time"] || $hashes[$found]["type"] == "r") {
                                $change = true;
                                $md5 = md5_file($p);
                                $hash = array("hash" => $md5, "type" => "f", "time" => $statTime, "name" => $entry, "check" => true);
                                $hashes[$found] = $hash;
                                Log::debug("upd fhash $path $md5 ".$hash["name"]." *** ".$statTime." > ".$hashes[$found]["time"]);
                            }
                        }
                        else if(is_dir($p)) {
                            if($recursive) {
                                $hash = $this->updateHashByPath($path."/".$entry, $recursive);
                            }
                            else {
                                $hash = $this->getHashByDirAndName($path."/".$entry,".");
                            }
                            $hash["type"] = "d";
                            if($hash["hash"] != $hashes[$found]["hash"] || $hash["time"] > $hashes[$found]["time"] || $hash["type"] != $hashes[$found]["type"]) {
                                $change = true;
                                $hashes[$found]["hash"] = $hash["hash"];
                                $hashes[$found]["time"] = $hash["time"];
                                $hashes[$found]["type"] = $hash["type"];
                                $hashes[$found]["check"] = true;
                                Log::debug("upd dhash $path ".$hash["hash"]." ".$hash["name"]);
                            }
                        }
                        else {
                            throw new \Exception("Unknonw file type $p");
                        }
                    }
                }
                $dir->close();
                
                // Are dir-entries deleted?
                $statTime = $this->getStatTime($path);
                $nhashes = array();
                for($i=0;$i<count($hashes);$i++) {
                    if($hashes[$i]["check"]) {
                        $nhashes[] = $hashes[$i];
                    }
                    else if($hashes[$i]["type"] != "r") {
                        $change = true;
                        $hashes[$i]["type"] = "r";
                        $hashes[$i]["time"] = $statTime;
                        $nhashes[] = $hashes[$i];
                        Log::debug("upd rhash $path ".$hashes[$i]["hash"]." ".$hashes[$i]["name"]);
                    }
                }
                
                if($change) {
                    Log::debug("upd ocrfs $path");
                    return $this->writeDirectoryHashes($path, $nhashes);
                }
                else {
                    return $hashes[0];
                }
            }
        }
        else if(!file_exists($realpath)) {
            return $this->updateHashByPath($dirname, $recursive);
        }
        else {
            throw new \Exception("Unknonw file type $path");
        }
    }
};