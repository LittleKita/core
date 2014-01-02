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

$bgsync = new OC\OCRFS\BackgroudSync();
//$bgsync->testRun();
$bgsync->startBackgroudSync();

Log::debug("EOF");
?>
