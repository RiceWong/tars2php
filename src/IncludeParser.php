<?php


namespace RickyWong\Tars\compiler;


use InterfaceParser;
use StructParser;

class IncludeParser {
    public function includeScan($includeFile, &$preEnums, &$preStructs, &$preNamespaceEnums, &$preNamespaceStructs) {
        $fp = fopen($includeFile, 'r');
        if (!$fp) {
            echo 'Include file not exit, please check';
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {
            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 判断是否有module
            $moduleFlag = strpos($line, 'module');
            if ($moduleFlag !== false) {
                $name = Utils::pregMatchByName('module', $line);
                $currentModule = $name;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line, 'enum');
            if ($enumFlag !== false) {
                $name = Utils::pregMatchByName('enum', $line);
                $preEnums[] = $name;
                if (!empty($currentModule)) {
                    $preNamespaceEnums[] = $currentModule . '::' . $name;
                }
                while (($lastChar = fgetc($fp)) != '}') {
                    continue;
                }
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                $preStructs[] = $name;
                if (!empty($currentModule)) {
                    $preNamespaceStructs[] = $currentModule . '::' . $name;
                }
            }
        }
    }

    public function includeParse($includeFile, &$preEnums, &$preStructs, $uniqueName, $moduleName, $namespaceName, $servantName, &$preNamespaceEnums, &$preNamespaceStructs, $outputDir) {
        $fp = fopen($includeFile, 'r');
        if (!$fp) {
            echo 'Include file not exit, please check';
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {
            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 正则匹配,发现是在consts中
            $constFlag = strpos($line, 'const');
            if ($constFlag !== false) {
                // 直接进行正则匹配
                echo 'CONST is not supported, please make sure you deal with them yourself in this version!';
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                $structParser = new StructParser($fp, $line, $uniqueName, $moduleName, $name, $preStructs, $preEnums,
                    $namespaceName, $preNamespaceEnums, $preNamespaceStructs);
                $structClassStr = $structParser->parse();
                file_put_contents($outputDir . $moduleName . '/classes/' . $name . '.php', $structClassStr);
            }

            // 正则匹配，发现是在interface中
            $interfaceFlag = strpos(strtolower($line), 'interface');
            // 一旦发现了struct，那么持续读到结束为止
            if ($interfaceFlag !== false) {
                $name = Utils::pregMatchByName('interface', $line);

                if (in_array($name, $preStructs)) {
                    $name .= 'Servant';
                }

                $interfaceParser = new InterfaceParser($fp, $line, $namespaceName, $moduleName, $name, $preStructs,
                    $preEnums, $servantName, $preNamespaceEnums, $preNamespaceStructs);
                $interfaces = $interfaceParser->parse();

                // 需要区分同步和异步的两种方式
                file_put_contents($outputDir . $moduleName . '/' . $name . '.php', $interfaces['syn']);
            }
        }
    }
}