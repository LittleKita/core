<?PHP
error_reporting(E_ALL);
$host = "cloud.b0red.de";
$port = 443;

$context = stream_context_create();
$result = stream_context_set_option($context, 'ssl', 'verify_host', true);
$result = stream_context_set_option($context, 'ssl', 'allow_self_signed', false);

$remote = "ssl:/"."/$host:$port";

$fp = stream_socket_client($remote, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $context);
if(is_resource($fp)) {
	fwrite($fp,"GET /index.php/apps/ocrfs/open?path=/root/abc123&mode=r HTTP/1.0\r\n");
	fwrite($fp,"Host: $host\r\n");
	fwrite($fp,"\r\n");
	while(!feof($fp)) {
		echo fread($fp,1024);
	}
	fclose($fp);
}
?>
