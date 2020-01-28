<?php
// src/Service/RconService.php
namespace App\Service;
use App\Service\ShardService;
use Symfony\Component\ErrorHandler\Errorhandler; //Not sure if I need this right now

class RconService extends ShardService
{
    CONST SET_SERVER_SETTING_ERROR = "RCONEnabled is set to false or not found in GameUserSettings.ini! Please add 'RCONEnabled=true' to GameUserSettings.ini under the [ServerSettings] header and restart your server shard!";
    CONST SET_SERVER_PORT_ERROR = 'RCONPort not found! Please double check the configuration in shard_config.ini. Please add "RCONPort=YOURPORT" to shard_config.ini and restart your server shard!';
    CONST SET_SERVER_PASSWORD_ERROR = 'The admin password is not set! Please make sure ServerAdminPassword is properly set in GameUserSettings.ini! Please add "ServerAdminPassword=YOURPASSWORD" under the [ServerSettings] header and restart your shard!';
    CONST SET_SERVER_STATUS_ERROR = 'Your server shard is not currently running. Please start your server shard before attempting to connect with the RCON client!';
    CONST STREAM_CONNECT_ERROR = 'Unable to connect to the RCON server. Please check firewall, port, and ip settings!';
    CONST STREAM_TIMEOUT = 3;
    CONST PACKET_ID = 5;
    CONST PACKET_TYPE = 3;


    private $ip;
    private $port;
    private $password;
    private $stream;

	public function __construct()
	{
        parent::__construct();
    }
    
    public function initiateConnection($choice)
    {
        $this->errorCheck($choice);
        $this->ip = '127.0.0.1'; //Hard coded for local since it is unexpected that we would remotely connect to RCON
        $this->port = $choice['shard_config.ini']['RCONPort'];
        $this->password = $choice['GameUserSettings.ini']['ServerSettings']['ServerAdminPassword'];
        $this->openSocket();
    }

    private function errorCheck($choice)
    {
        $game_user_settings_path = "\nFile Path = " . $choice['cfg_file_path']['GameUserSettings.ini'];
        $shard_config_path = "\nFile Path = " . $choice['cfg_file_path']['shard_config.ini'];
        if (!isset($choice['GameUserSettings.ini']) || !isset($choice['GameUserSettings.ini']['ServerSettings']['RCONEnabled']) || $choice['GameUserSettings.ini']['ServerSettings']['RCONEnabled'] !== 'true') {
            throw new \RuntimeException(SELF::SET_SERVER_SETTING_ERROR . $game_user_settings_path);
        } else if (!isset($choice['shard_config.ini']) || !isset($choice['shard_config.ini']['RCONPort']) || $choice['shard_config.ini']['RCONPort'] == '') {
            throw new \RuntimeException(SELF::SET_SERVER_PORT_ERROR . $shard_config_path);
        } else if (!isset($choice['GameUserSettings.ini']) || !isset($choice['GameUserSettings.ini']['ServerSettings']['ServerAdminPassword']) || $choice['GameUserSettings.ini']['ServerSettings']['ServerAdminPassword'] == '') {
            throw new \RuntimeException(SELF::SET_SERVER_PASSWORD_ERROR . $game_user_settings_path);
        } else if (!isset($choice['Status']) || !isset($choice['Status']['Running']) || $choice['Status']['Running'] !== 'Yes') {
            throw new \RuntimeException(SELF::SET_SERVER_STATUS_ERROR);
        }
    }

    public function runCommand($command)
    {
        $this->writePacket(SELF::PACKET_ID, SELF::PACKET_TYPE, $command);
        $response = $this->readPacket();
        // return $response['body'];
        return $response;
    }

    private function openSocket()
    {
        $this->stream = fsockopen($this->ip, $this->port, $errno, $errstr, SELF::STREAM_TIMEOUT);
        if ($this->stream === FALSE) {
            throw new \RuntimeException(SELF::STREAM_CONNECT_ERROR . "\nTried: " . $this->ip . ":" . $this->port . "\n$errstr");
        }
        stream_set_timeout($this->stream, SELF::STREAM_TIMEOUT, 0);
        $this->sendAuth();
    }

    private function sendAuth()
    {
        $this->writePacket(SELF::PACKET_ID, SELF::PACKET_TYPE, $this->password);
        $response = $this->readPacket();
        print_r($response);
        
    }

    private function writePacket($packet_id, $packet_type, $packet_body)
    {
        //Packet creation
        $packet = pack('VV', $packet_id, $packet_type);
        $packet = $packet . $packet_body . "\x00";
        $packet = $packet . "\x00";

        //Measure the packet size
        $packet_length = strlen($packet);

        //Send the packet
        $packet = pack('V', $packet_length) . $packet;
        fwrite($this->stream, $packet, strlen($packet));
    }

    private function readPacket()
    {
        //Find the size of the packet
        $raw_data = fread($this->stream, 4);
        if ($raw_data !== '') {
            $unpacked_data = unpack('V1size', $raw_data);
            $packet_size = $unpacked_data['size'];
            $actual_raw_data = fread($this->stream, $packet_size);
            $unpacked_actual_data = unpack('V1id/V1type/a*body', $actual_raw_data);
            return $unpacked_actual_data;
        } else {
            return NULL;
        }
    }
}