<?php

namespace ComposerNpmAudit;

use Composer\Plugin\Capability\CommandProvider;

class NpmAuditCommandProvider implements CommandProvider {

  /**
   * @inheritDoc
   */
  public function getCommands() {
    return [
      new NpmAuditCommand()
    ];
  }
}