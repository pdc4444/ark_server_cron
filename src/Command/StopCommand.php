<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use App\Service\StopService;
use App\Controller\UserConsoleController;

class StopCommand extends Command
{
    protected static $defaultName = 'stop';
    CONST SERVICE_TITLE = "Ark Server Cron";
    CONST USER_QUESTION = 'Which shard would you like to stop?';
    CONST ATTEMPT = "Trying to stop (this/these) server shard(s): %T\n(This can take up to 90 seconds)";
    CONST NONE_RUNNING = 'No shards are currently running!';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Stops the ark server of your choice.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new StopService();
        if (empty($service->running_shards)) {
            $output->writeln(SELF::NONE_RUNNING);
        } else {
            $console_controller->question = SELF::USER_QUESTION;
            $console_controller->options_list = ['Shard' => array_merge(['All'], array_keys($service->running_shards))];
            $answer = $console_controller->askQuestion();
            $console_controller->drawCliHeader();
            $output->writeln(str_replace('%T', $answer['Shard'], SELF::ATTEMPT));
            $service->stopSelectedServer($answer['Shard']);
        }
        
        return 0;
    }
}