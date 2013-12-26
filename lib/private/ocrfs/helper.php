<?php
declare(ticks=1);
namespace OC\OCRFS;

class Helper {
    public static function getSingleTrace($trace) {
        $msg = "\tat ";
        if(array_key_exists("file", $trace)) {
            $msg .= basename($trace["file"]);
        }
        else {
            $msg .= "<none>";
        }
        if(array_key_exists("line", $trace)) {
            $msg .= ":".$trace["line"];
        }
        
        $msg .= "\t";
        
        if(array_key_exists("class", $trace)) {
            $msg .= $trace["class"];
            $msg .= $trace["type"];
        }
        if(array_key_exists("function", $trace)) {
            $msg .= $trace["function"];
        }
        else {
            $msg .= "<none>";
        }
        
        if(array_key_exists("args", $trace)) {
            $msg .= "(";
            for($i=0;$i<count($trace["args"]);$i++) {
                if($i > 0) {
                    $msg .= ",";
                }
                $type = gettype($trace["args"][$i]);
                if($type == "string") {
                    $msg .= "\"".addslashes($trace["args"][$i])."\"";
                }
                else if($type == "boolean") {
                    if($trace["args"][$i] === true) {
                        $msg .= "true";
                    }
                    else {
                        $msg .= "false";
                    }
                }
                else if($type == "NULL") {
                    $msg .= "null";
                }
                else if($type == "resource") {
                    $msg .= $trace["args"][$i];
                }
                else if($type == "object") {
                    $msg .= "object(".get_class($trace["args"][$i]).")";
                }
                else if($type == "array") {
                    $msg .= "array(".count($trace["args"][$i]).")";
                }
                else {
                    $msg .= $trace["args"][$i];
                }
            }
            $msg .= ")";
        }
        
        $msg .= "\r\n";
        return $msg;
    }
    
    public static function getLastCallFunc() {
        $bt = debug_backtrace();
        $msg = "";
        
        $ignore = array("OC\OCRFS\Helper");
        for($index=0;$index<count($bt);$index++) {
            if(array_key_exists("class", $bt[$index]) && in_array($bt[$index]["class"], $ignore)) {
                continue;
            }
            $msg .= self::getSingleTrace($bt[$index]);
        }

        return $msg;
    }
    
    public static function logTrace() {
        error_log(self::getLastCallFunc());
    }
    
    public static function tick() {
        $bt = debug_backtrace();
        error_log("tick ".trim(self::getSingleTrace($bt[0])));
    }
    
    public static function enable_debug() {
        register_tick_function(array("OC\\OCRFS\\Helper","tick"));
    }
};