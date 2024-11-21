<?php

/************************* FUNCTIONS ***********************/

function showHelp() {   
    $msg = "

Usage: php " . basename($_SERVER['PHP_SELF']) . " <architecture> [options]

Options:
-h  Display this help message
-v  Verbose output
-s  Suppress all output
-m  Comma-separated list of mirror hostnames
-d  Distribution version (e.g., stable, bullseye)
-p  Comma-separated list of suites to include (main, contrib)
-n  Number of top packages to report

<architecture>
Required architecture (e.g., amd64, arm64, i386)

Example:  php " . basename($_SERVER['PHP_SELF']) . " amd64 -v -m ftp.debian.org,ftp.us.debian.org -d stable -n 10";
    outputLog($msg, 'normal');
}

/**
 * Parses command-line arguments and configuration file options.
 *
 * This function parses command-line arguments and a configuration file (optional)
 * to build an options array used by the script. 
 *
 * The function first parses any command-line arguments, prioritizing configuration 
 * provided on the command line. It then checks for a configuration file at the 
 * specified path and incorporates its settings while ensuring type compatibility 
 * between the configuration and script expectations.
 *
 * The function supports the following command-line arguments:
 *
 *  - `-v` : Sets the verbosity level to "verbose".
 *  - `-s` : Sets the verbosity level to "silent".
 *  - `-m` : Sets the list of mirrors separated by commas (",").
 *  - `-d` : Sets the distribution version.
 *  - `-p` : Sets the list of suites to include separated by commas (",").
 *  - `-n` : Sets the number of top N counts to report.
 *  - `<architecture>`:  Sets the architecture name (e.g., amd64, arm64).  This argument is required 
 *                       and should be the first argument (excluding the script name).
 *
 * If the configuration file exists, it follows the standard INI format and 
 * can provide values for the following options (excluding "arch"):
 *
 *  - mirrors
 *  - version
 *  - suites
 *  - topn
 *
 * **Note:** The script exits with an error code if no architecture is provided
 * on the command line.
 *
 * @param array $args The command-line arguments.
 * @param string $configFilename The path to the configuration file (optional).
 * @return array The options array containing configuration settings.
 * 
 */
function getOptions($args, $configFilename) {
    // get argument global for function
    global $verbosity;
    
    // NOTE: we are going to look for the verbosity flag real
    // quick in the command-line so we can log immediately

    // set default options
    $opts = array(
        "mirrors"    => array("ftp.debian.org"),    // default mirror
        "version"    => "stable",                   // distribution version
        "suites"     =>  array("main"),             // array of suites to include
        "topn"       => 10,                         // top N counts to report
        "verbosity"  => "normal",                   // verbosity level
        "arch"       => null                        // architecture
    );

    $all_options = array("v", "s", "m", "d", "p", "n", "h");

    foreach ($args as $argName => $argVal) {
        if (!in_array($argName, $all_options)) {
            $opts["arch"] = $argName;
            break;
        }
    }

    // If no architecture provided, exit with error code
    if (!$opts["arch"]) {
        outputLog("ERROR: Must have at least one argument as architecture name: (e.g. amd64 arm64, mips, etc.)", $verbosity);
        showHelp();
        return false;
    }

    // first get ini file values (if there are any). 
    // we will use the "$opts" array above as our dictionary of variables to look for. 
    // we also will check for var type to make sure it matches
    if (file_exists($configFilename)) {
        $ini = parse_ini_file($configFilename, true, INI_SCANNER_TYPED);
        debugLog("Got ini values: " . json_encode($ini, JSON_PRETTY_PRINT), $verbosity);
        foreach (array_keys($opts) as $key) {
            if ($key == "arch") {  // we don't accept arch from the ini file since requirement is to have in command-line
                continue;
            }
            if (isset($ini[$key])) {
                $optsVarType = gettype($opts[$key]);
                $iniVarType = gettype($ini[$key]);
                if ($optsVarType == $iniVarType) {  // If they types are different (e.g. array vs string) then the setting wasn't properly set
                    $opts[$key] = $ini[$key];
                }
            }
        }
    }

    // store parameter into $architecture with lowercase version of input
    foreach ($args as $argName => $argVal) {
        switch ($argName) {
            case 'v':
                $opts["verbosity"] = "verbose";
                debugLog("Got cmd-line: verbosity = verbose");
                break;
            case 's':
                $opts["verbosity"] = "silent";
                debugLog("Got cmd-line: verbosity = silent");
                break;
            case 'm':
                $opts["mirrors"] = explode(",", $argVal);
                debugLog("Got cmd-line: mirrors = " . json_encode($argVal));
                break;
            case 'd':
                $opts["version"] = $argVal;
                debugLog("Got cmd-line: version = " . $argVal);
                break;
            case 'p':
                $opts["suites"] = explode(",", $argVal);
                debugLog("Got cmd-line: suites = " . json_encode($argVal));
                break;
            case 'n':
                $opts["topn"] = intval($argVal);
                debugLog("Got cmd-line: topn = " . $argVal);
                break;
            default:
                break;
        }
    }

    // remove duplicates
    $opts["mirrors"] = array_unique($opts["mirrors"]);
    $opts["suites"] = array_unique($opts["suites"]);

    return $opts;
}


 /**
 * Extracts the list of architectures from a Debian Release file.
 *
 * This function fetches the specified Debian Release file, parses it to find the
 * "Architectures:" line, and extracts the list of architectures from it.
 *
 * @param string $url The URL of the Debian Release file.
 * @return array|bool An array of architecture names if successful, `false` otherwise.
 */
