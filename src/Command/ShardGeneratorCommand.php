<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShardGeneratorService;
use App\Service\PortService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class ShardGeneratorCommand extends Command
{
    protected static $defaultName = 'new';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = "Are you sure you want to create a new server shard?";
    CONST HELP_TEXT = "A shard is an entirely new Ark server which can run in parallel to your other installed Ark Shards.";

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Creates a new ark server shard.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new ShardGeneratorService();
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->help_text = SELF::HELP_TEXT;
        $console_controller->options_list = ['?' => ['Yes', 'No']];
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();
        if ($answer['?'] == 'Yes') {
            $service->build();
            $service->configureBuild($console_controller);
        }
        $service->finalizeBuild();

        if ($service->port_range != '') {
            //check to see if we have the port range configuration set and then ask if the user wants ports auto allocated.
            $port_service = new PortService();
            $port_service->console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
            $reallocate_ports = $port_service->performUserCheck();
            $console_controller->drawCliHeader();
            if ($reallocate_ports === TRUE && $port_service->portAllocation() === TRUE) {
                $port_service->writeNewPorts(FALSE, $service->generated_shard_name);
            } else {
                $output->writeln($port_service::ALLOCATION_FAILURE);
            }
        }
        
        return 0;
    }
}