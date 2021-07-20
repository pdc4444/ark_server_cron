<?php
// src/Service/AutoService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\StopService;
use App\Service\UpdateService;
use App\Service\ModService;
use App\Service\StartService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
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
        $service = $this;
        $service = HelperService::enabledCheck($service);
        $total_shards = count($service->shards['installed']);

        $process_count = 0;
        $progress = 1;
        $binary = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console';
        $queued_jobs = [];
        
        foreach ($service->shards['installed'] as $shard) {
            if ($process_count <= SELF::PROCESS_LIMIT) {
                $queued_jobs[] = new Process([$binary, 'backup', $shard, 'auto_service']);
                $process_count++;
            }
            if ($process_count >= SELF::PROCESS_LIMIT || $progress == $total_shards) {
                foreach ($queued_jobs as $job) {
                    $job->start();
                }
                foreach ($queued_jobs as $job) {
                    while ($job->isRunning()) {
                        sleep(5);
                    }
                }
                $queued_jobs = [];
            }
            $progress++;
        }
    }
}