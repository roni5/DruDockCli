<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command\Mysql;

use Docker\Drupal\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Docker\Drupal\Style\DruDockStyle;
use Docker\Drupal\Extension\ApplicationContainerExtension;

/**
 * Class MysqlImportExportCommand
 *
 * @package Docker\Drupal\Command\Mysql
 */
class MysqlExportCommand extends Command {

  protected function configure() {
    $this
      ->setName('mysql:export')
      ->setDescription('Export .sql files')
      ->setHelp("Use this to dump .sql files to the current running APPs drudock_db. eg. [drudock mysql:export -p ./latest.sql]")
      ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Specify export file path including filename [./latest.sql]');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $application = new Application();
    $container_application = new ApplicationContainerExtension();

    $io = new DruDockStyle($input, $output);
    $io->section('MYSQL ::: dump/export database');

    if ($config = $application->getAppConfig($io)) {
      $appname = $config['appname'];
    }
    else {
      $appname = 'app';
    }

    // GET AND SET APP TYPE
    $savepath = $input->getOption('path');

    if (!$savepath) {
      //specify save path
      $datetime = date("Y-m-d--H-i-s");
      $helper = $this->getHelper('question');
      $question = new Question('Specify save path, including filename [latest-' . $appname . '--' . $datetime . '.sql] : ', 'latest-' . $appname . '--' . $datetime . '.sql');
      $savepath = $helper->ask($input, $output, $question);
    }

    if ($container_application->checkForAppContainers($appname, $io) && isset($savepath)) {
      $command = $container_application->getComposePath($appname, $io) . 'exec -T mysql mysqldump -u drudock -pMYSQLPASS drudock_db > ' . $savepath;
      $application->runcommand($command, $io);
    }
  }
}
