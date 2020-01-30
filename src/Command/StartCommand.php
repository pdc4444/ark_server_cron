<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StartService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class StartCommand extends Command
{
    protected static $defaultName = 'start';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = 'Which shard would you like to start?';
    CONST START_ECHO = "Starting Ark server: ";
    CONST RUNNING = " is already running! Did you mean to use the restart or stop commands?";

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Starts the ark server of your choice.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new StartService();
        $raw_shard_data = $service->shards;
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->options_list = ['Shard' => array_merge(['All'], HelperService::extractShardNames($service->shards['installed']))];
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();

        $choice = HelperService::translateAnswer($answer['Shard'], $service->shards['installed']);
        $servers = $service->startServers($choice);
        if (!empty($servers)) {
            foreach ($servers as $server => $result) {
                ($result) ? $output->writeln(SELF::START_ECHO . $server) : $output->writeln($server . SELF::RUNNING);
            }
        }
        
        return 0;
    }
}