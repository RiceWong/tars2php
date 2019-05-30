<?php


namespace RickyWong\Tars\compiler;

class FileConverter {
    public $moduleName;
    public $uniqueName;
    public $interfaceName;
    public $fromFile;
    public $outputDir;

    public $appName;
    public $serverName;
    public $objName;
    public $servantName;

    public $namespaceName;
    public $namespacePrefix;

    public $preStructs = [];
    public $preEnums = [];
    public $preConsts = [];
    public $preNamespaceStructs = [];
    public $preNamespaceEnums = [];

    public function __construct($config) {
        $basePath = realpath(dirname($config)).'/';
        $config = require $config;
        $this->fromFile = $basePath.$config['tarsFiles'][0];

        if (empty($config['appName']) || empty($config['serverName']) || empty($config['objName'])) {
            Utils::abnormalExit('error', 'appName or serverName or objName empty!');
        }
        $this->servantName = $config['appName'] . '.' . $config['serverName'] . '.' . $config['objName'];

        $this->appName = $config['appName'];
        $this->serverName = $config['serverName'];
        $this->objName = $config['objName'];

        $this->outputDir = realpath($basePath.(empty($config['dstPath']) ? './' : $config['dstPath'] . '/')).'/';

        $pos = strrpos($this->fromFile, '/', -1);
        $inputDir = substr($this->fromFile, 0, $pos);
        $this->inputDir = $inputDir;

        $this->namespacePrefix = $config['namespacePrefix'];
        $this->withServant = $config['withServant'];

        $this->initDir();
    }

    /**
     * 首先需要初始化一些文件目录.
     *
     * @return [type] [description]
     */
    public function initDir() {
        if (strtolower(substr(php_uname('a'), 0, 3)) === 'win') {
            exec('mkdir ' . $this->outputDir . $this->appName);
            exec('mkdir ' . $this->outputDir . $this->appName . '\\' . $this->serverName);
            exec('DEL ' . $this->outputDir . $this->appName . '\\' . $this->serverName . '\\' . $this->objName . '\\*.*');
            exec('mkdir ' . $this->outputDir . $this->appName . '\\' . $this->serverName . '\\' . $this->objName);

            $this->moduleName = $this->appName . '\\' . $this->serverName . '\\' . $this->objName;

            exec('mkdir ' . $this->outputDir . $this->moduleName . '\\classes');
            exec('mkdir ' . $this->outputDir . $this->moduleName . '\\tars');
            exec('copy ' . $this->fromFile . ' ' . $this->outputDir . $this->moduleName . '\\tars');
        } else {
            $appPath = $this->outputDir . $this->appName;
            $serverPath = $appPath . '/' .  $this->serverName;
            $objPath = $this->outputDir . $this->appName . '/' . $this->serverName . '/' . $this->objName;
            if (!is_dir($appPath)){
                exec('mkdir ' . $appPath);
            }
            if (!is_dir($serverPath)){
                exec('mkdir ' . $serverPath);
            }
            exec('rm -rf ' . $objPath);
            exec('mkdir ' . $objPath);

            $this->moduleName = $this->appName . '/' . $this->serverName . '/' . $this->objName;

            exec('mkdir ' . $this->outputDir . $this->moduleName . '/classes');
            exec('mkdir ' . $this->outputDir . $this->moduleName . '/tars');
            exec('cp ' . $this->fromFile . ' ' . $this->outputDir . $this->moduleName . '/tars');
        }

        $this->namespaceName = empty($this->namespacePrefix) ? $this->appName . '\\' . $this->serverName . '\\' . $this->objName : $this->namespacePrefix . '\\' . $this->appName . '\\' . $this->serverName . '\\' . $this->objName;

        $this->uniqueName = $this->appName . '_' . $this->serverName . '_' . $this->objName;
    }

    public function usage() {
        echo 'php tars2php.php tars.proto.php';
    }

