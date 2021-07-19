<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
// use App\Service\TestService;

class CreateUserCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'test';

    public function __construct(bool $requirePassword = false)
    {
        // best practices recommend to call the parent constructor first and
        // then set your own properties. That wouldn't work in this case
        // because configure() needs the properties set in this constructor
        $this->requirePassword = $requirePassword;

        parent::__construct();
    }

    protected function configure()
    {
        $this
        // the short description shown while running "php bin/console list"
        ->setDescription('Creates a new user.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('This command allows you to create a user...');

        $this
        // ...
        ->addArgument('name', InputArgument::REQUIRED, 'Your name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $section1 = $output->section();
        $section2 = $output->section();
        $section3 = $output->section();
        $output->writeln([
            'Test Header!',
            '============',
            '',
        ]);
        $section1->writeln("Here is text is the first section");

        // the value returned by someMethod() can be an iterator (https://secure.php.net/iterator)
        // that generates and returns the messages with the 'yield' PHP keyword
        
        $section2->writeln("Here is text in the second section");
        $section3->writeln("Enter your name: " . $input->getArgument('name'));
        $result = TestService::firstService();
        // $result = $this->container->get(TestService::firstService());
        $output->writeln($result);

        // $section2->overwrite('Hello ' . $name);
        
        return 0;
    }
}