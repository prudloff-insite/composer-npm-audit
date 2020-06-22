<?php

namespace ComposerNpmAudit;

use Composer\Plugin\Capability\CommandProvider;

class NpmAuditCommandProvider implements CommandProvider {

  /**
   * @return \Composer\Command\BaseCommand[]|\ComposerNpmAudit\NpmAuditCommand[]
   */
  public function getCommands() {
    return [
      new NpmAuditCommand()
    ];
  }
}