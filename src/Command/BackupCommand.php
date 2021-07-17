<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\StopService;
use App\Service\BackupService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class BackupCommand extends Command
{
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST FEEDBACK = "Beginning backup!";
    CONST QUERY = "Which shard would you like to backup?";
    CONST COMMENT_QUERY = "Please enter a comment for this backup:";
    CONST CONTEXT = "Note: The shard will be stopped before the backup process begins!";
    CONST STOP_SERVICE_CHOICE = 'All';
    CONST DESCRIPTION = 'Backup the ark server of your choice.';

    protected static $defaultName = 'backup';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription(SELF::DESCRIPTION)
        ->setHelp('');

        $this->addArgument('cli_shard', InputArgument::OPTIONAL, 'The shard name that you want to backup.');
        $this->addArgument('cli_comment', InputArgument::OPTIONAL, 'The shard name that you want to backup.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cli_shard = $input->getArgument('cli_shard');
        $cli_comment = $input->getArgument('cli_comment');
        $service = new BackupService();
        if ($cli_shard === NULL) {
            $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
            $console_controller->question = SELF::QUERY;
            $console_controller->options_list = ['Shard' => array_merge(['All'], HelperService::extractShardNames($service->shards['installed']))];
            $console_controller->help_text = SELF::CONTEXT;
            $chosen_shard = $console_controller->askQuestion();
            $console_controller->drawCliHeader();
            $console_controller->reset();
            $console_controller->question = SELF::COMMENT_QUERY;
            $comment = $console_controller->askQuestion();
        } else {
            $chosen_shard['Shard'] = $cli_shard;
            is_null($cli_comment) ? $comment = '' : $comment = $cli_comment;
        }
    
        $stop_service = new StopService();
        if (!empty($stop_service->running_shards)) {
            $stop_service->stopSelectedServer($chosen_shard['Shard']);
        }

        if (is_null($cli_shard)) {
            $console_controller->drawCliHeader();
            $output->writeln(SELF::FEEDBACK . $console_controller::LINE_BREAK);
        }

        $service->run($chosen_shard['Shard'], $comment, $output);
        
        return 0;
    }
}