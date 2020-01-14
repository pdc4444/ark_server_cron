<?php
// src/Command/RconCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\RconService;
use App\Controller\UserConsoleController;

class RconCommand extends Command
{
    CONST SERVICE_TITLE = 'Ark Rcon Connector';
    CONST USER_QUESTION = 'Which shard would you like to connect to?';
    CONST HELP_TEXT = 'RCON is a service that lets you connect to a server shard via the console to issue admin commands or chat with connected users.';

    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'rcon';

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
        $console_controller->total_questions = 3;
        $options = [
            'Shard' => $rcon_service->shards['installed'],
        ];
        $console_controller->options_list = $options;
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();
        $output->writeln("\nhere is your choice\n");
        print_r($answer);

        $console_controller->reset();
        $console_controller->question = 'Please decided between true or false';
        $console_controller->options_list = [
            '?' => ['True', 'False'],
        ];
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();
        $output->writeln("\nhere is your choice\n");
        print_r($answer);

        //Single question testing
        $console_controller->reset();
        $console_controller->question = 'Type something';
        $answer = $console_controller->askQuestion();
        $console_controller->drawCliHeader();
        $output->writeln("\nhere is your choice\n");
        print_r($answer);


        return 0;
    }
}