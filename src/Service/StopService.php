<?php
// src/Service/StopService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;

class StopService extends ShardService
{
    CONST POSIX_ERROR = "Unable to stop the server. Try running this command with sudo!\nError Message: ";

    public $running_shards;
    private $shard_choice;

	public function __construct()
	{   
        $this->refreshRunningShards();
    }

    public function stopSelectedServer($shard_choice = 'All')
    {
        $this->shard_choice = $shard_choice;
        if ($this->shard_choice == 'All') {
            foreach ($this->running_shards as $name => $pid) {
                $this->killRunningProcess($pid, SIGINT);
            }
        } else {
            $this->killRunningProcess($this->running_shards[$this->shard_choice], SIGINT);
        }
        $this->verifyServerIsStopped();
    }

    private function verifyServerIsStopped()
    {
        $sleep_counter = 0;
        while (TRUE) {
            $this->refreshRunningShards();
            if (empty($this->running_shards)) {
                return;
            } else if (!isset($this->running_shards[$this->shard_choice]) && $this->shard_choice !== 'All') {
                return;
            }
            if ($sleep_counter >= 30) {
                foreach ($this->running_shards as $name => $pid) {
                    if ($this->shard_choice == 'All' || $name == $this->shard_choice) {
                        $this->killRunningProcess($pid, SIGKILL);
                    }
                }
            }
            sleep(3);
            $sleep_counter++;
        }
    }

    private function refreshRunningShards()
    {
        parent::__construct();
        $this->running_shards = $this->extractRunningShards();
    }

    private function killRunningProcess($pid, $sig)
    {   
        posix_kill($pid, $sig);
        $outcome = posix_get_last_error();
        if ($outcome !== 0) {
            throw new \RuntimeException(SELF::POSIX_ERROR . posix_strerror($outcome));
        }
    }
    
    private function extractRunningShards()
    {
        $running_shards = [];
        foreach ($this->shards as $shard_name => $shard_data) {
            if ($shard_name != 'installed' && $shard_data['Status']['Running'] == 'Yes') {
                $shard_name = $shard_data[HelperService::GAME_CONFIG]['SessionSettings']['SessionName'];
                $process_id = $shard_data['Status']['Process Id'];
                $running_shards[$shard_name] = $process_id;
            }
        }
        return $running_shards;
    }

}