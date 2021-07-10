<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StopService;
use App\Service\UpdateService;
use App\Service\ModService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class ModUpdateCommand extends Command
{
    protected static $defaultName = 'modupdate';
    CONST SERVICE_TITLE = "Ark Server Cron";
    // CONST HALFWAY_DONE = "Ark Root Server files updated, now updating each shard!";
    // CONST FAILURE = "Ark Root Server files could not be updated. Please try manually running this command for further troubleshooting: ";
    // CONST SUCCESS = "All shards have been updated! Ark server update complete!";
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
        $console_controller->drawCliHeader();
        // print_r($service->mod_list);
        $service->run();
        
        return 0;
    }
}