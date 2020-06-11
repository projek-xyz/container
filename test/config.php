<?php

/** @var Kahlan\Cli\CommandLine $cli */

use Kahlan\Filter\Filters;

$cli = $this->commandLine();
$cli->option('spec', 'default', ['test']);
$cli->option('grep', 'default', ['*.test.php']);

Filters::apply($this, 'namespaces', function($next) {
    $this->autoloader()->addPsr4('Projek\\ContainerStub\\', __DIR__ . '/stub/');

    return $next();
});
