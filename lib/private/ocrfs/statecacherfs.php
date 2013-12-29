<?php
namespace OC\OCRFS;

interface StateCacheRFS {
    public function setRM($rm);
    public function getServerId();
    
    public function checkPath($path);

    public function fopen($path,$mode,$onlyLocal = false);
    public function fread($fp,$size);
    public function fwrite($fp,$data);
    public function fstat($fp);
    public function feof($fp);
    public function fflush($fp);
    public function fclose($fp);

    public function opendir($path,$onlyLocal = false);
    public function readdir($fp);
    public function rewinddir($fp);
    public function closedir($fp);

    public function getMetaData($fp);
    public function url_stat($path);
    
    public function touch($path, $time = null, $atime = null);
    public function mkdir($path);

    public function unlink($path);
    public function rmdir($path);
};