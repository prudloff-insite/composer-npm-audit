<?php

namespace ComposerNpmAudit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

class NpmAuditPlugin implements PluginInterface, Capable {

  /**
   * @param \Composer\Composer $composer
   * @param \Composer\IO\IOInterface $io
   */
  public function activate(Composer $composer, IOInterface $io) {
  }

  /**
   * @return string[]
   */
  public function getCapabilities() {
    return [
      CommandProvider::class => NpmAuditCommandProvider::class,
    ];
  }
}