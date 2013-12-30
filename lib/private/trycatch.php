<?php
namespace OC;

use OC\OCRFS\Manager;

class TryCatch implements TryCatchHandler {
    protected $oldErrorHandler = null;
    protected $oldExceptionHandler = null;
    protected $catcher = null;

    protected function __construct(TryCatch $catcher = null) {
        $this->catcher = $catcher;
        set_error_handler(array($this, "errorHandler"));
        set_exception_handler(array($this, "exceptionHandler"));
    }
    
    public function exceptionHandler(\Exception $e) {
        $id = Manager::getInstance()->getReplicationServerId();
        error_log($id."\t".$e->getMessage());
        $call = true;
        if($this->catcher != null) {
            $call = call_user_func_array(array($this->catcher, "exceptionHandler"), func_get_args());
        }
        if($call !== false && $this->oldExceptionHandler != null) {
            call_user_func_array($this->oldExceptionHandler, func_get_args());
        }
    }

    public function errorHandler($errno , $errstr , $errfile = null, $errline = null, $errcontext = null) {
        $id = Manager::getInstance()->getReplicationServerId();
        error_log($id."\t".$errfile.":".$errline." ($errno)$errstr");
        $call = true;
        if($this->catcher != null) {
            $call = call_user_func_array(array($this->catcher, "errorHandler"), func_get_args());
        }
        if($call !== false && $this->oldErrorHandler != null) {
            call_user_func_array($this->oldErrorHandler, func_get_args());
        }
    }
    
    public function c($res) {
        restore_error_handler();
        restore_exception_handler();
        return $res;
    }
    
    public static function tryCatch($arg = null) {
        return new TryCatch($arg);
    }
    
    public static function dummy() {
    }
};

interface TryCatchHandler {
    public function exceptionHandler(\Exception $e);
    public function errorHandler($errno , $errstr , $errfile = null, $errline = null, $errcontext = null);
};

function tryCatch($arg = null) {
    return TryCatch::tryCatch($arg);
}

function tc($arg = null) {
    return TryCatch::tryCatch($arg);
}