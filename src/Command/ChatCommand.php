<?php
// src/Command/ChatCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\RconChatService;
use App\Controller\UserConsoleController;

class ChatCommand extends Command
{
    CONST SERVICE_TITLE = 'ARK Rcon Chat';
    CONST USER_QUESTION = 'Which shard would you like to connect to?';
    CONST HELP_TEXT = 'This service allows you to talk to connected users in real time.';

    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'chat';
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
        $rcon_chat_service = new RconChatService();
        $console_controller->question = SELF::USER_QUESTION;
        $console_controller->help_text = SELF::HELP_TEXT;
        $console_controller->options_list = ['Shard' => $rcon_chat_service->shards['installed']];
        $answer = $console_controller->askQuestion();
        $choice = $rcon_chat_service->shards[$answer['Shard']];
        $console_controller->drawCliHeader();
        $rcon_chat_service->initiateConnection($choice);
        $rcon_chat_service->test();

        // $choice = $rcon_service->shards[$answer['Shard']];
        // $rcon_service->initiateConnection($choice);
        // $console_controller->drawCliHeader();
        // while (true) {
        //     $output->writeln($console_controller::LINE_BREAK . SELF::RCON_PROMPT);
        //     $answer = readline($console_controller::READLINE_PROMPT);
        //     $this->response = $rcon_service->runCommand($answer);
        //     if ($this->response !== FALSE) {
        //         $this->response = str_replace($console_controller::LINE_BREAK, '', $this->response);
        //         // $output->writeLn($this->response);
        //         print_r($this->response);
        //     }
        // }

        return 0;
    }
}