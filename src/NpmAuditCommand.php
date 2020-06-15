<?php

namespace ComposerNpmAudit;

use Composer\Command\BaseCommand;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PackageVersions\Versions;
use stdClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class NpmAuditCommand
 *
 * @package ComposerNpmAudit
 */
class NpmAuditCommand extends BaseCommand {

  /**
   *
   */
  protected function configure() {
    $this->setName('npm-audit')
      ->addOption('json', 'j', InputOption::VALUE_NONE, 'Display result as JSON')
      ->addOption('command', 'c', InputOption::VALUE_NONE, 'Generate a Composer command');
  }

  /**
   * Reused from fxp/composer-asset-plugin.
   *
   * @param $name
   *
   * @return string
   */
  private static function revertName($name)
  {
    if (false !== strpos($name, '--')) {
      $name = '@'.str_replace('--', '/', $name);
    }

    return $name;
  }

  /**
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   * @param \stdClass $results
   *
   * @return int
   */
  private function printCommand(SymfonyStyle $io, stdClass $results) {
    $require = [];
    foreach ($results->advisories as $advisory) {
      $require[] = "'npm-asset/" . $advisory->module_name . ':' . $advisory->patched_versions . "'";
    }

    if (!empty($require)) {
      $io->writeln('composer require ' . implode(' ', $require) . ' --update-with-dependencies');
    }

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   * @param \stdClass $results
   *
   * @return int
   */
  private function printTable(SymfonyStyle $io, stdClass $results) {
    if (empty((array) $results->advisories)) {
      $io->success('No known vulnerability.');

      return 0;
    }
    else {
      $table = [];
      foreach ($results->advisories as $advisory) {
        $table[] = [
          $advisory->severity,
          $advisory->title,
          $advisory->module_name,
          $advisory->vulnerable_versions,
          $advisory->recommendation,
          $advisory->url,
        ];
      }

      $io->table(
        [
          'Severity',
          'Title',
          'Dependency',
          'Vulnerable versions',
          'Recommendation',
          'URL',
        ],
        $table
      );

      return 1;
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $vendorDir = $this->getComposer()->getConfig()->get('vendor-dir');
    require $vendorDir . '/autoload.php';

    $client = new Client();
    $io = new SymfonyStyle($input, $output);

    $requires = [];
    $dependencies = [];
    foreach (Versions::VERSIONS as $package => $version) {
      $packageInfo = explode('/', $package);
      $versionInfo = explode('@', $version);
      if ($packageInfo[0] == 'npm-asset') {
        $name = $this->revertName($packageInfo[1]);
        $requires[$name] = $versionInfo[0];
        $dependencies[$name] = [
          'version' => $versionInfo[0],
        ];
      }
    }

    $response = $client->post(
      'http://registry.npmjs.org/-/npm/v1/security/audits',
      [
        RequestOptions::BODY => json_encode([
          'dependencies' => $dependencies,
          'requires' => $requires,
        ]),
      ]
    );
    $results = json_decode($response->getBody()->getContents());

    if ($input->getOption('json')) {
      return json_encode($results);
    }
    elseif ($input->getOption('command')) {
      return $this->printCommand($io, $results);
    } else {
      return $this->printTable($io, $results);
    }

  }
}
