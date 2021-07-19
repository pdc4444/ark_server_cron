<?php
// src/Service/PortService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;
ErrorHandler::register();

class PortService extends ShardService
{
    CONST PORT_QUERY = "Would you like Ark Server Cron to automatically define the ports?";
    CONST PORT_INFO = "Since a port range was defined during initial install we can use this to automatically dole out ports.";
    CONST ALLOCATION_FAILURE = 'We were unable to allocate ports automatically. You will need to manually set your port in $server_shard_directory/shard_#/ShooterGame/Saved/Config/LinuxServer/shard_config.ini';
    public $console_controller;
    private $used_ports = [];
    private $allocated_ports = [
        'QueryPort' => FALSE,
        'GamePort'  => FALSE,
        'UdpPort'   => FALSE,
        'RCONPort'  => FALSE
    ];

    public function __construct()
    {
        parent::__construct();
        $this->compileUsedPorts();
    }

    public function performUserCheck()
    {
        $this->console_controller->reset();
        $this->console_controller->question = SELF::PORT_QUERY;
        $this->console_controller->help_text = SELF::PORT_INFO;
        $this->console_controller->options_list = ['?' => ['Yes', 'No']];
        if ($this->console_controller->askQuestion()['?'] == 'Yes') {
            return TRUE;
        }
        return FALSE;
    }

    public function portAllocation()
    {
        $port_range_array = explode('-', $this->port_range);
        $beginning_port = intval($port_range_array[0]);
        $ending_port = intval($port_range_array[1]);
        for ($current_port = $beginning_port; $current_port < $ending_port; $current_port++) {
            if ($current_port > 65535) {
                break;
            }
            //QueryPort & RCONPort
            if (!in_array($current_port, $this->used_ports)) {
                if ($this->allocated_ports['QueryPort'] === FALSE) {
                    $this->allocated_ports['QueryPort'] = $current_port;
                    continue;
                }
                if ($this->allocated_ports['RCONPort'] === FALSE) {
                    $this->allocated_ports['RCONPort'] = $current_port;
                    continue;
                }
            }
            //UdpPort is always GamePort + 1
            if (!in_array($current_port, $this->used_ports) && !in_array($current_port + 1, $this->used_ports)) {
                $this->allocated_ports['GamePort'] = $current_port;
                $this->allocated_ports['UdpPort'] = $current_port + 1;
                break;
            }
        }

        //If we weren't able to set any ports then return FALSE and inform the user they will need to define them all.
        foreach ($this->allocated_ports as $port) {
            if ($port === FALSE) {
                return FALSE;
            }
        }
        return TRUE;
    }

    public function writeNewPorts($shard_to_modify)
    {
        // Determine the shard number
        foreach ($this->shards['installed'] as $shard_number => $shard_name) {
            ($shard_name == $shard_to_modify) ? $shard_key = $shard_number : FALSE;
        }

        $shard_cfg = $this->shards[$shard_key]['cfg_file_path'][HelperService::SHARD_CONFIG];

        //Now that we have the path we can modify shard_config to write the new ports
        $shard_cfg_data = file_get_contents($shard_cfg);
        $shard_cfg_array = explode("\n", $shard_cfg_data);
        $port_types = array_keys($this->allocated_ports);
        foreach ($shard_cfg_array as $key => $line) {
            $line_parts = explode("=", $line);
            $setting = $line_parts[0];
            if (!in_array($setting, $port_types)) {
                continue;
            }
            $value = $this->allocated_ports[$setting];
            $shard_cfg_array[$key] = $setting . '=' . $value;
        }
        $new_cfg = implode("\n", $shard_cfg_array);
        file_put_contents($shard_cfg, $new_cfg);
    }

    private function compileUsedPorts()
    {
        foreach ($this->shards as $key => $array) {
            if ($key == 'installed') {
                continue;
            }
            $this->used_ports[] = intval($array['shard_config.ini']['ShardSettings']['QueryPort']);
            $this->used_ports[] = intval($array['shard_config.ini']['ShardSettings']['GamePort']);
            $this->used_ports[] = intval($array['shard_config.ini']['ShardSettings']['UdpPort']);
            $this->used_ports[] = intval($array['shard_config.ini']['ShardSettings']['RCONPort']);
        }
        $this->used_ports = array_unique($this->used_ports);
    }
}