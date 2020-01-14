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

        $data = $this->compileImportantInfo($raw_shard_data);
        $shard_names = $data['shards'];
        $important_shard_data = $data['data'];

        $console_controller->question = SELF::USER_QUESTION;
        $options = [
            'Shards' => $shard_names,
        ];
        $console_controller->options_list = $options;
        $answer = $console_controller->askQuestion($output);

        $detail_table = new Table($output);

        if (isset($answer['Shards'])) {
            $console_controller->drawCliHeader();
            $rows = [];
            foreach ($important_shard_data[$answer['Shards']] as $row_header => $data) {
                $rows[] = [$row_header, $data];
            }
            $detail_table
                ->setHeaders(['Setting Name', 'Value'])
                ->setRows($rows)
            ;
            $detail_table->setStyle('borderless');
            $detail_table->render();
        }
        
        return 0;
    }

    private function compileImportantInfo($raw_shard_data)
    {
        $shard_names = [];
        $important_shard_data = [];
        foreach ($raw_shard_data as $shard_name => $shard_data) {
            if($shard_name != 'installed'){
                $shard_names[] = $shard_name;
                $important_shard_data[$shard_name]['Shard Number'] = $shard_name;
                $important_shard_data[$shard_name]['Query Port'] = $shard_data['shard_config.ini']['QueryPort'];
                $important_shard_data[$shard_name]['Game Port'] = $shard_data['shard_config.ini']['GamePort'];
                $important_shard_data[$shard_name]['RCON Port'] = $shard_data['shard_config.ini']['RCONPort'];
                $important_shard_data[$shard_name]['Map'] = $shard_data['shard_config.ini']['Server_Map'];
                $important_shard_data[$shard_name]['Session Name'] = $shard_data['GameUserSettings.ini']['SessionName'];
                $important_shard_data[$shard_name]['Session Password'] = $shard_data['GameUserSettings.ini']['ServerPassword'];
                $important_shard_data[$shard_name]['Max Players'] = $shard_data['shard_config.ini']['MaxPlayers'];
                $important_shard_data[$shard_name]['Running'] = $shard_data['Status']['Running'];
                $important_shard_data[$shard_name]['Process Id'] = $shard_data['Status']['Process Id'];
            }
        }
        return ['shards' => $shard_names, 'data' => $important_shard_data];
    }
}