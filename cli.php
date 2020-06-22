<?php

use ComposerNpmAudit\NpmAuditCommand;
use Symfony\Component\Console\Application;

require_once __DIR__.'/vendor/autoload.php';

$application = new Application();

$application->add(new NpmAuditCommand());

try {
  $application->run();
} catch (Exception $e) {
  die($e->getMessage());
}