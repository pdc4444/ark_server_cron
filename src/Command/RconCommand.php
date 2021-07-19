<?php
// src/Command/RconCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\RconService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class RconCommand extends Command
{
    CONST SERVICE_TITLE = 'Ark Rcon Connector';
    CONST USER_QUESTION = 'Which shard would you like to connect to?';
    CONST HELP_TEXT = 'RCON is a service that lets you connect to a server shard via the console to issue admin commands or chat with connected users.';
    CONST RCON_PROMPT = 'Please enter your RCON Command:';
    CONST RCON_HELP_TEXT = 'To quit, press ctrl + c';

    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'rcon';
    private $response = FALSE;

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
        ->setDescription('Allows connecting to any running Ark Servers Rcon service.')

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
        $rcon_service = new RconService();
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->help_text = SELF::HELP_TEXT;
        $console_controller->options_list = ['Shard' => HelperService::extractShardNames($rcon_service->shards['installed'])];
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();

        $choice = $rcon_service->shards[HelperService::translateAnswer($answer['Shard'], $rcon_service->shards['installed'])];
        $rcon_service->initiateConnection($choice);
        $console_controller->drawCliHeader();
        while (true) {
            $output->writeln($console_controller::LINE_BREAK . SELF::RCON_PROMPT);
            $answer = readline($console_controller::READLINE_PROMPT);
            $this->response = $rcon_service->runCommand($answer);
            if ($this->response !== FALSE) {
                $this->response = str_replace($console_controller::LINE_BREAK, '', $this->response);
                // $output->writeLn($this->response);
                print_r($this->response);
            }
        }

        return 0;
    }

    
}