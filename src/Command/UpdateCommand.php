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
use App\Service\ModService;

class UpdateCommand extends Command
{
    protected static $defaultName = 'update';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST HALFWAY_DONE = "Ark Root Server files updated, now updating each shard!";
    CONST FAILURE = "Ark Root Server files could not be updated. Please try manually running this command for further troubleshooting: ";
    CONST SUCCESS = "All shards have been updated! Ark server update complete!";
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

        $mod_service = new ModService();
        $mod_service ->user_console_controller = $service->user_console_controller;
        $mod_service->run();
        HelperService::recursiveChmod($service->root_server_files, 0755, 0755);

        if ($service->update_status === TRUE) {
            $output->writeln(SELF::HALFWAY_DONE);
            $service->updateEachShard();
            $output->writeln(SELF::SUCCESS);
        } else {
            $output->writeln(SELF::FAILURE . $service->generated_update_command);
        }
        return 0;
    }
}