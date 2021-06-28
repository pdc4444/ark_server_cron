<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StopService;
use App\Service\UpdateService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class UpdateCommand extends Command
{
    protected static $defaultName = 'update';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST SUCCESS = "Ark Server update complete!";
    CONST FAILURE = "Ark Server could not be updated! Try manually running this command to observe the error:\n";
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
        $service = new UpdateService();
        $service->user_console_controller = $console_controller;
        $console_controller->drawCliHeader();
        $stop_service = new StopService();
        if (!empty($stop_service->running_shards)) {
            //Stop The Server
            $stop_service->stopSelectedServer(SELF::STOP_SERVICE_CHOICE);
        }
        $service->run();

        if ($service->update_status === TRUE) {
            $output->writeln(SELF::SUCCESS);
        } else {
            $output->writeln(SELF::FAILURE . $service->generated_update_command);
        }
        $output->writeln(SELF::FAILURE . $service->generated_update_command);
        return 0;
    }
}