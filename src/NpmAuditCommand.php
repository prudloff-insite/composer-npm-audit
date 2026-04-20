<?php

namespace ComposerNpmAudit;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Jean85\PrettyVersions;
use OutOfBoundsException;
use stdClass;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NpmAuditCommand
 *
 * @package ComposerNpmAudit
 */
class NpmAuditCommand extends BaseCommand {

  /**
   * @return void
   */
  protected function configure(): void {
    parent::configure();

    $this->setName('npm-audit')
      ->setDescription('Detect known vulnerable versions of NPM packages')
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
  private static function revertName($name): string {
    if (str_contains($name, '--')) {
      $name = '@' . str_replace('--', '/', $name);
    }

    return $name;
  }

  /**
   * @param OutputInterface $output
   * @param \stdClass $results
   *
   * @return int
   */
  private function printCommand(OutputInterface $output, stdClass $results): int {
    $require = [];
    foreach ($results as $package => $advisories) {
      $require[] = "'npm-asset/" . $package . "'";
    }

    if (!empty($require)) {
      $output->writeln('composer require ' . implode(' ', $require) . ' --update-with-dependencies');
    }

    return 0;
  }

  /**
   * @param OutputInterface $output
   * @param \stdClass $results
   *
   * @return int
   */
  private function printTable(OutputInterface $output, stdClass $results): int {
    if (empty((array) $results)) {
      $output->writeln('<info>No known vulnerability.</info>');

      return 0;
    }

    $rows = [];
    foreach ($results as $package => $advisories) {
      foreach ($advisories as $advisory) {
        $rows[] = [
          $advisory->severity,
          $advisory->title,
          $package,
          $advisory->vulnerable_versions,
          $advisory->url,
        ];
      }
    }
    $table = new Table($output);

    $table->setHeaders([
      'Severity',
      'Title',
      'Dependency',
      'Vulnerable versions',
      'URL',
    ]);
    $table->setRows($rows);
    $table->render();

    return 1;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @noinspection PhpMissingParentCallCommonInspection
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $composer = $this->tryComposer();
    if (isset($composer)) {
      $vendorDir = $this->requireComposer()->getConfig()->get('vendor-dir');
    }
    else {
      $vendorDir = __DIR__ . '/../vendor/';
    }
    require $vendorDir . '/autoload.php';

    $client = new Client();

    $dependencies = [];
    foreach (InstalledVersions::getInstalledPackagesByType('npm-asset') as $package) {
      try {
        $packageInfo = explode('/', $package);
        $versionInfo = PrettyVersions::getVersion($package);

        if ($packageInfo[0] == 'npm-asset') {
          $name = $this->revertName($packageInfo[1]);
          $dependencies[$name][] = $versionInfo->getShortVersion();
        }
      } catch (OutOfBoundsException) {
        if ($output->isDebug()) {
          $output->writeln('<comment>' . $package . ' is not installed</comment>');
        }
      }
    }

    if (empty($dependencies)) {
      $output->writeln('<comment>This project does not use any NPM package.</comment>');

      return 0;
    }

    $response = $client->post(
      'https://registry.npmjs.org/-/npm/v1/security/advisories/bulk',
      [
        RequestOptions::JSON => $dependencies,
      ]
    );
    $results = json_decode($response->getBody()->getContents());

    if ($input->getOption('json')) {
      $output->write(json_encode($results));

      return 0;
    }
    elseif ($input->getOption('command')) {
      return $this->printCommand($output, $results);
    }
    else {
      return $this->printTable($output, $results);
    }

  }
}
