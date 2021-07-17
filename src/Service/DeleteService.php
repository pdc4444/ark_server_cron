<?php
// src/Service/DeleteService.php
namespace App\Service;
use Symfony\Component\ErrorHandler\Errorhandler;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\ShardService;
// use App\Controller\UserConsoleController;
use App\Service\HelperService;
ErrorHandler::register();

class DeleteService extends ShardService
{
	public function __construct()
	{
        parent::__construct();
    }
    
    public function processDeletion($choice)
    {
        $locations = [];
        if ($choice !== 'All') {
            $locations[] = $this->shards[$choice]['Path'];
        } else {
            $locations = array_column($this->shards, 'Path');
        }
        
        foreach ($locations as $shard) {
            HelperService::delTree($shard);
        }
    }
}