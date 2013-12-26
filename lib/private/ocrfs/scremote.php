<?php
namespace OC\OCRFS;

class SCRemote implements StateCacheRFS {
    protected $serverId;
    protected $url;
    protected $type;
    protected $secret;

    public function __construct($id, $url, $type, $secret) {
        $this->serverId = $id;
        $this->url = $url;
        $this->type = $type;
        $this->secret = $secret;
    }
    
    public function getServerId() {
        return $this->serverId;
    }

    public function getType() {
        return $this->type;
    }

    public function callRemoteServer($operation,$arguments = array(),$data = NULL) {
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

		$fp = stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
		if(is_resource($fp)) {
			$query = http_build_query($arguments,'','&');
			if(strlen($query) > 0) {
				$query = "?$query";
			}
			$request = "POST ".$url["path"]."/index.php/apps/ocrfs/$operation";
			$request = str_replace("/"."/","/",$request);
			$request = "$request$query HTTP/1.0";
			error_log(self::$replicationserverid."\tRequest: ".$url["host"].": ".trim($request));
			fwrite($fp,"$request\r\n");
			fwrite($fp,"Host: ".$url["host"]."\r\n");
			fwrite($fp,"Content-Length: ".strlen($data)."\r\n");
			fwrite($fp,"\r\n");
			if($data !== NULL) {
				fwrite($fp,$data);
			}

			$contentType = "";
			$data = "";
			while(!feof($fp)) {
				$line = fgets($fp,1024);
				error_log(self::$replicationserverid."\tHeader: ".trim($line));
				$contentType = "";
				if(substr($line,0,strlen("Content-Type:")) === "Content-Type:") {
				    $contentType = trim(substr($line,strlen("Content-Type:")));
				}
				if($line === "\r\n") {
					while(!feof($fp)) {
						$data .= fread($fp,1024);
					}
					error_log(self::$replicationserverid."\tData: ".trim($data));
					break;
				}
				
				if(strpos($contentType,"application/octet-stream") !== false) {
				}
				else if(strpos($contentType,"application/json") !== false) {
				    $data = json_decode($data, true);
				}
				else if(strpos($contentType,"singletype") !== false) {
				    if($data === "f") {
				        $data = false;
				    }
				    else if($data === "t") {
				        $data = true;
				    }
				    else if($data === "n") {
				        $data = null;
				    }
				    else {
				        $data = $data/1;
				    }
				}
				else {
				    error_log("Unknonw data: ".$data);
				    throw new \Exception($data);
				}
			}
			fclose($fp);
			return $data;
		}
		else {
			return false;
		}
	}

    public function fopen($path,$mode) {
        return $this->callRemoteServer("fopen", array("path" => $path, "mode" => $mode));
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
        return $this->callRemoteServer("fflush", array("id" => $id));
    }

    public function fclose($id) {
        return $this->callRemoteServer("fwrite", array("id" => $id));
    }
    
    public function opendir($path) {
        return $this->callRemoteServer("opendir", array("path" => $path));
    }

    public function readdir($id) {
        return $this->callRemoteServer("fread", array("id" => $id));
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
        return $this->callRemoteServer("unlink", array("path" => $path));
    }
};