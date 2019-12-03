<?php

namespace ComposerNpmAudit;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

class NpmAuditPlugin implements PluginInterface, Capable {

  /**
   * @var \Composer\Composer
   */
  private $composer;

  /**
   * @var \Composer\IO\IOInterface
   */
  private $io;

  /**
   * @inheritDoc
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * @inheritDoc
   */
  public function getCapabilities() {
    return [
      CommandProvider::class => NpmAuditCommandProvider::class,
    ];
  }
}