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
    // CONST SUCCESS = "Ark Server backup complete!";
    // CONST FAILURE = "Ark Server could not be backed up!";
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

        $this->addArgument('shards', InputArgument::IS_ARRAY, 'A list of servers to backup.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $selected_shards = $input->getArgument('shards');
        $service = new BackupService();
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $console_controller->question = SELF::QUERY;
        $console_controller->options_list = ['Shard' => array_merge(['All'], HelperService::extractShardNames($service->shards['installed']))];
        $console_controller->help_text = SELF::CONTEXT;
        $chosen_shard = $console_controller->askQuestion();

        $console_controller->drawCliHeader();
        $console_controller->reset();
        $console_controller->question = SELF::COMMENT_QUERY;
        $comment = $console_controller->askQuestion();

        $stop_service = new StopService();
        if (!empty($stop_service->running_shards)) {
            //Stop The Server
            $stop_service->stopSelectedServer($chosen_shard['Shard']);
        }

        $console_controller->drawCliHeader();
        $output->writeln(SELF::FEEDBACK . $console_controller::LINE_BREAK);
        $service->run($chosen_shard['Shard'], $comment, $output);
        
        return 0;
    }
}