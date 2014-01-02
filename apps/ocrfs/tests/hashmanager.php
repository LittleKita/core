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

if(getcwd() == dirname(__FILE__)) {
	chdir("../../../");
}
include("lib/base.php");

function removeAllOCRFS() {
    $path = array("./");
    for($i=0;$i<count($path);$i++) {
        $p = $path[$i]."/.ocrfs";
        if(file_exists($p)) {
            Log::debug("UNLINK " . $p);
            unlink($p);
        }

        $dir = dir($path[$i]);
        if(is_object($dir)) {
            while(($entry = $dir->read()) !== false) {
                if($entry == "." || $entry == "..") continue;
                $p = $path[$i]."/".$entry;
                if(is_dir($p)) {
                    $path[] = $p;
                }
            }
            $dir->close();
        }
    }
}


$hm = \OC\OCRFS\HashManager::getInstance();

removeAllOCRFS();
$hm->clearOCRFS();

$fp = fopen("data/christian.lange/files/test","w");
mt_srand(microtime() * 1000000);
fwrite($fp, mt_rand());
fclose($fp);
/*
touch("data/christian.lange/files/test");
//$hm->updateHashByPath("/christian.lange/files/test");
*/
//$hm->updateHashByPath("/", true);
$hm->updateHashByPath("/");

Log::debug("EOF");
?>
