<?php


$a = '{
    "authors": [
        {
            "name": "jmz",
            "email": "1125378902@qq.com"
        }
    ],
        "autoload": {
        "psr-4": {
            "App\\": "App/"
        }
    },
    "require": {
        "easyswoole/easyswoole": "3.4.x",
                "easyswoole/template": "^1.0",
                "easyswoole/mysqli": "^2.0",
                "easyswoole/verifycode": "^3.0",
                "duncan3dc/blade": "^4.5",
        "easyswoole/cache": "^1.1",
        "easyswoole/redis-pool": "^2.1",
        "easyswoole/task": "^1.1",
        "easyswoole/socket": "1.1",
        "topthink/think-orm": "^2.0",
        "joshcam/mysqli-database-class": "dev-master",
        "easyswoole/kafka": "^1.0",
        "easyswoole/orm": "1.4.30",
        "aliyuncs/oss-sdk-php": "^2.3",
        "ritaswc/zx-ip-address": "^2.0"
    }
}';
var_dump(json_decode($a, true));