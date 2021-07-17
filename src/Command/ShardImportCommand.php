<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShardImportService;
use App\Service\HelperService;
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
            $output->writeln(SELF::SUCCESS);
        } else {
            $output->writeln(SELF::FAILURE);
        }
        
        return 0;
    }
}