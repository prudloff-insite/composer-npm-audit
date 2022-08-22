<?php

namespace ComposerNpmAudit;

use Composer\Command\BaseCommand;
use Composer\InstalledVersions;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Jean85\PrettyVersions;
use OutOfBoundsException;
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
   * @param \Symfony\Component\Console\Style\SymfonyStyle $output
   * @param \stdClass $results
   *
   * @return int
   */
  private function printCommand(SymfonyStyle $output, stdClass $results): int {
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
   * @param \Symfony\Component\Console\Style\SymfonyStyle $output
   * @param \stdClass $results
   *
   * @return int
   */
  private function printTable(SymfonyStyle $output, stdClass $results): int {
    if (empty((array) $results->advisories)) {
      $output->success('No known vulnerability.');

      return 0;
    }

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
    $output->table(
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int
   * @noinspection PhpMissingParentCallCommonInspection
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
    $output = new SymfonyStyle($input, $output);

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
          $output->warning($package . 'is not installed');
        }
      }
    }

    if (empty($dependencies)) {
      $output->warning('This project does not use any NPM package.');

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
