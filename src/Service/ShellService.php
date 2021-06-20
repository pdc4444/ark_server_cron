<?php
// src/Service/ShellService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
// use App\Service\ShardService;
// use App\Service\HelperService;
ErrorHandler::register();

class ShellService {
    
	public function __construct()
	{
        echo "this is ShellService \n";
    }
    
}