    public function moduleScan() {
        $fp = fopen($this->fromFile, 'r');
        if (!$fp) {
            $this->usage();
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有module
            $moduleFlag = strpos($line, 'module');
            if ($moduleFlag !== false) {
                $name = Utils::pregMatchByName('module', $line);
                $currentModule = $name;
            }

            // 判断是否有include
            $includeFlag = strpos($line, '#include');
            if ($includeFlag !== false) {
                // 找出tars对应的文件名
                $tokens = preg_split('/#include/', $line);
                $includeFile = trim($tokens[1], "\" \r\n");

                if (strtolower(substr(php_uname('a'), 0, 3)) === 'win') {
                    exec('copy ' . $includeFile . ' ' . $this->moduleName . '\\tars');
                } else {
                    exec('cp ' . $includeFile . ' ' . $this->moduleName . '/tars');
                }

                $includeParser = new IncludeParser();
                $includeParser->includeScan($includeFile, $this->preEnums, $this->preStructs, $this->preNamespaceEnums,
                    $this->preNamespaceStructs);
            }

            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*' || trim($line) === '{') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line, 'enum');
            if ($enumFlag !== false) {
                $name = Utils::pregMatchByName('enum', $line);
                if (!empty($name)) {
                    $this->preEnums[] = $name;

                    // 增加命名空间以备不时之需
                    if (!empty($currentModule)) {
                        $this->preNamespaceEnums[] = $currentModule . '::' . $name;
                    }

                    while (($lastChar = fgetc($fp)) != '}') {
                        continue;
                    }
                }
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                if (!empty($name)) {
                    $this->preStructs[] = $name;
                    // 增加命名空间以备不时之需
                    if (!empty($currentModule)) {
                        $this->preNamespaceStructs[] = $currentModule . '::' . $name;
                    }
                }
            }
        }
        fclose($fp);
    }

    public function moduleParse() {
        $fp = fopen($this->fromFile, 'r');
        if (!$fp) {
            $this->usage();
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有include
            $includeFlag = strpos($line, '#include');
            if ($includeFlag !== false) {
                // 找出tars对应的文件名
                $tokens = preg_split('/#include/', $line);
                $includeFile = trim($tokens[1], "\" \r\n");
                $includeParser = new IncludeParser();
                $includeParser->includeParse($includeFile, $this->preEnums, $this->preStructs, $this->uniqueName,
                    $this->moduleName, $this->namespaceName, $this->servantName, $this->preNamespaceEnums,
                    $this->preNamespaceStructs, $this->outputDir);
            }

            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line, 'enum');
            if ($enumFlag !== false) {
                // 处理第一行,正则匹配出classname
                $enumTokens = preg_split('/enum/', $line);

                $enumName = $enumTokens[1];
                $enumName = trim($enumName, " \r\0\x0B\t\n{");

                // 判断是否是合法的structName
                preg_match('/[a-zA-Z][0-9a-zA-Z]/', $enumName, $matches);
                if (empty($matches)) {
                    Utils::abnormalExit('error', 'Enum名称有误');
                }

                $this->preEnums[] = $enumName;
                while (($lastChar = fgetc($fp)) != '}') {
                    continue;
                }
            }

            // 正则匹配,发现是在consts中
            $constFlag = strpos($line, 'const');
            if ($constFlag !== false) {
                // 直接进行正则匹配
                Utils::abnormalExit('warning',
                    'const is not supported, please make sure you deal with them yourself in this version!');
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                $structParser = new StructParser($fp, $line, $this->uniqueName, $this->moduleName, $name,
                    $this->preStructs, $this->preEnums, $this->namespaceName, $this->preNamespaceEnums,
                    $this->preNamespaceStructs);
                $structClassStr = $structParser->parse();
                $file = $this->outputDir . $this->moduleName . '/classes/' . $name . '.php';
                echo "generate: $file\n";
                file_put_contents($file, $structClassStr);
            }

            // 正则匹配，发现是在interface中
            $interfaceFlag = strpos(strtolower($line), 'interface');
            // 一旦发现了struct，那么持续读到结束为止
            if ($interfaceFlag !== false) {
                $name = Utils::pregMatchByName('interface', $line);
                $interfaceName = $name . 'Servant';

                // 需要区分一下生成server还是client的代码
                if ($this->withServant) {
                    $servantParser = new ServantParser($fp, $line, $this->namespaceName, $this->moduleName,
                        $interfaceName, $this->preStructs, $this->preEnums, $this->servantName,
                        $this->preNamespaceEnums, $this->preNamespaceStructs);
                    $servant = $servantParser->parse();
                    echo "generate: $file\n";
                    file_put_contents($this->outputDir . $this->moduleName . '/' . $interfaceName . '.php', $servant);
                } else {
                    $interfaceParser = new InterfaceParser($fp, $line, $this->namespaceName, $this->moduleName,
                        $interfaceName, $this->preStructs, $this->preEnums, $this->servantName,
                        $this->preNamespaceEnums, $this->preNamespaceStructs);
                    $interfaces = $interfaceParser->parse();
                    $file = $this->outputDir . $this->moduleName . '/' . $interfaceName . '.php';
                    echo "generate: $file\n";
                    // 需要区分同步和异步的两种方式
                    file_put_contents($file,  $interfaces['syn']);
                }
            }
        }
    }
}