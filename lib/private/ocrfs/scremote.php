<?php
namespace OC\OCRFS;

class SCRemote implements StateCacheRFS {
    protected $serverId;
    protected $url;
    protected $type;
    protected $secret;
    protected $rm;

    public function __construct($id, $url, $type, $secret, $rm) {
        $this->serverId = $id;
        $this->url = $url;
        $this->type = $type;
        $this->secret = $secret;
        if(!$rm/1) {
            throw new \Exception("Wrong rm($rm).");
        }
        $this->rm = $rm;
    }
    
    public function setRM($rm) {
        if(!$rm/1) {
            throw new \Exception("Wrong rm($rm).");
        }
        $oldRM = $this->rm;
        $this->rm = $rm;
        return $oldRM;
    }
    
    public function getServerId() {
        return $this->serverId;
    }
    
    public function checkPath($path) {
    }

    public function getType() {
        return $this->type;
    }

    public function callRemoteServer($operation,$arguments = array(),$data = NULL) {
        $msg = "SCRemote::callRemoteServer($operation,".serialize($arguments).") serverId=".$this->serverId;
		$url = parse_url($this->url);
		$errno = NULL;
		$errstr = NULL;
		$context = stream_context_create();
		if($url["scheme"] == "https") {
			stream_context_set_option($context, 'ssl', 'verify_host', true);
			stream_context_set_option($context, 'ssl', 'allow_self_signed', false);

			if(!array_key_exists("port", $url)) $url["port"] = 443;
			$remote = "ssl:/"."/".$url["host"].":".$url["port"];
		}
		else {
			if(!array_key_exists("port", $url)) $url["port"] = 80;
			$remote = "tcp:/"."/".$url["host"].":".$url["port"];
		}
		
		$arguments["rm"] = $this->rm;

		$fp = stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
		if(is_resource($fp)) {
			$query = http_build_query($arguments,'','&');
			if(strlen($query) > 0) {
				$query = "?$query";
			}
			$request = "POST ".$url["path"]."/index.php/apps/ocrfs/$operation";
			$request = str_replace("/"."/","/",$request);
			$request = "$request$query HTTP/1.0";
			Log::debug($this->serverId."\tRequest: ".$url["host"].": ".trim($request));
//			error_log(print_r($arguments, true));
			fwrite($fp,"$request\r\n");
			fwrite($fp,"Host: ".$url["host"]."\r\n");
			fwrite($fp,"Content-Length: ".strlen($data)."\r\n");
			fwrite($fp,"Connection: close\r\n");

			fwrite($fp,"\r\n");
			if($data !== NULL) {
				fwrite($fp,$data);
				Log::debug($this->serverId."\tData(".strlen($data).")");
			}

            $http = 0;
			$contentType = "";
			$contentLength = -1;
			$data = "";
			while(!feof($fp)) {
				$line = fgets($fp,1024);
//				Log::debug($this->serverId."\tHeader: ".trim($line));
				if($http === 0) {
				    list($null,$http) = explode(" ", $line);
				}
				else if(substr($line,0,strlen("Content-Type:")) === "Content-Type:") {
				    $contentType = trim(substr($line,strlen("Content-Type:")));
				}
				else if(substr($line,0,strlen("Content-Length:")) === "Content-Length:") {
				    $contentLength = trim(substr($line,strlen("Content-Length:")));
				}
				if($line === "\r\n") {
				    $msg = $this->serverId."\tContent";
				    if($contentLength >= 0) {
				        if($contentLength > 0) {
				            $len = $contentLength;
        					while(!feof($fp) && $len > 0) {
        					    $tmp = fread($fp,min(1024, $len));
        					    $len -= strlen($tmp);
        						$data .= $tmp;
        					}
				        }

        				if(strpos($contentType,"application/octet-stream") !== false) {
        			        $msg .= ": DATA";
    				    }
    				    else if(strpos($contentType,"application/json") !== false) {
    			    	    $data = json_decode($data, true);
    		    	        $msg .= ": JSON";
    	    			}
        				else if(strpos($contentType,"singletype") !== false) {
        				    if($data === "f") {
    				            $msg .= ": false";
    			    	        $data = false;
    		    		    }
    	    			    else if($data === "t") {
        				        $msg .= ": true";
        				        $data = true;
    				        }
    				        else if($data === "n") {
    			    	        $msg .= ": null";
    		    		        $data = null;
    	    			    }
        				    else {
        				        $data = $data/1;
    				            $msg .= ": $data";
    				        }
    			    	}
    		    		else {
    		    		    if(strlen($data) > 100) {
    		    		        $data = substr($data,0,97)."...";
    		    		    }
    	    			    Log::debug($msg." http: $http, Unknonw data($contentLength): ".$data);
        				    throw new \Exception($data);
        				}
				    }
				    else {
	    			    Log::debug($msg." http: $http, Unknonw data($contentLength)");
    				    throw new \Exception($data);
				    }
				    
				    if($http != "200") {
				        Log::debug($msg." http: $http");
    				    throw new \Exception("http: ".$http);
				    }

    				break;
				}
			}
			fclose($fp);
			Log::debug($msg);
			return $data;
		}
		else {
		    Log::debug($msg.": ERR");
			return false;
		}
	}

