<?php

ini_set("memory_limit", "2048M");

use PHPUnit\Framework\TestCase;

class unit_tests extends TestCase {

    public function testDefaultOptions()
    {
        $args["amd64"] = 1;
        $configFilename = "not_a_file.ini";  // Assuming file doesn't exist
        $expectedOpts = [
            "mirrors"    => ["ftp.debian.org"],
            "version"    => "stable",
            "suites"     => ["main"],
            "topn"       => 10,
            "verbosity"  => "normal",
            "arch"       => "amd64"
        ];

        $opts = getOptions($args, $configFilename);

        $this->assertEquals($expectedOpts, $opts);
    }

    public function testCommandLineOptions() {
        $args = [
            "v" => true,
            "m" => "ftp.us.debian.org",
            "d" => "testing",
            "p" => "contrib,non-free",
            "n" => 5,
            "arm64" => 1 // Must have architecture
        ];
        $configFilename = "not_a_file.ini";  // Assuming file doesn't exist

        $expectedOpts = [
            "mirrors"    => ["ftp.us.debian.org"],
            "version"    => "testing",
            "suites"     => ["contrib", "non-free"],
            "topn"       => 5,
            "verbosity"  => "verbose",
            "arch"       => "arm64"
        ];

        $opts = getOptions($args, $configFilename);

        $this->assertEquals($expectedOpts, $opts);
    }

    public function testIniOptions()
    {
        $args["amd64"] = 1;
        $configFilename = __DIR__ . "/test.ini"; // Assuming a test ini file exists

        // Assuming test.ini has options like version=testing, suites=contrib

        $expectedOpts = [
            "mirrors"    => ["ftp.debian.org"],  // Assuming no mirror in ini
            "version"    => "stable",  // From ini
            "suites"     => ["main"], // From ini (assuming only one suite)
            "topn"       => 10,
            "verbosity"  => "normal",
            "arch"       => "amd64"
        ];

        $opts = getOptions($args, $configFilename);

        $this->assertEquals($expectedOpts, $opts);
    }

    public function testMissingArchitecture()
    {
        $configFilename = "not_a_file.ini";
        $opt = getOptions($args, $configFilename);
        $this->assertFalse($opt);
    }

    public function getVars() {
        $this->mirrorURL = "http://ftp.debian.org/debian/dists/stable/";
        $this->architecture = "i386";
        $this->contentsFileName = "Contents-{$this->architecture}.gz";
        $this->$contentsFilePathName = $this->mirrorURL . "main/$contentsFileName";
    }

    public function testUrlExists() {
        if (!$this->mirrorURL) $this->getVars();
        $this->mirrorURL = "http://ftp.debian.org/debian/dists/stable/";
        $architecture = "i386";
        $contentsFileName = "Contents-{$this->architecture}.gz";
        $contentsFilePathName = $this->mirrorURL . "main/$contentsFileName";
        $url = $this->mirrorURL . "Release";
        $this->assertTrue(urlExists($url));
    }

    public function testUrlDoesNotExist() {
        if (!$this->mirrorURL) $this->getVars();
        $url = $this->mirrorURL . "NonExistentFile";
        $this->assertFalse(urlExists($url));
    }

    public function testGetArchitectures() {
        if (!$this->mirrorURL) $this->getVars();
        $url = $this->mirrorURL . "Release";
        $archList = getArchitectures($url);
        $this->assertIsArray($archList);
        $this->assertContains('i386', $archList);
    }

    public function testArchitectureNotInList() {
        if (!$this->mirrorURL) $this->getVars();
        $url = $this->mirrorURL . "Release";
        $archList = getArchitectures($url);
        $this->assertNotContains('nonexistent_arch', $archList);
    }

    public function testFileDecompression() {
        if (!$this->mirrorURL) $this->getVars();
        $contentsFilePathName = $this->mirrorURL . "main/" .  $this->contentsFileName;
        $zippedContentsFile = file_get_contents($contentsFilePathName);
        $contentsFile = gzdecode($zippedContentsFile);
        $this->assertNotFalse($contentsFile);
    }

    public function testBadArchitecture() {   
        if (!$this->mirrorURL) $this->getVars();
        $invalidArch = "invalid_arch";
        $archList = ["i386", "amd64", "arm64"];
        $this->assertNotContains($invalidArch, $archList);
    }   

    public function testNoArgs() {
        if (!$this->mirrorURL) $this->getVars();
        $this->assertFalse(isset($argv[1]));
    }
}


require_once("functions.php");

