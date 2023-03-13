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
  protected function configure() {
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
    if (FALSE !== strpos($name, '--')) {
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
    foreach ($results->advisories as $advisory) {
      $require[] = "'npm-asset/" . $advisory->module_name . ':' . $advisory->patched_versions . "'";
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
    if (empty((array) $results->advisories)) {
      $output->writeln('<info>No known vulnerability.</info>');

      return 0;
    }

    $rows = [];
    foreach ($results->advisories as $advisory) {
      $rows[] = [
        $advisory->severity,
        $advisory->title,
        $advisory->module_name,
        $advisory->vulnerable_versions,
        $advisory->recommendation,
        $advisory->url,
      ];
    }
    $table = new Table($output);

    $table->setHeaders([
      'Severity',
      'Title',
      'Dependency',
      'Vulnerable versions',
      'Recommendation',
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
    $composer = $this->getComposer(FALSE);
    if (isset($composer)) {
      $vendorDir = $this->getComposer()->getConfig()->get('vendor-dir');
    }
    else {
      $vendorDir = __DIR__ . '/../vendor/';
    }
    require $vendorDir . '/autoload.php';

    $client = new Client();

    $requires = [];
    $dependencies = [];
    foreach (InstalledVersions::getInstalledPackagesByType('npm-asset') as $package) {
      try {
        $packageInfo = explode('/', $package);
        $versionInfo = PrettyVersions::getVersion($package);

        if ($packageInfo[0] == 'npm-asset') {
          $name = $this->revertName($packageInfo[1]);
          $requires[$name] = $versionInfo->getShortVersion();
          $dependencies[$name] = [
            'version' => $versionInfo->getShortVersion(),
          ];
        }
      } catch (OutOfBoundsException $e) {
        if ($output->isDebug()) {
          $output->writeln('<comment>' . $package . 'is not installed</comment>');
        }
      }
    }

    if (empty($dependencies)) {
      $output->writeln('<comment>This project does not use any NPM package.</comment>');

      return 0;
    }

    $response = $client->post(
      'https://registry.npmjs.org/-/npm/v1/security/audits',
      [
        RequestOptions::BODY => json_encode([
          'dependencies' => $dependencies,
          'requires' => $requires,
        ]),
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
