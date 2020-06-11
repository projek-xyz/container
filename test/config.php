<?php
/**
 * @link https://kahlan.github.io/docs/config-file.html
 */

/** @var Kahlan\Cli\CommandLine $cli */
$cli = $this->commandLine();
$cli->option('coverage', 'default', 3);
$cli->option('spec', 'default', ['test/spec']);
