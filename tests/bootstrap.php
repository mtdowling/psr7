<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('PHPUnit\Framework\Error\Warning')) {
    class_alias('PHPUnit_Framework_Error_Warning', 'PHPUnit\Framework\Error\Warning');
}
