<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShardImportService;
use App\Service\HelperService;
use App\Service\PortService;
use App\Controller\UserConsoleController;

class ShardImportCommand extends Command
{
    protected static $defaultName = 'import';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = "Please enter path to the shard you wish to import.";
    CONST HELP_TEXT = "NOTE: It is expected that the shard you're importing is a backup zip created via Ark Server Cron. Each shard name must be unique, meaning if the shard name matches a current shard, then the imported shard name will contain an incremented number.";
    CONST SUCCESS = "Shard imported successfully!";
    CONST FAILURE = "Unable to import the shard. Double check the file path & permissions. Is it a zip file?";

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Imports a shard from a saved backup.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new ShardImportService();
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->help_text = SELF::HELP_TEXT;
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();
        if ($service->run($answer)) {
            if ($service->port_range != '') {
                //check to see if we have the port range configuration set and then ask if the user wants ports auto allocated.
                $port_service = new PortService();
                $port_service->console_controller = $console_controller;
                $reallocate_ports = $port_service->performUserCheck();
                $console_controller->drawCliHeader();
                if ($reallocate_ports === TRUE && $port_service->portAllocation() === TRUE) {
                    $port_service->writeNewPorts($service->new_shard_name);
                } else {
                    $output->writeln($port_service::ALLOCATION_FAILURE);
                }
            }
            $output->writeln(SELF::SUCCESS);
        } else {
            $output->writeln(SELF::FAILURE);
        }
        
        return 0;
    }
}