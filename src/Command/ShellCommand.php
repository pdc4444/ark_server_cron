<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShellService;
use App\Controller\UserConsoleController;

class ShellCommand extends Command
{
    protected static $defaultName = 'shell';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = 'What would you like to do?';
    CONST OPTIONS_LIST = ['?' => [
        'Install Root Server Files',
        'Start a Shard',
        'Stop a Shard',
        'Create a new Shard',
        'Restart a Shard',
        'Print Shard Status',
        'Perform Manual Backup',
        'Perform Manual Update',
        'Restore a Backup',
        'Manual Mod Only Update'
        ]];
    CONST ANSWER_KEY = [
        'Install Root Server Files' => 'InstallCommand',
        'Start a Shard' => 'StartCommand',
        'Stop a Shard' => 'StopCommand',
        'Create a new Shard' => 'ShardGeneratorCommand',
        'Restart a Shard' => 'RestartCommand',
        'Print Shard Status' => 'StatusCommand',
        'Perform Manual Backup' => 'BackupCommand',
        'Perform Manual Update' => 'UpdateCommand',
        'Restore a Backup' => 'RestoreCommand',
        'Manual Mod Only Update' => 'ModUpdateCommand'
    ];

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Starts the Ark Server Cron shell. Mostly for use within Docker.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new ShellService();
        
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->options_list = SELF::OPTIONS_LIST;
        $answer = $console_controller->askQuestion()['?'];
        $console_controller->drawCliHeader();
        $action = "App\Command\\" . SELF::ANSWER_KEY[$answer];
        $action = new $action();
        
        $action->execute($input, $output);
        
        return 0;
    }
}