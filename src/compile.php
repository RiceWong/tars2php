<?php
namespace RickyWong\Tars\compiler;
require __DIR__.'/FileConverter.php';
require __DIR__.'/IncludeParser.php';
require __DIR__.'/InterfaceParser.php';
require __DIR__.'/ServantParser.php';
require __DIR__.'/StructParser.php';
require __DIR__.'/Utils.php';

$configFile = $argv[1];
$fileConverter = new FileConverter($configFile);

$fileConverter->moduleScan();

$fileConverter->moduleParse();
