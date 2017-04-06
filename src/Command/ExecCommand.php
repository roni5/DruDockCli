<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command;

use Symfony\Component\Console\Command\Command;
//use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Docker\Drupal\Style\DockerDrupalStyle;
use Docker\Drupal\Extension\ApplicationContainerExtension;

/**
 * Class DemoCommand
 * @package Docker\Drupal\Command
 */
class ExecCommand extends Command {
  protected function configure() {
    $this
      ->setName('docker:exec')
      ->setAliases(['exec'])
      ->setDescription('Execute bespoke commands at :container')
      ->setHelp("This command will run command inside specified container")
      ->addOption('service', 's', InputOption::VALUE_OPTIONAL, 'Specify the service/container [php]')
      ->addOption('cmd', 'c', InputOption::VALUE_OPTIONAL, 'Specify the command ["bash"]');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {

    $application = $this->getApplication();
    $container_application = new ApplicationContainerExtension();

    $cmd = $input->getOption('cmd');
    $service = $input->getOption('service');

    $io = new DockerDrupalStyle($input, $output);
    $io->section("EXEC CMD");

    $running_containers = $application->getRunningContainerNames();
    $available_services = [];

    foreach ($running_containers as $c) {
      $name_parts = explode('_', $c);
      $available_services[] = $name_parts[1];
    }

    if (!$service) {
      $helper = $this->getHelper('question');
      $question = new ChoiceQuestion(
        'Which service/container? : ',
        $available_services
      );
      $service = $helper->ask($input, $output, $question);
    }

    $config = $application->getAppConfig($io);
    if ($config) {
      $appname = $config['appname'];
    }

    if (!$cmd) {
      $helper = $this->getHelper('question');
      $question = new Question('Enter command : ', 'bash');
      $cmd = $helper->ask($input, $output, $question);
    }

    if ($container_application->checkForAppContainers($appname, $io)) {
      $command = $application->getComposePath($appname, $io) . 'exec -T ' . $service . ' ' . $cmd . ' 2>&1';
      $application->runcommand($command, $io);
    }
  }
}