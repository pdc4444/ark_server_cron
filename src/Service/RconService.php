<?php
// src/Service/RconService.php
namespace App\Service;
use App\Service\ShardService;

class RconService extends ShardService
{
    public $ip;
    public $port;
    public $password;

	public function __construct()
	{
        // echo "We have initalized RconService\n";
        parent::__construct();
    }
    
    public function setServer($ip, $port, $password)
    {
        
    }
    
}