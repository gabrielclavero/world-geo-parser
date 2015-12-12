<?php

/**
*
* World Geo Parser
* Author: Gabriel Clavero
* Homepage: https://github.com/gabrielclavero/world-geo-parser
* License: Creative Commons Attribution 3.0
*
*/

error_reporting(E_ALL);

// we will be dealing with very large data files
ini_set('memory_limit','512M');

//change locale for sorting. Use the one you like, make sure it's installed on your machine
setlocale(LC_COLLATE,'');

// constants
define('CONNECT_TIMEOUT', 25000);
define('BASE_URL', 'http://download.geonames.org/export/dump');
define('COUNTRY_INFO_URL', BASE_URL.'/countryInfo.txt');
define('STATES_INFO_URL', BASE_URL.'/admin1CodesASCII.txt');
define('COUNTRY_INFO_FILE', 'countries.json');
define('DATA_FOLDER_COUNTRIES', './countries');
define('DATA_FOLDER_STATES', DATA_FOLDER_COUNTRIES.'/states');
define('LOCAL_ZIP_FILE', 'country.zip');

// cities that no longer exist, etc
$IGNORED_FEATURE_CODES = array('PPLH', 'PPLQ', 'PPLW', 'PPLX', 'STLMT');
//countries that no longer exist
$IGNORED_ISO_CODES = array('AN', 'CS');

// custom error handler
function exception_error_handler($severity, $message, $file, $line) 
{
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    exit($message."\n");
}
set_error_handler("exception_error_handler");


// create directories
if(!file_exists(DATA_FOLDER_COUNTRIES)) {
    mkdir(DATA_FOLDER_COUNTRIES, 0777, true);
}
if(!file_exists(DATA_FOLDER_STATES)) {
    mkdir(DATA_FOLDER_STATES, 0777, true);
}


/////// 1. Get countries names and iso codes
if(($ch = curl_init()) === FALSE) exit("Could not initialize a new cURL session.\n");

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, CONNECT_TIMEOUT);

curl_setopt($ch, CURLOPT_URL, COUNTRY_INFO_URL);

if(($res = curl_exec($ch)) === FALSE) exit(curl_error($ch)."\n");

// we assume a certain format for the country info remote file
$res = explode("\n", $res);
$countries = array();
foreach($res as $line) {
    // ignore comment lines
    if(strlen($line) === 0 || $line[0] === "#") continue;
    
    // get iso code and name
    preg_match('/([^\t]+)\t[^\t]*\t[^\t]*\t[^\t]*\t([^\t]+)\t.*/iu', $line, $matches);
    $isoCode = $matches[1];
    $name = $matches[2];
    
    // ignored countries
    if(in_array($isoCode, $IGNORED_ISO_CODES)) continue;
    
    // add it to array
    $countries[$isoCode] = array('name'=>$name, 'states'=>array());
}

// export the country names to a json file
$jsonData = array();
foreach($countries as $isoCode=>$country) {
    $jsonData[$country['name']] = $isoCode;
}
ksort($jsonData, SORT_LOCALE_STRING);
$fp = fopen(COUNTRY_INFO_FILE, 'w');
fwrite($fp, json_encode($jsonData, JSON_UNESCAPED_UNICODE));
fclose($fp);


/////// 2. Get states/provinces of each country
curl_setopt($ch, CURLOPT_URL, STATES_INFO_URL);

if(($res = curl_exec($ch)) === FALSE) exit(curl_error($ch)."\n");
curl_close($ch);

// we assume a certain format for the states/provinces info remote file
$res = explode("\n", $res);
foreach($res as $line) {
    // ignore comment lines
    if(strlen($line) === 0 || $line[0] === "#") continue;

    preg_match('/(..).([^\t]+)\t([^\t]*)\t([^\t]*)\t.*/iu', $line, $matches);
    
    $isoCode = $matches[1];
    $stateCode = $matches[2];
    $stateName = strlen($matches[4]) == 0 ? $matches[3] : $matches[4];  //$matches[3] is utf-8, $matches[4] is ascii. Use the one you like more
    
    if(strlen($stateName) > 0) {
        $countries[$isoCode]['states'][$stateCode] = array('name'=>$stateName, 'cities'=>array());
    }
}
unset($res);

