<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\DeleteService;
use App\Service\HelperService;
use App\Service\StopService;
use App\Controller\UserConsoleController;

class DeleteCommand extends Command
{
    protected static $defaultName = 'delete';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = 'Which shard do you want to delete?';
    CONST COMPLETE = 'Selected shards have been removed.';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Deletes the server shard of your choice.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new DeleteService();
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->help_text = "Note that any saved backups will not be deleted and must be removed manually. The backups are contained here: " . $service->backup_path;
        $console_controller->options_list = ['Shard' => array_merge(['All'], HelperService::extractShardNames($service->shards['installed']))];
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();

        $stop_service = new StopService();
        if (array_key_exists($answer['Shard'], $stop_service->running_shards)) {
            $stop_service->stopSelectedServer($answer['Shard']);
        }

        $choice = HelperService::translateAnswer($answer['Shard'], $service->shards['installed']);
        $service->processDeletion($choice);
        $output->writeln(SELF::COMPLETE);
        
        return 0;
    }
}