# DPR

The DPR (Debian Package Ranker) is a program that fetches the "Contents" index from a Debian mirror for a given architecture (e.g. i386, amd64, arm64, etc.), then outputs the package file counts in descending order by file count.  That is the package with the most files will appear first.

**Sample Output:**

```
php dpr.php amd64

1. main/piglit: 53007
2. main/esys-particle: 18408
3. main/acl2-books: 16907
4. main/libboost1.81-dev: 15456
5. main/libboost1.74-dev: 14333
6. main/racket: 9599
7. main/zoneminder: 8161
8. main/horizon-eda: 8130
9. main/libtorch-dev: 8089
10. main/liboce-modeling-dev: 7458
```


## Features

* Includes a list of Debian mirrors to try via the included **dpr.ini** file.
* User may choose which distribution version they wish (stable, bookworm, Debian11.11, etc.  The directory must exist within the /debian/dists directory of the Debian mirror.  
* User may input which suites to include in the rankings.  

## Prerequisites

* PHP 5.x+ and above must be installed on the system running the script
* Internet access (to fetch from mirror)
* Debian mirror should have a standard Release file (/debian/dist/Distribution/Release) with the corresponding architecture's 'Contents-<arch>.gz' file.  (e.g. /debian/dists/stable/main/Contents-amd64.gz)
* dpr.ini to be in the same directory as the script in order to read user-configured user settings
* We are only getting gzip (.gz) versions of Contents files

## Installation

* Install PHP 5.x+
* Copy all files in this repo to a single directory
* To set program defaults without providing command-line arguments, modify the dpr.ini file.  See below for syntax.

## Usage

The script will read parameters in the order as follows:

* It will first read from the dpr.ini file (if it exists)
* Then, it will read from the command-line arguments to override any settings defined in the .ini file.  
* The script requires just one argument: the architecture name (amd64, arm64, etc). 
* If only the architecture is passed and no .ini config file exists, then the script will run with the following defaults:
  * **Verbosity**: normal
  * **Mirror**: ftp.debian.org
  * **Version**: stable
  * **Suites**: main
  * **Top 'N'**: 10

To run the command, simply start with:  ```php dpr.php arm64``` to run with the above defaults.

## dpr.ini File Syntax

The included file comes with the following content:

```
;; ini file for dpr.php

;; verbosity level, normal, verbose, silent
verbosity = normal 

;; top "N" packages to report
topn = 10

;; distribution to use
version = stable

;; suites to include when tallying
suites[] = main
; suites[] = contrib
; suites[] = non-free
; suites[] = non-free-firmware

;; mirrors to try in order as defined below
mirrors[] = ftp.us.debian.org
mirrors[] = atl.mirrors.clouvider.net
mirrors[] = debian.cc.lehigh.edu
mirrors[] = debian.csail.mit.edu
mirrors[] = debian.cs.binghamton.edu 
```

The ini file's options can be in any order.  Just as long as they follow the correct syntax as follows:

* **verbosity**: 'normal', 'verbose', or 'silent'
* **topn**: Top "n" packages to report
* **version**:  Distribution name to use (e.g. 'stable'
* **suites[]**: array of suites to include when tallying packages. Note: 
* **mirrors[]**: array of mirrors to try.  
* **NOTE**: Each of the 'suites[]' and 'mirrors[]' lines must have the '[]' to be properly parsed as an array.

## Command-line Argument Syntax 

If any command-line arguments are provided, they supersede the same settings found in the .ini file.  Following are allowed arguments as shown in the script's help message:

```
-h  Display this help message
-v  Verbose output
-s  Suppress all output
-m  Comma-separated list of mirror hostnames
-d  Distribution version (e.g., stable, bullseye)
-p  Comma-separated list of suites to include
-n  Number of top packages to report

Example:  php dpr.php amd64 -v -m ftp.debian.org,ftp.us.debian.org -d stable -n 10
```
## Sample Output

The above example command, ```php dpr.php amd64 -v -m ftp.debian.org,ftp.us.debian.org -d stable -n 10``` gives following output (note the verbose mode):

```
[VERBOSE] Command-line arguments: {"amd64":true,"v":true,"m":"ftp.debian.org,ftp.us.debian.org","d":"stable","n":"10"}
[VERBOSE] Got ini values: {
    "verbosity": "normal",
    "topn": 10,
    "version": "stable",
    "suites": [
        "main"
    ],
    "mirrors": [
        "ftp.us.debian.org",
        "atl.mirrors.clouvider.net",
        "debian.cc.lehigh.edu",
        "debian.csail.mit.edu",
        "debian.cs.binghamton.edu"
    ]
}
[VERBOSE] Got cmd-line: verbosity = verbose
[VERBOSE] Got cmd-line: mirrors = "ftp.debian.org,ftp.us.debian.org"
[VERBOSE] Got cmd-line: version = stable
[VERBOSE] Got cmd-line: topn = 10
[VERBOSE] Final options: {
    "mirrors": [
        "ftp.debian.org",
        "ftp.us.debian.org"
    ],
    "version": "stable",
    "suites": [
        "main"
    ],
    "topn": 10,
    "verbosity": "verbose",
    "arch": "amd64"
}
[VERBOSE] Checking if https://ftp.debian.org/debian/dists/stable/Release exists...
[VERBOSE] URL OK: https://ftp.debian.org/debian/dists/stable/Release
[VERBOSE] Found architectures line: {"9":"Architectures: all amd64 arm64 armel armhf i386 mips64el mipsel ppc64el s390x\n"} in https://ftp.debian.org/debian/dists/stable/Release
[VERBOSE] Final architecture list: all, amd64, arm64, armel, armhf, i386, mips64el, mipsel, ppc64el, s390x
[VERBOSE] amd64 found in list: all, amd64, arm64, armel, armhf, i386, mips64el, mipsel, ppc64el, s390x
[VERBOSE] Gathering package info for suites: main
[VERBOSE] Checking if https://ftp.debian.org/debian/dists/stable/main/Contents-amd64.gz exists...
[VERBOSE] URL OK: https://ftp.debian.org/debian/dists/stable/main/Contents-amd64.gz
[VERBOSE] Working on suite: main
[VERBOSE] Checking if https://ftp.debian.org/debian/dists/stable/main/Contents-amd64.gz exists...
[VERBOSE] URL OK: https://ftp.debian.org/debian/dists/stable/main/Contents-amd64.gz
[VERBOSE] Total lines for main/Contents-amd64.gz: 1649528
[VERBOSE] Total packages for main suite: 49581
[VERBOSE] Total packages for all suites: 49581
1. main/piglit: 53007
2. main/esys-particle: 18408
3. main/acl2-books: 16907
4. main/libboost1.81-dev: 15456
5. main/libboost1.74-dev: 14333
6. main/racket: 9599
7. main/zoneminder: 8161
8. main/horizon-eda: 8130
9. main/libtorch-dev: 8089
10. main/liboce-modeling-dev: 7458

```

Command without verbose setting: ```php dpr.php amd64  -m ftp.debian.org,ftp.us.debian.org -d stable -n 10``` provides the following output:

```
php dpr.php amd64  -m ftp.debian.org,ftp.us.debian.org -d stable -n 10

1. main/piglit: 53007
2. main/esys-particle: 18408
3. main/acl2-books: 16907
4. main/libboost1.81-dev: 15456
5. main/libboost1.74-dev: 14333
6. main/racket: 9599
7. main/zoneminder: 8161
8. main/horizon-eda: 8130

```