    public function fopen($path,$mode,$onlyLocal = false, $data = null, $close = false, $time = null, $atime = null) {
        $args = array("path" => $path, "mode" => $mode);
        if($data !== null) {
            if($close) {
                $args["close"] = 1;
                if($time !== null) {
                    $args["time"] = $time;
                    if($atime !== null) {
                        $args["atime"] = $atime;
                    }
                }
            }
            return $this->callRemoteServer("fopen", $args, $data);
        }
        else {
            return $this->callRemoteServer("fopen", $args);
        }
    }

    public function fread($id,$size) {
        return $this->callRemoteServer("fread", array("id" => $id, "size" => $size));
    }

    public function fwrite($id,$data) {
        return $this->callRemoteServer("fwrite", array("id" => $id), $data);
    }
    
    public function fstat($id) {
        return $this->callRemoteServer("fstat", array("id" => $id));
    }

    public function feof($id) {
        return $this->callRemoteServer("feof", array("id" => $id));
    }

    public function fflush($id) {
        return true;//$this->callRemoteServer("fflush", array("id" => $id));
    }

    public function fclose($id, $time = null, $atime = null) {
        $args = array("id" => $id);
        if($time !== null) {
            $args["time"] = $time;
            if($atime !== null) {
                $args["atime"] = $atime;
            }
        }
        return $this->callRemoteServer("fclose", $args);
    }
    
    public function opendir($path,$onlyLocal = false) {
        return $this->callRemoteServer("opendir", array("path" => $path));
    }

    public function readdir($id) {
        return $this->callRemoteServer("readdir", array("id" => $id));
    }

    public function rewinddir($id) {
        return $this->callRemoteServer("rewinddir", array("id" => $id));
    }

    public function closedir($id) {
        return $this->callRemoteServer("closedir", array("id" => $id));
    }
    
    public function getMetaData($id) {
        return $this->callRemoteServer("metadata", array("id" => $id));
    }

    public function url_stat($path) {
        return $this->callRemoteServer("urlstat", array("path" => $path));
    }
    
    public function touch($path, $time = null, $atime = null) {
        $args = array("path" => $path);
        if($time != null) {
            $args["time"] = $time;
        }
        if($atime != null) {
            $args["atime"] = $atime;
        }
        return $this->callRemoteServer("touch", $args);
    }

    public function mkdir($path) {
        return $this->callRemoteServer("mkdir", array("path" => $path));
    }
    
    public function unlink($path) {
        return $this->callRemoteServer("unlink", array("path" => $path));
    }

    public function rmdir($path) {
        return $this->callRemoteServer("rmdir", array("path" => $path));
    }
};