function getArchitectures($url) { 
    $fileArray = file($url);

    $archLine = preg_grep('/^Architectures\:.*/', $fileArray);
    debugLog("Found architectures line: " . json_encode($archLine) . " in $url");
    if (!$archLine) {
        debugLog("Could not find architecture line in $url");
        return false;
    }
    
    reset($archLine);
    //explode first array element (list of archs) into array, then trim and lowercase them
    $archArray = explode(" ", strtolower(trim(current($archLine))));
    array_splice($archArray, 0, 1);  // Remove the first in the array which is the 'Architectures:' line
    debugLog("Final architecture list: " . implode(", ", $archArray));
    return $archArray;
}


/**
 * Checks if a given URL exists and is accessible.
 *
 * This function sends an HTTP HEAD request to the specified URL and checks the response status code.
 * If the response code is 200 (OK), it indicates that the URL exists and is accessible.
 *
 * @param string $url The URL to check.
 * @return bool Returns `true` if the URL exists, `false` otherwise.
 */
function urlExists($url) {
    debugLog("Checking if $url exists...");
    $headers = @get_headers($url);
    if($headers && strpos( $headers[0], '200')) { 
        $status = true;
        debugLog("URL OK: $url");
    } 
    else { 
        $status = false;
        debugLog("URL Failed: $url");
    }
    return $status;
}


/**
 * Outputs an error message to the console and terminates the script.
 *
 * This function is typically used to handle fatal errors or unexpected exceptions. 
 * It prints the specified error message and then exits the script with a non-zero 
 * exit code to indicate failure.
 *
 * @param string $text The error message to be displayed.
 */
 function errorOut($text) {
    echo "ERROR: $text";
    showHelp();
    exit(1);
}

/**
 * Logs a debug message to the console if the global `$verbosity` variable is set to "verbose".
 *
 * This function is primarily used for debugging purposes and can be helpful for tracking
 * the execution flow of a script.
 *
 * @param string $message The debug message to be logged.
 */
function debugLog($message) {
    global $verbosity;
    if ($verbosity == "verbose") {
        echo "[VERBOSE] $message\n";
    }
}

/**
 * Outputs a message to the console based on the specified verbosity level.
 *
 * @param string $message The message to be output.
 * @param string $verbosity The verbosity level. Can be one of the following:
 *     - 'verbose': Outputs the message with a "[VERBOSE]" prefix.
 *     - 'normal': Outputs the message without any prefix.
 *     - 'silent': Suppresses the output.
 */
 function outputLog($message, $verbosity) {
    switch ($verbosity) {
        case 'verbose':
            echo "[VERBOSE] $message\n";
            break;
        case 'normal':
            echo "$message\n";
            break;
        case 'silent':
            // Do nothing
            break;
        default:
            // echo "[UNKNOWN VERBOSITY] $message\n";
            break;
    }
}


/**
 * Parses command-line arguments.
 *
 * This class provides a method to parse command-line arguments, handling various formats
 * such as single-letter options, long options with and without values, and option groups.
 *
 * @example
 * ```php
 * $parser = new ArgvParser();
 * $configs = $parser->parseConfigs();
 *
 * // Access parsed options:
 * if (isset($configs['v'])) {
 *     echo "Verbose mode enabled.\n";
 * }
 * ```
 */
class ArgvParser {
    const MAX_ARGV = 1000;

    /**
     * Parse arguments
     * 
     * @param array|string [$message] input arguments
     * @return array Configs Key/Value
     */
    public function parseConfigs(&$message = null)
    {
        if (is_string($message)) {
            $argv = explode(' ', $message);
        } else if (is_array($message)) {
            $argv = $message;
        } else {
            global $argv;
            if (isset($argv) && count($argv) >= 1) {
                array_shift($argv);
            }
        }

        $index = 0;
        $configs = array();
        while ($index < self::MAX_ARGV && isset($argv[$index])) {
            if (preg_match('/^([^-\=]+.*)$/', $argv[$index], $matches) === 1) {
                // not have any -= prefix
                $configs[$matches[1]] = true;
            } else if (preg_match('/^-+(.+)$/', $argv[$index], $matches) === 1) {
                // match prefix - with next parameter
                if (preg_match('/^-+(.+)\=(.+)$/', $argv[$index], $subMatches) === 1) {
                    $configs[$subMatches[1]] = $subMatches[2];
                } else if (isset($argv[$index + 1]) && preg_match('/^[^-\=]+$/', $argv[$index + 1]) === 1) {
                    // have sub parameter
                    $configs[$matches[1]] = $argv[$index + 1];
                    $index++;
                } elseif (strpos($matches[0], '--') === false) {
                    for ($j = 0; $j < strlen($matches[1]); $j += 1) {
                        $configs[$matches[1][$j]] = true;
                    }
                } else if (isset($argv[$index + 1]) && preg_match('/^[^-].+$/', $argv[$index + 1]) === 1) {
                    $configs[$matches[1]] = $argv[$index + 1];
                    $index++;
                } else {
                    $configs[$matches[1]] = true;
                }
            }
            $index++;
        }

        return $configs;
    }
}
