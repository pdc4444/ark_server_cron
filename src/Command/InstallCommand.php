<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Service\InstallService;
use App\Service\HelperService;
use App\Controller\UserConsoleController;

class InstallCommand extends Command
{
    protected static $defaultName = 'install';
    protected $console_controller;
    CONST SERVICE_TITLE = "Ark Server Cron Installer";
    CONST GENERAL_CONFIG = [
        'A' => 'ark_server_files',
        'B' => 'ark_server_shards',
        'C' => 'ark_server_backups',
        'D' => 'ark_server_cluster',
        'F' => 'port_range'
    ];
    CONST USER_QUESTION_A = "Where would you like to install the ark server files?";
    CONST HELP_TEXT_A = "The ark server files are the meat of the install and will be referenced by all server shards.";
    CONST USER_QUESTION_B = "Where would you like to install the ark server shards?";
    CONST HELP_TEXT_B = "The ark server shards are the individual servers that are hosted. One hosted map is one shard.";
    CONST USER_QUESTION_C = "Where would you like to store your backups?";
    CONST HELP_TEXT_C = "Ark Server Cron has functionality to compress and backup the saved files of every server shard.";
    CONST USER_QUESTION_D = "Where would you like to store your shard cluster information?";
    CONST HELP_TEXT_D = "If you plan on hosting multiple maps, you can enable server clustering to allow player uploads / downloads only within your cluster.";
    CONST USER_QUESTION_F = "You can define a range of ports to have Ark Server Cron automatically define ports for new shards. You will of course have to make sure your router has these ports forwarded for you.";
    CONST HELP_TEXT_F = "Example port range: '60000 - 60125'";
    CONST CUSTOM_STRING = 'Please define your custom path. Expected example: /path/to/directory';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
        ->setDescription('Installs the ark server cron for the first time.')
        ->setHelp('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->console_controller = new UserConsoleController(SELF::SERVICE_TITLE, $output);
        $service = new InstallService();

        foreach (SELF::GENERAL_CONFIG as $key => $item) {
            $question = constant("SELF::USER_QUESTION_" . $key);
            $help = constant("SELF::HELP_TEXT_" . $key);
            $options = ['?' => ["~/" . $item . ' (Default)', 'Custom']];
            $answer = $this->cycleThroughQuestions($question, $help, $options)['?'];
            if ($answer !== 'Custom') {
                $answer = $_SERVER['HOME'] . DIRECTORY_SEPARATOR . $item;
            } else {
                $answer = $this->cycleThroughQuestions(SELF::CUSTOM_STRING);
            }
            $service->general_config[$key] = $answer;
        }
        $service->beginInstallation();
        return 0;
    }

    protected function cycleThroughQuestions($question, $help_text = "", $options = FALSE)
    {
        $this->console_controller->reset();
        $this->console_controller->question = $question;
        $this->console_controller->help_text = $help_text;
        $options ? $this->console_controller->options_list = $options : FALSE;
        $answer = $this->console_controller->askQuestion();
        $this->console_controller->drawCliHeader();
        return $answer;
    }
}