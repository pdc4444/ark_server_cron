<?php
// src/Service/RconChatService.php
namespace App\Service;
use App\Service\RconService;
use Symfony\Component\ErrorHandler\Errorhandler; //Not sure if I need this right now

class RconChatService extends RconService
{
	public function __construct()
	{
        parent::__construct();
    }

    public function test()
    {
        echo "RconChatService\n\n\n\n\n";
        $cmd = 'listPlayers';
        $result = $this->runCommand($cmd);
        print_r($result);
    }
    
}