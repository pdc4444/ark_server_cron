<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StopService;
use App\Service\ModService;
use App\Controller\UserConsoleController;

class ModUpdateCommand extends Command
{
    protected static $defaultName = 'modupdate';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST STOP_SERVICE_CHOICE = 'All';
    CONST DESCRIPTION = 'Updates the ark server of your choice.';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription(SELF::DESCRIPTION)
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new ModService();
        $service->user_console_controller = $console_controller;
        $stop_service = new StopService();
        if (!empty($stop_service->running_shards)) {
            //Stop The Server
            $stop_service->stopSelectedServer(SELF::STOP_SERVICE_CHOICE);
        }
        $console_controller->drawCliHeader();
        $service->run();
        
        return 0;
    }
}