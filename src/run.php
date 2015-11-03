<?php

use Symfony\Component\Yaml\Yaml;

require_once(dirname(__FILE__) . "/../vendor/autoload.php");

require_once "Keboola/SilverpopEx/Silverpop.php";

$arguments = getopt("d::", array("data::"));
if (!isset($arguments["data"])) {
    print "Data folder not set.";
    exit(1);
}

$config = Yaml::parse(file_get_contents($arguments["data"] . "/config.yml"));

if (isset($config["storage"]["input"]["tables"][0]["destination"])) {
    $sourceFile  = $config["storage"]["input"]["tables"][0]["destination"];
} else {
    $sourceFile = $config["storage"]["input"]["tables"][0]["source"];
}

try {
    $silverpop = new Silverpop(
        $arguments["data"] . "/in/tables/{$sourceFile}",
        $arguments["data"] . "/out/tables/"
    );

    $silverpop->run();
} catch (Exception $e) {
    print $e->getMessage();
    exit(1);
}

exit(0);
