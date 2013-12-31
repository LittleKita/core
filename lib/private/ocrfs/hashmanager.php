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
    
    protected function getHashByDirAndName($dir, $name, $all = false) {
        $path = $this->dataDirectory."/".$dir."/.ocrfs";
        if(is_readable($path)) {
            $fp = fopen($path,"r");
            if(is_resource($fp)) {
                $res = array();
                $first = true;
                while(($entry = fgets($fp)) !== false) {
                    list($hash,$time,$ename) = explode("\t", str_replace("\n","",$entry), 3);
                    if($first && $ename != ".") {
                        throw new \Exception("Read: First entry is not '.'! ($ename)");
                    }
                    $first = false;
                    if($all) {
                        $res[] = array("hash" => $hash, "time" => $time, "name" => $ename, "check" => false);
                    }
                    else if(!$all && $ename === $name) {
                        fclose($fp);
                        return array("hash" => $hash, "time" => $time, "name" => $ename, "check" => false);
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
        $p = $this->dataDirectory."/".$dir."/.ocrfs";
        if($hashes[0]["name"] != ".") {
            throw new \Exception("Write First entry is not '.'! (".$hashes[0]["name"].")");
        }
        usort($hashes, array($this,"hash_sort"));
        if($hashes[0]["name"] != ".") {
            throw new \Exception("WriteSort First entry is not '.'! (".$hashes[0]["name"].")");
        }

        $fp = fopen($p, "w");
        if(is_resource($fp)) {
            $md5 = "";
            for($i=1;$i<count($hashes);$i++) {
                $md5 .= $hashes[$i]["hash"];
            }
            $md5 = md5($md5);
            $hashes[0]["hash"] = $md5;
            $hashes[0]["time"] = time();
            
            for($i=0;$i<count($hashes);$i++) {
                fwrite($fp, $hashes[$i]["hash"]."\t".$hashes[$i]["time"]."\t".$hashes[$i]["name"]);
                if($i+1 < count($hashes)) {
                    fwrite($fp, "\n");
                }
            }
            fclose($fp);
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
        }
        else if($found["hash"] !== $hash["hash"]) {
            $found = $hash;
            $change = true;
        }
        
        if($change) {
            return $this->writeDirectoryHashes($dir, $oldHash);
        }

        return null;
    }
    
    protected function updateHashByDirAndFile($dir, $name) {
        $md5 = md5_file($this->dataDirectory."/".$dir."/".$name);
        $hash = array("hash" => $md5, "time" => time(), "name" => $name);

        return $this->updateHashByDirAndNameAndHash($dir, $name, $hash);
    }
    
    public function getHashByPath($path) {
        $path = $this->dataDirectory."/".Helper::normalizePath($path);

        if(is_file($path)) {
            $dirname = dirname($path);
            $basename = basename($path);
            return getHashByDirAndName($dirname, $basename);
        }
        else if(is_dir($path)) {
            return getHashByDirAndName($path, ".");
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
                    $change = true;
                    $md5 = md5("");
                    $hashes = array(array("hash" => $md5, "time" => time(), "name" => ".", "check" => true));
                    Log::debug("new start $path $md5 ".$hashes[0]["name"]);
                }
                else {
                    $hashes[0]["check"] = true;
                    $change = false;
                }
                
                while(($entry = $dir->read()) !== false) {
                    if($entry == "." || $entry == ".." || $entry == ".ocrfs") continue;

                    $found = false;
                    for($i=0;$i<count($hashes);$i++) {
                        if($hashes[$i]["name"] === $entry) {
                            $hashes[$i]["check"] = true;
                            $found = $i;
                            break;
                        }
                    }
                    $p = $realpath."/".$entry;
                    if($found === false) {
                        $change = true;
                        if(is_file($p)) {
                            $md5 = md5_file($p);
                            $hash = array("hash" => $md5, "time" => time(), "name" => $entry, "check" => true);
                            $hashes[] = $hash;
                            Log::debug("new fhash $path $md5 ".$hash["name"]);
                        }
                        else if(is_dir($p)) {
                            $hash = $this->getHashByDirAndName($path."/".$entry,".");
                            if($hash === null || $recursive) {
                                $hash = $this->updateHashByPath($path."/".$entry, $recursive);
                            }
                            $hash["name"] = $entry;
                            $hash["check"] = true;

                            Log::debug("new dhash $path ".$hash["hash"]." ".$hash["name"]);
                            $hashes[] = $hash;
                        }
                        else {
                            throw new \Exception("Unknonw file type $p");
                        }
                    }
                    else {
                        if(is_file($p)) {
                            $stat = stat($p);
                            if($stat["mtime"] > $hashes[$found]["time"] || $stat["ctime"] > $hashes[$found]["time"]) {
                                $change = true;
                                $md5 = md5_file($p);
                                $hash = array("hash" => $md5, "time" => time(), "name" => $entry, "check" => true);
                                $hashes[$found] = $hash;
                                Log::debug("upd fhash $path $md5 ".$hash["name"]." *** ".$stat["mtime"]." ".$stat["ctime"]." > ".$hashes[$found]["time"]);
                            }
                        }
                        else if(is_dir($p)) {
                            if($recursive) {
                                $hash = $this->updateHashByPath($path."/".$entry, $recursive);
                            }
                            else {
                                $hash = $this->getHashByDirAndName($path."/".$entry,".");
                            }
                            if($hash["hash"] != $hashes[$found]["hash"] || $hash["time"] > $hashes[$found]["time"]) {
                                $change = true;
                                $hashes[$found]["hash"] = $hash["hash"];
                                $hashes[$found]["time"] = $hash["time"];
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
                $nhashes = array();
                for($i=0;$i<count($hashes);$i++) {
                    if($hashes[$i]["check"]) {
                        $nhashes[] = $hashes[$i];
                    }
                    else {
                        $change = true;
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
        else {
            throw new \Exception("Unknonw file type $path");
        }
    }
};