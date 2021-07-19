<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StartService;
use App\Service\StopService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class RestartCommand extends Command
{
    protected static $defaultName = 'restart';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = 'Which shard would you like to restart?';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Restarts the ark server of your choice.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $stop_service = new StopService();
        if (empty($stop_service->running_shards)) {
            $output->writeln(StopCommand::NONE_RUNNING);
        } else {
            //Stop The Server
            $console_controller->question = SELF::USER_QUESTION;
            $console_controller->options_list = ['Shard' => array_merge(['All'], array_keys($stop_service->running_shards))];
            $answer = $console_controller->askQuestion();
            $console_controller->drawCliHeader();
            $output->writeln(str_replace('%T', $answer['Shard'], StopCommand::ATTEMPT));
            $stop_service->stopSelectedServer($answer['Shard']);

            //Start The Server
            $start_service = new StartService();
            $choice = HelperService::translateAnswer($answer['Shard'], $start_service->shards['installed']);
            $servers = $start_service->startServers($choice);
            if (!empty($servers)) {
                foreach ($servers as $server => $result) {
                    ($result) ? $output->writeln(StartCommand::START_ECHO . $server) : $output->writeln($server . StartCommand::RUNNING);
                }
            }
        }
        
        return 0;
    }
}