<?php
// src/Service/AutoService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\StopService;
use App\Service\UpdateService;
use App\Service\ModService;
use App\Service\StartService;
use Symfony\Component\ErrorHandler\Errorhandler;
ErrorHandler::register();

class AutoService extends ShardService
{
    CONST PROCESS_LIMIT = 10;

    public function __construct()
    {
        parent::__construct();
        $this->stop();
        $this->backup();
        $this->performUpdate();
        $start_service = new StartService();
        $start_service->startServers('All');
    }

    private function performUpdate()
    {
        $updater_service = new UpdateService();
        $updater_service->run();

        $mod_service = new ModService();
        $mod_service->run();

        $updater_service->updateEachShard();
    }

    private function stop()
    {
        $service = new StopService();
        $service->stopSelectedServer('All');
    }

    private function backup()
    {
        $total_shards = count($this->shards['installed']);
        
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $process_count = 0;
        $status = [];
        $progress = 1;
        $binary = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';
        
        foreach ($this->shards['installed'] as $shard) {
            if ($process_count <= SELF::PROCESS_LIMIT) {
                $command = $binary . ' backup ' . $shard . ' auto_service';
                $proc = proc_open($command, $descriptors, $pipes, NULL);
                $status[] = $proc;
                $process_count++;
            }
            if ($process_count >= SELF::PROCESS_LIMIT || $progress == $total_shards) {
                $complete_processes = $this->process_limiter($status);
                $process_count = $process_count - $complete_processes;
            }
            $progress++;
        }
    }

    private function process_limiter($process_statuses)
    {
        $complete_processes = 0;
        foreach ($process_statuses as $current_process) {
            while (TRUE) {
                $current_status = proc_get_status($current_process);
                if ($current_status['running'] != 1 || is_null($current_status['running'])) {
                    $complete_processes++;
                    continue 2;
                }
                sleep(5);
            }
        }
        return $complete_processes;
    }
}