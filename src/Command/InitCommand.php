<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Docker\Drupal\Style\DockerDrupalStyle;


/**
 * Class DemoCommand
 * @package Docker\Drupal\Command
 */
class InitCommand extends ContainerAwareCommand
{


    protected function configure()
    {
        $this
            ->setName('build:init')
            ->setAliases(['build'])
            ->setDescription('Fetch and build DockerDrupal containers')
            ->setHelp('This command will fetch the specified DockerDrupal config, download and build all necessary images.')
            ->addArgument('appname', InputArgument::OPTIONAL, 'Specify NAME of application to build [app-dd-mm-YYYY]')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Specify app version [D7,D8,DEFAULT]')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $application = $this->getApplication();
        $io = new DockerDrupalStyle($input, $output);
        $fs = new Filesystem();
        $date =  date('Y-m-d--H-i-s');

        $appname = $input->getArgument('appname');

        if(!$appname){
            $io->title("SET APP NAME");
            $helper = $this->getHelper('question');
            $question = new Question('Enter App name [dd_app_'.$date.'] : ', 'my-app-'.$date);
            $appname = $helper->ask($input, $output, $question);
        }

        $type = $input->getOption('type');
        $available_types = array('DEFAULT', 'D7', 'D8');

        if($type && !in_array($type, $available_types)){
            $io->warning('TYPE : '.$type.' not allowed.');
            $type = null;
        }

        if(!$type){
            $io->title("SET APP TYPE");
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Select your APP type : ',
                $available_types,
                '0,1'
            );
            $type = $helper->ask($input, $output, $question);
        }

        if(!$fs->exists($appname)){
            $fs->mkdir($appname , 0755);
            $fs->mkdir($appname.'/docker_'.$appname , 0755);
        }else{
            $io->error('This app already exists');
            return;
        }

        $message = 'Fetching DockerDrupal v'.$application->getVersion();
        $io->note($message);
        $command = 'git clone https://github.com/4alldigital/DockerDrupal-lite.git '.$appname.'/docker_'.$appname;
        $this->runcommand($command);

        /**
         * Install specific APP type
         */
        if(isset($type) && $type == 'DEFAULT'){
            $this->setUpExampleApp($fs, $io, $appname);
        }
        if(isset($type) && $type == 'D7'){
            $this->setupD7($fs, $io, $appname);
        }
        if(isset($type) && $type == 'D8'){
            $this->setupD8($fs, $io, $appname);
        }

        $this->initDocker($io, $appname);

        $this->installDrupal8($io);

