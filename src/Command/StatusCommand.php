<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use App\Service\ShardService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class StatusCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'status';

    CONST SERVICE_TITLE = "Ark Status";
    CONST USER_QUESTION = 'Which shard would you like to see the status of?';

    public function __construct()
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor

        parent::__construct();
    }

    protected function configure()
    {
        $this
        // the short description shown while running "php bin/console list"
        ->setDescription('Compiles and returns data about any installed Ark Server Shards.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('TBD');

        // $this
        // // ...
        // ->addArgument('name', InputArgument::REQUIRED, 'Your name')
        // ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new ShardService();
        $raw_shard_data = $service->shards;

        $important_shard_data = HelperService::summarizeShardInfo($raw_shard_data);

        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->options_list = ['Shards' => HelperService::extractShardNames($raw_shard_data['installed'])];
        $answer = $console_controller->askQuestion($output);
        $selected_shard = HelperService::translateAnswer($answer['Shards'], $raw_shard_data['installed']);

        $detail_table = new Table($output);
        $console_controller->drawCliHeader();
        $rows = [];
        foreach ($important_shard_data[$selected_shard] as $row_header => $data) {
            $rows[] = [$row_header, $data];
        }
        $detail_table
            ->setHeaders(['Setting Name', 'Value'])
            ->setRows($rows)
        ;
        $detail_table->setStyle('borderless');
        $detail_table->render();
        
        return 0;
    }
}