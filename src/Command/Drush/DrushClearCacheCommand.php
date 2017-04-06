<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command\Drush;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Docker\Drupal\Style\DockerDrupalStyle;
use Docker\Drupal\Extension\ApplicationContainerExtension;

/**
 * Class DemoCommand
 * @package Docker\Drupal\Command
 */
class DrushClearCacheCommand extends Command {

  protected function configure() {
    $this
      ->setName('drush:cc')
      ->setDescription('Run drush cache clear ')
      ->setHelp("This command will clear Drupal APP caches.");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $application = $this->getApplication();
    $container_application = new ApplicationContainerExtension();

    $io = new DockerDrupalStyle($input, $output);
    $config = $application->getAppConfig($io);

    if (!$config) {
      $io->error('No config found. You\'re not currently in an Drupal APP directory');
      return;
    } else {
      $appname = $config['appname'];
    }

    switch ($config['apptype']) {
      case 'D8':
        $cmd = 'cr all';
        break;
      case 'D7':
        $cmd = 'cc all';
        break;
      default:
        $io->error('You\'re not currently in an Drupal APP directory');
        return;
    }

    $io->section('PHP ::: drush ' . $cmd);

    if ($container_application->checkForAppContainers($appname, $io)) {
      $command = $application->getComposePath($appname, $io) . ' exec -T php drush ' . $cmd;
      $application->runcommand($command, $io);
    }
  }

}