// export the countries data to a json file
foreach($countries as $isoCode=>$country) {
    $jsonData = array();
    foreach($country['states'] as $stateCode=>$state) {
        $jsonData[$state['name']] = $stateCode;
    }
    ksort($jsonData, SORT_LOCALE_STRING);
    $fp = fopen(DATA_FOLDER_COUNTRIES.'/'.$isoCode.'.json', 'w');
    fwrite($fp, json_encode($jsonData, JSON_UNESCAPED_UNICODE));
    fclose($fp);
    $fp = NULL;
}


/////// 3. Load cities for each country
foreach($countries as $isoCode=>$country) {
    // open remote file
    if(file_put_contents(LOCAL_ZIP_FILE, fopen(BASE_URL.'/'.$isoCode.'.zip', 'r')) === FALSE) exit("Error downloading external zip file\n");
    
    // unzip file
    $zip = new ZipArchive;
    $res = $zip->open(LOCAL_ZIP_FILE);
    if($res === TRUE) {
        if(!($zip->extractTo('.'))) {
            unlink(LOCAL_ZIP_FILE);
            exit("Could not extract zip file contents\n");
        }
        $zip->close();
    } else {
        unlink(LOCAL_ZIP_FILE);
        exit("ZipArchive open failed code: ".$res."\n");
    }

    // delete local zip file
    unlink(LOCAL_ZIP_FILE);
    
    // read local unzipped file and process its lines, we assume a certain format for these files
    if(($localFile = file_get_contents($isoCode.".txt")) === FALSE) {
        unlink("readme.txt");
        unlink($isoCode.".txt");
        exit("Error opening local file\n");
    }
    
    // delete uncompressed files
    unlink("readme.txt");
    unlink($isoCode.".txt");
    
    // city files can get very large, we don't create an auxiliar array like we did with the other ones for memory issues
    // for this reason we also use an offset variable instead of modifying the file string
    $N = strlen($localFile);
    $offset = 0;
    while($offset < $N) {
        // read line by line
        $linebreakIdx = strpos($localFile, "\n", $offset);
        $line = '';
        if($linebreakIdx === FALSE) {
            $line = substr($localFile, $offset);
            $offset = $N;
        } else {
            $line = substr($localFile, $offset, $linebreakIdx - $offset);
            $offset = $linebreakIdx+1;
        }
    
        // ignore comment lines and empty lines
        if(strlen($line) == 0 || $line[0] === "#") continue;
        
        // extract geographical information
        preg_match('/[^\t]*\t([^\t]*)\t([^\t]*)\t[^\t]*\t[^\t]*\t[^\t]*\t([^\t]*)\t([^\t]*)\t[^\t]*\t[^\t]*\t([^\t]*)\t.*/iu', $line, $matches);
        
        if(count($matches) < 6) continue;        
        $featureClass = $matches[3];
        $featureCode = $matches[4];
        $stateCode = $matches[5];
        $cityName = strlen($matches[2]) == 0 ? $matches[1] : $matches[2];       //$matches[1] is utf-8, $matches[2] is ascii. Use the one you like more
        
        
        // only consider it if is a city        http://www.geonames.org/export/codes.html
        if($featureClass !== 'P') continue;
        if(strlen($featureCode) == 0 || in_array($featureCode, $IGNORED_FEATURE_CODES)) continue;
        
        // if these values are not available dont add it
        if(strlen($stateCode) == 0 || strlen($cityName) == 0) continue;
        
        // save the city in the array key too, to avoid saving the same name more than once (it often occurs more than once in the source actually)
        $countries[$isoCode]['states'][$stateCode]['cities'][$cityName] = $cityName;
    }
    
    // export the cities of each state of this particular country to its corresponding json file
    foreach($countries[$isoCode]['states'] as $stateCode=>$state) {
        $tmp = array();
        foreach($state['cities'] as $key=>$value) {
            $tmp[] = $key;
        }
        sort($tmp, SORT_LOCALE_STRING);
    
        $fp = fopen(DATA_FOLDER_STATES.'/'.$isoCode.'.'.$stateCode.'.json', 'w');
        fwrite($fp, json_encode($tmp, JSON_UNESCAPED_UNICODE));
        fclose($fp);
    }

    // to maintain a low usage of memory
    unset($countries[$isoCode]['states']);
}
