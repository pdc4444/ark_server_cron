<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\ShardService;

class StatusCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'status';

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
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln([
            'Ark Status',
            '============',
            '',
        ]);
        // $section1->writeln("Here is text is the first section");

        // the value returned by someMethod() can be an iterator (https://secure.php.net/iterator)
        // that generates and returns the messages with the 'yield' PHP keyword
        
        // $section2->writeln("Here is text in the second section");
        // $section3->writeln("Enter your name: " . $input->getArgument('name'));?
        $service = new ShardService();
        $raw_shard_data = $service->shards;
        $shard_names = [];
        $important_shard_data = [];
        foreach ($raw_shard_data as $shard_name => $shard_data) {
            $shard_names[] = $shard_name;
            $important_shard_data[$shard_name]['Query Port'] = $shard_data['shard_config.ini']['QueryPort'];
            $important_shard_data[$shard_name]['Game Port'] = $shard_data['shard_config.ini']['GamePort'];
            $important_shard_data[$shard_name]['RCON Port'] = $shard_data['shard_config.ini']['RCONPort'];
            $import_shard_data[$shard_name]['Map'] = $shard_data['shard_config.ini']['Server_Map'];
            $import_shard_data[$shard_name]['Session Name'] = $shard_data['GameUserSettings.ini']['SessionName'];
            $import_shard_data[$shard_name]['Session Password'] = $shard_data['GameUserSettings.ini']['ServerPassword'];
            $import_shard_data[$shard_name]['Max Players'] = $shard_data['shard_config.ini']['MaxPlayers'];
            $import_shard_data[$shard_name]['Running'] = $shard_data['Status']['Running'];
            $import_shard_data[$shard_name]['Process Id'] = $shard_data['Status']['Process Id'];
        }
        $output->writeln('The following shard(s) are installed. Which one would you like to view the status of?');
        foreach ($shard_names as $shard_name) {
            $output->writeln($shard_name);
        }

        $user_input = readLine('?: ');
        foreach ($shard_names as $shard_name) {
            if (strtolower($user_input) === $shard_name) {
                foreach ($import_shard_data[$shard_name] as $item => $value) {
                    $output->writeln($item . ': ' . $value);
                }
            }
        }
        // $result = $this->container->get(TestService::firstService());
        // print_r($service);
        // $output->writeln($service);

        // $section2->overwrite('Hello ' . $name);
        
        return 0;
    }
}