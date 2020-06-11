<?php
/**
 * @link https://kahlan.github.io/docs/config-file.html
 */

/** @var Kahlan\Cli\CommandLine $cli */

use Kahlan\Filter\Filters;
use Kahlan\Reporter\Coverage\Exporter\Coveralls;

$cli = $this->commandLine();
$cli->option('coverage', 'default', 3);
$cli->option('grep', 'default', ['*.test.php']);
$cli->option('spec', 'default', ['test']);

Filters::apply($this, 'namespaces', function($next) {
    $this->autoloader()->addPsr4('Projek\\ContainerStub\\', __DIR__ . '/stub/');

    return $next();
});
