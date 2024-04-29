<?php

use Yoast\WPTestUtils\BrainMonkey\TestCase;

$composer = __DIR__.'/../vendor/autoload.php';
require_once $composer;

uses()->group('integration')->in('Integration');
uses()->group('unit')->in('Unit');

uses(TestCase::class)->in('Unit');
