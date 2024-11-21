<?php

/******************************************
DESCRIPTION:
    This script fetches relevant Debian Repository "Contents” index and pulls the list the files assiciated 
    with each of the packages for a given architecture (e.g. arm6, amg64, etc4).
    Upon parsing the Contents file, it will gather file counts for each package, and output the top N (configurable)
    package names and file counts in descending order by file count. 
EXPECTED INPUT:
    The architecture MUST be passed as an argument (stdin).
EXPECTED OUTPUT: 
    Top N packages with the most associations with total count of files descending sort 
    by file count.
OPTIONAL: 
    Script will look for an ini file in the same directory with the same base file name as the script
    for example if the script is "foo.php", then the script will look for "foo.ini" 
    Command-line parameters take precedence over all other options (ini or defaults)
        -h  Display help message
        -v  Verbose output
        -s  Suppress all output
        -m  Comma-separated list of mirror hostnames
        -d  Distribution version (e.g., stable, bullseye)
        -p  Comma-separated list of suites to include
        -n  Number of top packages to report

    Example:

    php deb_pkg_ranker.php amd64 -v -m ftp.debian.org,ftp.us.debian.org -d stable -s main 

    
PREREQUISITES/ASSUMPTIONS:
    PHP installed, 
    PHP Unit module for unit testing
        composer require --dev phpunit/phpunit
    Internet access (to fetch from mirror)
    Debian mirror should have standard Release file (/debian/dist/Distribution/Release)
    - included .ini in same directory as script and must have the same base file name
    - We are only getting gzip (.gz) versions of Contents files
******************************************/

ini_set("memory_limit", "2048M");
$scriptDir = __DIR__;

require_once("dpr_functions.php");

// set config file name to be same basename as this script with extension .ini
$configFilename = __DIR__ . "/" . pathinfo(__FILE__, PATHINFO_FILENAME) . ".ini";

// special case to get arguments first so we can set verbosity immediately
$verbosity = 'normal';
$argvParser = new ArgvParser();
$args = $argvParser->parseConfigs();

if (isset($args["v"])) {
    $verbosity = 'verbose';
}
else if(isset($args["silent"])) {
    $verbosity = 'silent';
}

debugLog("Command-line arguments: " . json_encode($args));

// display help if "h" parameter was passed
if (isset($args["h"])) {
    showHelp();
    exit(0);
}

// Get options from ini and command-line
// Note: command-line always overrides ini file settings
$opts = getOptions($args, $configFilename);

debugLog("Final options: " . json_encode($opts, JSON_PRETTY_PRINT));

if (!$opts) {
    exit(1);    
}

// Find first working mirror
$mirrorFound = false;
foreach ($opts["mirrors"] as $mirrorHostname) {
    $distListURL = "https://$mirrorHostname/debian/dists";

    $distURL = "$distListURL/" . $opts["version"];
    $releaseFileURL = "$distURL/Release";

    if (urlExists($releaseFileURL)) {
        $mirrorFound = true;
        break;
    }
}

if (!$mirrorFound) {
    errorOut("ERROR: Could not reach any of the following URLs:" . json_encode($opts["mirrors"]));
}

// Get list of valid architectures from the mirror's uri /debian/dists
$archList = getArchitectures($releaseFileURL);
if ($archList == false) {
    errorOut("Could not find 'Architectures:' line in $releaseFileURL");
}

$architecture = $opts["arch"];
if (in_array($architecture, $archList) == false) {
    errorOut("$architecture does not exist in architecture list. \nPlease use one of the following: " . implode(", ", $archList));
}
debugLog("$architecture found in list: " . implode(", ", $archList));

// handle multiple suites

debugLog("Gathering package info for suites: " . implode(", ", $opts["suites"]));
$package_counts = array();
$totalCount = 0; // Used for stats in verbose mode

// first check if all of the urls work before we do heavy parsing...
foreach ($opts["suites"] as $suite) {
    $distFilesURL = "$distURL/" . $suite;
    $contentsFileName = "Contents-$architecture.gz";
    $contentsFilePathName = "$distFilesURL/$contentsFileName";

    if (!urlExists($contentsFilePathName)) {
        errorOut("$contentsFilePathName does not exist.");
    }
}


foreach ($opts["suites"] as $suite) {
    $suiteCount = 0; // Used for stats in verbose mode
    debugLog("Working on suite: $suite");
    $distFilesURL = "$distURL/" . $suite;
    $contentsFileName = "Contents-$architecture.gz";
    $contentsFilePathName = "$distFilesURL/$contentsFileName";

    if (!urlExists($contentsFilePathName)) {
        errorOut("$contentsFilePathName does not exist.");
    }
    
    if (!$zippedContentsFile = file_get_contents($contentsFilePathName)) {
        errorOut("No content received for $contentsFilePathName");
    }
    
    // Decompress file
    if (!$contentsFile = gzdecode($zippedContentsFile)) {
        errorOut("Could not unzip $contentsFileName");
    }

    $lines = explode("\n", $contentsFile);
    debugLog("Total lines for $suite/$contentsFileName: " . count($lines));
    unset($contentsFile); // release some memory 
    
    foreach ($lines as $line) {
        // Split the line into file path and package names
        $line_parts = explode(' ', $line, 2);
        if (count($line_parts) < 2) continue;  // If for some reason we don't have 2 parts in a line, move on
        $file_path = $line_parts[0]; // keeping for fun, unused.
        $line_packages = explode(',', trim($line_parts[1]));
    
        // Update the package associations count
        foreach ($line_packages as $line_package) {
            // some lines have multiple packages delimited by comma, so parse them out 
            $package_array = explode(",", $line_package);

            // for each package found in on the line delimited by comma, tally them below
            foreach ($package_array as $package) {
                // parse the section/group and package name (ie: net/net-tools)
                $package_parts = explode("/", $package);  
                
                if (!isset($package_parts[1])) {
                    // If for some reason this is not a properly formatted line, skip to next line
                    continue;  
                }
                
                // Let's save the package name into the array.
                // We will include the suite name because it's possible we can be tallying 
                // multiple suites with duplicate package names
                $package_name = $suite . "/" . $package_parts[1];
                
                // Increment counter for suite/package
                // If this suite/package's name is not yet a key in the array, create it.
                if (isset($package_counts[$package_name])) { 
                    $package_counts[$package_name]++;
                    // bonus: getting count of packages per suite as well (only in verbose mode)
                    $suiteCount++;
                } else {
                    $package_counts[$package_name] = 1; // if package id did not exist, create with value of 1
                    // bonus: getting count of packages per suite as well (shown only in verbose mode)
                    $suiteCount = 1;
                }
            }
        }
    }
    // bonus: getting total count of packages overall (shown only in verbose mode)
    $totalCount += $suiteCount;
    debugLog("Total packages for $suite suite: $suiteCount");
}

debugLog("Total packages for all suites: $totalCount");
arsort($package_counts); // reverse sort the array

// Output the top N packages and their total counts
$topN = $opts["topn"];
$i = 1;
foreach ($package_counts as $package => $count) {
    echo $i . ". " . $package . ": " . $count . "\n";
    if ($i == $topN) {
        break;
    }
    $i++;
} 