        $message = 'Opening Drupal 8 base Installation at http://docker.dev';
        $io->comment($message);
        shell_exec('python -mwebbrowser http://docker.dev');

    }

    protected function runcommand($command){
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        echo $process->getOutput();
    }

    private function initDocker($io, $appname){

        if(exec('docker ps -q 2>&1', $exec_output)) {
            $dockerstopcmd = 'docker stop $(docker ps -q)';
            $this->runcommand($dockerstopcmd);
        }

        $message = 'Download and configure DockerDrupal.... This may take a few minutes....';
        $io->comment($message);

        // Run Unison APP SYNC so that PHP working directory is ready to go with DATA stored in the Docker Volume.
        // When 'Synchronization complete' kill this temp run container and start DockerDrupal.
        $dockerlogs = 'docker-compose -f '.$appname.'/docker_'.$appname.'/docker-compose.yml logs -f';
        $this->runcommand($dockerlogs);

        $dockercmd = 'until docker-compose -f '.$appname.'/docker_'.$appname.'/docker-compose.yml run app 2>&1 | grep -m 1 -e "Synchronization complete" -e "Nothing to do"; do : ; done';
        $this->runcommand($dockercmd);

        $dockercmd = 'docker kill $(docker ps -q)';
        $this->runcommand($dockercmd);

        $dockercmd = 'docker-compose -f '.$appname.'/docker_'.$appname.'/docker-compose.yml up -d';
        $this->runcommand($dockercmd);

    }

    private function setupD7($fs, $io, $appname){

        $app_dest = $appname.'/app';
        $date =  date('Y-m-d--H-i-s');

        $application = $this->getApplication();
        $utilRoot = $application->getUtilRoot();

    }

    private function setupD8($fs, $io, $appname){

        $app_dest = $appname.'/app';
        $date =  date('Y-m-d--H-i-s');

        $application = $this->getApplication();
        $utilRoot = $application->getUtilRoot();

        try {
            $fs->mkdir($app_dest);

            $fs->mkdir($app_dest.'/repository/config/sync');
            $fs->mkdir($app_dest.'/repository/libraries/custom');
            $fs->mkdir($app_dest.'/repository/modules/custom');
            $fs->mkdir($app_dest.'/repository/profiles/custom');
            $fs->mkdir($app_dest.'/repository/themes/custom');

            $fs->mkdir($app_dest.'/shared/files');

        } catch (IOExceptionInterface $e) {
            //echo 'An error occurred while creating your directory at '.$e->getPath();
            $io->error(sprintf('An error occurred while creating your directory at '.$e->getPath()));
        }

        // build repo content
        if (is_dir($utilRoot . '/bundles/d8') && is_dir($app_dest.'/repository')) {
            $d8files = $utilRoot . '/bundles/d8';
            // potential repo files
            $fs->copy($d8files.'/composer.json', $app_dest.'/repository/composer.json');
            $fs->copy($d8files.'/development.services.yml', $app_dest.'/repository/development.services.yml');
            $fs->copy($d8files.'/services.yml', $app_dest.'/repository/services.yml');
            $fs->copy($d8files.'/robots.txt', $app_dest.'/repository/robots.txt');
            $fs->copy($d8files.'/settings.php', $app_dest.'/repository/settings.php');
            //local shared files
            $fs->copy($d8files.'/settings.local.php', $app_dest.'/shared/settings.local.php');
        }

        // download D8 - ask for version ?? [8.1.8]
        $command = sprintf('composer create-project drupal/drupal:8.2.x-dev '.$app_dest.'/builds/'.$date.'/public --stability dev --no-interaction');
        $io->comment('Download and configure Drupal 8.... This may take a few minutes....');
        $this->runcommand($command);

        $buildpath = 'builds/'.$date.'/public';
        $fs->symlink($buildpath, $app_dest.'/www', true);

        $rel = $fs->makePathRelative($app_dest.'/repository/', $app_dest.'/'.$buildpath);
        $fs->remove(array($app_dest.'/'.$buildpath.'/composer.json'));
        $fs->symlink($rel.'composer.json', $app_dest.'/'.$buildpath.'/composer.json', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/robots.txt'));
        $fs->symlink($rel.'robots.txt', $app_dest.'/'.$buildpath.'/robots.txt', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/sites/development.services.yml'));
        $fs->symlink('../'.$rel.'development.services.yml', $app_dest.'/'.$buildpath.'/sites/development.services.yml', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/sites/default/services.yml'));
        $fs->symlink('../../'.$rel.'services.yml', $app_dest.'/'.$buildpath.'/sites/default/services.yml', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/sites/default/settings.php'));
        $fs->symlink('../../'.$rel.'settings.php', $app_dest.'/'.$buildpath.'/sites/default/settings.php', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/sites/default/files'));
        $fs->symlink('../../../../../shared/settings.local.php', $app_dest.'/'.$buildpath.'/sites/default/settings.local.php', true);
        $fs->remove(array($app_dest.'/'.$buildpath.'/sites/default/files'));
        $fs->symlink('../../../../../shared/files', $app_dest.'/'.$buildpath.'/sites/default/files', true);

        $fs->chmod($app_dest.'/'.$buildpath.'/sites/default/files', 0777, 0000, true);
        $fs->chmod($app_dest.'/'.$buildpath.'/sites/default/settings.php', 0777, 0000, true);
        $fs->chmod($app_dest.'/'.$buildpath.'/sites/default/settings.local.php', 0777, 0000, true);

    }

    private function setupExampleApp($fs, $io, $appname){

        $message = 'Setting up Example app';
        $io->note($message);
        // example app source and destination
        $app_src = $appname.'/docker_'.$appname.'/example/app/';
        $app_dest = $appname.'/app/repository/';

        try {
            $fs->mkdir($app_dest);
            $fs->mirror($app_src, $app_dest);
        } catch (IOExceptionInterface $e) {
            echo 'An error occurred while creating your directory at '.$e->getPath();
        }

        $io->note($appname.'www');
        $fs->symlink('repository', $appname.'/app/www', true);

    }

    private function installDrupal8($io){
        // Check for running mySQL container before launching Drupal Installation
        $message = 'Waiting for mySQL service.';
        $io->warning($message);
        while (!@mysqli_connect('127.0.0.1', 'dev', 'DEVPASSWORD', 'dev_db')) {
            sleep(1);
            echo '.';
        }
        $io->text(' ');
        $message = 'mySQL CONNECTED';
        $io->success($message);

        $message = 'Run Drupal Installation.... This may take a few minutes....';
        $io->comment($message);
        $installcmd = 'docker exec -i $(docker ps --format {{.Names}} | grep php) drush site-install standard --account-name=dev --account-pass=admin --site-name=DockerDrupal --site-mail=drupalD8@docker.dev --db-url=mysql://dev:DEVPASSWORD@db:3306/dev_db --quiet -y';
        $this->runcommand($installcmd);
    }


}