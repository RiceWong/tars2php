
# tars 接口文件编译器 使用说明

数据结构及配置文件请参照 [官方文档](https://github.com/TarsPHP/tars2php/blob/master/README.md) 

## 安装
在composer.json require 中添加如依赖
```json
...
    "require": {
        ...
        "rickywong/tars-php-compiler": "^1.0.0"
        ...
    }
....
```
或者
```bash
composer require rickywong/tars-php-compiler
```
### 使用
```bash
./vendor/rickywong/tars-php-compiler/bin/compile.sh path/to/your/tars.proto.php
```