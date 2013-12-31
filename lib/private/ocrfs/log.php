<?php
namespace OC\OCRFS;

class Log {
    protected static $loop = false;
    
    protected static function log($level, $text, $obj) {
        $loop = self::$loop;
        self::$loop = true;
        $dt = \debug_backtrace();
        $dobj = null;
        $file = "";
        $line = "";
        $first = true;
        $force = false;
        for($i=0;$i<count($dt);$i++) {
            if($first && $dt[$i]["file"] != __FILE__) {
                $first = false;
                if(array_key_exists("file", $dt[$i])) {
                    $file = $dt[$i]["file"];
                    if(array_key_exists("line", $dt[$i])) {
                        $line = $dt[$i]["line"];
                    }
                }
                else {
                    if(array_key_exists("line", $dt[$i])) {
                        $file = "?";
                        $line = $dt[$i]["line"];
                    }
                }
            }
            if(!array_key_exists("class", $dt[$i]) || $dt[$i]["class"] != "OC\OCRFS\Log"/* || $dt[$i]["file"] != __FILE__*/) {
                $dobj = $dt[$i];
                $dobj["file"] = $file;
                $dobj["line"] = $line;
                break;
            }
        }
        for($i=0;$i<count($dt);$i++) {
            unset($dt[$i]["args"]);
            unset($dt[$i]["object"]);
        }

        if($dobj === null) {
            $dobj = $dt[count($dt)-1];
            if(array_key_exists("class", $dobj) && $dobj["class"] == "OC\OCRFS\Log") {
                unset($dobj["class"]);
                unset($dobj["type"]);
                $dobj["function"] = "main";
            }
        }
        
        $dobj["file"] = basename($dobj["file"]);

        if(!$loop) {
            $id = \OC_Config::getValue("replicationserverid", "-1")/1;
        }
        else {
            $id = -1;
        }

        $msg = "";
        if($level == \OC_Log::DEBUG) {
            $msg .= "DEBUG($id): ";
        }
        else if($level == \OC_Log::INFO) {
            $msg .= "INFO ($id): ";
        }
        else if($level == \OC_Log::WARN) {
            $msg .= "WARN ($id): ";
        }
        else if($level == \OC_Log::ERROR) {
            $msg .= "ERROR($id): ";
        }
        else if($level == \OC_Log::FATAL) {
            $msg .= "FATAL($id): ";
        }
        
        if(array_key_exists("class", $dobj)) {
            $class = explode("\\", $dobj["class"]);
            $class = $class[count($class)-1];
            $msg .= $class;
        }
        if(array_key_exists("type", $dobj)) {
            $msg .= $dobj["type"];
        }
        if(array_key_exists("function", $dobj)) {
            $msg .= $dobj["function"];
        }
        $msg .= "(".$dobj["file"].":".$dobj["line"].")";
        $msg .= "\t$text";

        if(php_sapi_name() == "cli") {
            $fp = false;//fopen("??????", "a");
            if(is_resource($fp)) {
                $d = date("d-M-Y H:i:s");
                fwrite($fp, "$d $msg\n");
                fflush($fp);
                fclose($fp);
            }
            else {
                error_log($msg);
            }
        }
        else {
            error_log($msg);
        }

        self::$loop = false;
    }
    
    public static function debug($text, $obj = null) {
        return self::log(\OC_Log::DEBUG, $text, $obj);
    }

    public static function info($text, $obj = null) {
        return self::log(\OC_Log::INFO, $text, $obj);
    }

    public static function warn($text, $obj = null) {
        return self::log(\OC_Log::WARN, $text, $obj);
    }

    public static function error($text, $obj = null) {
        return self::log(\OC_Log::ERROR, $text, $obj);
    }

    public static function fatal($text, $obj = null) {
        return self::log(\OC_Log::FATAL, $text, $obj);
    }
}