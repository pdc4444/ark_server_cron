<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShardGeneratorService;
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
        $raw_shard_data = $service->shards;
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
        
        return 0;
    }
}