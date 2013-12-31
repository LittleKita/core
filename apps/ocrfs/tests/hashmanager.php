<?PHP

use OC\OCRFS\Log;

if(!posix_setgid(33)) {
    die("posix_setgid");
}
if(!posix_setuid(33)) {
    die("posix_setuid");
}
if(!posix_setegid(33)) {
    die("posix_setegid");
}
if(!posix_seteuid(33)) {
    die("posix_seteuid");
}

chdir("../../../");
include("lib/base.php");

$hm = \OC\OCRFS\HashManager::getInstance();

//$hm->clearOCRFS();
$fp = fopen("data/christian.lange/files/test","w");
mt_srand(microtime() * 1000000);
fwrite($fp, mt_rand());
fclose($fp);
/*
touch("data/christian.lange/files/test");
//$hm->updateHashByPath("/christian.lange/files/test");
*/
$hm->updateHashByPath("/", true);
//$hm->updateHashByPath("/");

function xyz() {
	Log::debug("HUHU");
}
xyz();
Log::debug("HUHU2");
echo "\r\nEOF\r\n";
?>
