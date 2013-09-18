<?php
/*                                              _____ _        _             
    /\                                         / ____| |      | |            
   /  \__      _____  ___  ___  _ __ ___   ___| (___ | |_ __ _| |_ _   _ ___ 
  / /\ \ \ /\ / / _ \/ __|/ _ \| '_ ` _ \ / _ \\___ \| __/ _` | __| | | / __|
 / ____ \ V  V /  __/\__ \ (_) | | | | | |  __/____) | || (_| | |_| |_| \__ \
/_/    \_\_/\_/ \___||___/\___/|_| |_| |_|\___|_____/ \__\__,_|\__|\__,_|___/

@name AwesomeStatus
@author Tyzoid
@version 0.5
*/

// Parameters:
$server = "server.tyzoid.com";
$serverport  = "25565";
$supress_nonessential = false;

// Check Dependencies
$dependencies = array(
	"PHP: FastCGI" => array(
		"fastcgi_finish_request" => array("type" => "function", "required" => false),
	),
	"PHP: GD2 Library" => array(
		"ImageCreateFromPNG" => array("type" => "function", "required" => true),
	),
	"Font: newscycle.ttf" => array(
		"newsycle.ttf" => array("type" => "file", "required" => true),
	),
	"Image: online.png/offline.png" => array(
		"online.png" => array("type" => "file", "required" => true),
		"offline.png" => array("type" => "file", "required" => true),
	),
);

$allgood = true;
foreach($dependencies as $dname => $dependency){
	if (empty($dependency) || ! is_array($dependency)) continue;

	foreach ($dependency as $name => $component){
		$missing = false;
		$level = ($component['required']? "[Error]":"[Warning]");

		if($component["required"] === true){
			$missing = $missing || (!($component['type'] !== "file"     || file_exists($name)));
			$missing = $missing || (!($component['type'] !== "function" || function_exists($name)));
		} else {
			// Only print warning messages if not overridden:
			$missing = $missing || (!($component['type'] !== "file"     || file_exists($name))     && !$supress_nonessential);
			$missing = $missing || (!($component['type'] !== "function" || function_exists($name)) && !$supress_nonessential);
		}

		if($missing){
			echo "$level Missing Dependency: '$dname'. Component '$name' of type '{$component['type']}' could not be found<br />\n";
			$allgood = false;
		}
	}
}

if ($allgood === false) die("Not all dependencies are met. Please resolve these dependencies and run again.");

ignore_user_abort(true);
function query($ip, $port){
    @$f = fsockopen($ip, $port, $errorno, $errordesc, 2);
    if($f === false) return false; //connection failed
    stream_set_timeout($f, 2);
    fwrite($f, "\xfe\x01");
    $data = fread($f, 256);
    if(substr($data, 0, 1) != "\xff") return false; //Not a minecraft server
    $data2 = mb_convert_encoding(substr($data, 3), 'UTF8', 'UCS-2');
    if(strpos($data2, "\0") !== false) $data = explode("\0", $data2); //1.5.1 servers
    else $data = explode("§", mb_convert_encoding(substr($data, 3), 'UTF8', 'UCS-2'));
    return array(
        "players" => intval($data[count($data)-2]),
        "maxplayers" => intval($data[count($data)-1])
    );
}
 
$expired = true;
$online = false;
$file = fopen("check.txt", "r");
if(!feof($file)) {
    $line = fgets($file);
    $online = (substr($line,0,1) === "t");
    $players = intval(substr($line,1,3));
    $expired = (intval(substr($line,4))+60*1 < time());
    $waituntilafter = (intval(substr($line,4))+60*4 > time());
}
fclose($file);
 
if($expired === true && $waituntilafter === false){
    $info = query($server,$serverport);
    $file = fopen("check.txt", "w");
    fwrite($file,(($info !== false)?"t":"f"));
    fwrite($file,sprintf("%03d", (($info !== false)?$info['players']:0)));
    fwrite($file,(string)time());
    fclose($file);
 
    $players = (($info !== false)?$info['players']:0);
} else {
    $info = $online;
}
 
if($info === false){
    $name = './offline.png';
    $fp = fopen($name, 'rb');
 
    header("Content-Type: image/png");
    header("Content-Length: " . filesize($name));
 
    fpassthru($fp);
} else {
    $name = "./online.png";
    $image = ImageCreateFromPNG($name);
    imagesavealpha($image, true);
    $color = imagecolorallocate($image, 64, 64, 64); // #444
    imagettftext($image, 24, 0, 33, 86, $color, 'newscycle.ttf', sprintf("%02d", $players).'/16');
    header("Content-Type: image/png");
    imagepng($image);
}
 
if (function_exists("fastcgi_finish_request")) fastcgi_finish_request();
 
if($expired === true && $waituntilafter === true){
    $info = query($server,$serverport);
    $file = fopen("check.txt", "w");
    fwrite($file,(($info !== false)?"t":"f"));
    fwrite($file,sprintf("%03d", (($info !== false)?$info['players']:0)));
    fwrite($file,(string)time());
    fclose($file);
}
