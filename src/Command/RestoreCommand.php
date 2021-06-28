<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StopService;
use App\Service\RestoreService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class RestoreCommand extends Command
{
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST FEEDBACK = "Restoring selected backup!";
    CONST QUERY = "Which shard would you like to restore?";
    CONST QUERY_2 = "Please select a back you'd like to restore.";
    // CONST COMMENT_QUERY = "Please enter a comment for this backup:";
    CONST CONTEXT = "Note: The shard will be need to be stopped for a backup restoration.";
    // CONST SUCCESS = "Ark Server backup complete!";
    // CONST FAILURE = "Ark Server could not be backed up!";
    CONST STOP_SERVICE_CHOICE = 'All';
    CONST DESCRIPTION = 'Restore the ark server of your choice.';

    protected static $defaultName = 'restore';

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
        $service = new RestoreService();
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $console_controller->question = SELF::QUERY;
        $console_controller->options_list = ['Shard' => HelperService::extractShardNames($service->shards['installed'])];
        $console_controller->help_text = SELF::CONTEXT;
        $chosen_shard = $console_controller->askQuestion()['Shard'];

        $console_controller->drawCliHeader();
        $service->shard_name = $chosen_shard;
        $service->compileBackupList();
        $service->tidyBackupsForQuery();
        
        $console_controller->reset();
        $console_controller->question = SELF::QUERY_2;
        $console_controller->options_list = ['Filename' => array_column($service->tidied_backups, 'file') , 'Date & Time' => array_column($service->tidied_backups, 'time'), 'Comment' => array_column($service->tidied_backups, 'comment')];
        $chosen_backup = $console_controller->askQuestion()['Filename'];

        $stop_service = new StopService();
        if (!empty($stop_service->running_shards)) {
            //Stop The Server
            $stop_service->stopSelectedServer($chosen_shard['Shard']);
        }

        $console_controller->drawCliHeader();
        $output->writeln(SELF::FEEDBACK . $console_controller::LINE_BREAK);
        $service->run($chosen_backup);
        // $output->writeln(SELF::FEEDBACK . $console_controller::LINE_BREAK);
        // $service->run($chosen_shard['Shard'], $comment, $output);
        
        return 0;
    }
}