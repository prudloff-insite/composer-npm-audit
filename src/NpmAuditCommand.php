<?php

namespace ComposerNpmAudit;

use Composer\Command\BaseCommand;
use Fxp\Composer\AssetPlugin\Converter\NpmPackageUtil;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use PackageVersions\Versions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NpmAuditCommand extends BaseCommand {

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  protected function configure() {
    $this->setName('npm-audit');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $vendorDir = $this->getComposer()->getConfig()->get('vendor-dir');
    require $vendorDir . '/autoload.php';

    $client = new Client();
    $io = new SymfonyStyle($input, $output);

    foreach (Versions::VERSIONS as $package => $version) {
      $packageInfo = explode('/', $package);
      $versionInfo = explode('@', $version);
      if ($packageInfo[0] == 'npm-asset') {
        $name = NpmPackageUtil::revertName($packageInfo[1]);

        try {
          // todo Utiliser une requête POST sur http://registry.npmjs.org/-/npm/v1/security/audits.
          $response = $client->get('http://registry.npmjs.org/-/npm/v1/security/audits/' . $name . '/' . $versionInfo[0]);

          $data = json_decode($response->getBody()->getContents());
          $io->section($name . ' ' . $versionInfo[0]);
          if (empty((array) $data->advisories)) {
            $io->success('No known vulnerability.');
          }
          else {
            $table = [];
            foreach ($data->advisories as $advisory) {
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
          }
        } catch (BadResponseException $e) {
          $io->error(
            'Could not get the vulnerabilites for ' . $name . ' ' . $versionInfo[0] . ': ' . PHP_EOL .
            $e->getMessage()
          );
        }
      }
    }
  }
}