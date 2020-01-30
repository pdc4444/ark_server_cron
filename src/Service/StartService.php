<?php
// src/Service/StartService.php
namespace App\Service;
use App\Service\ShardService;
use App\Service\HelperService;
use Symfony\Component\ErrorHandler\Errorhandler;

/**
 * This service is responsible for compiling the data necessary to start the associated Ark Server Shards.
 */
class StartService extends ShardService
{

    CONST BINARY_PATH = 'ShooterGame' . DIRECTORY_SEPARATOR . 'Binaries' . DIRECTORY_SEPARATOR . 'Linux' . DIRECTORY_SEPARATOR . 'ShooterGameServer ';
    CONST SHARD_CONFIG = 'shard_config.ini';
    CONST GAME_CONFIG = 'GameUserSettings.ini';
    CONST LISTEN = '?listen';
    CONST QUERY = '?QueryPort=';
    CONST PORT = '?Port=';
    CONST RCON = '?RCONPort=';
    CONST PLAYERS = '?MaxPlayers=';
    CONST RAW_SOCKETS = '?bRawSockets';
    CONST BATTLE_EYE = ' -NoBattlEye';
    CONST COMMAND_TAIL = ' > /dev/null 2>&1 & ';
    CONST NOT_SET = ' is not set! Check your shard configuration files for errors!';
    CONST BINARY_MISSING = 'ShooterGameServer binary is missing or corrupted for ';
    
    private $start_commands = [];

    /**
     * Initalize the Parent service (ShardService) and proceed with building the Ark server shard start commands.
     */
	public function __construct()
	{
        parent::__construct();
        $this->buildStartCommands();
    }

    /**
     * References the users choice and attempts to start the selected shard
     * 
     * @param string $user_choice - The shard name such as shard_1 or All
     * @return array $started_servers - An array of shards organized by the session name defined in the
     * Shards GameUserSettings.ini. The value of the session name will either be true (started) or false (not started)
     */
    public function startServers($user_choice)
    {
        $started_servers = [];
        if ($user_choice == 'All') {
            foreach ($this->start_commands as $shard_name => $cmd) {
                $result = $this->executeStartCommand($shard_name, $cmd);
                $session_name = $this->shards[$shard_name][HelperService::GAME_CONFIG]['SessionSettings']['SessionName'];
                ($result) ? $started_servers[$session_name] = TRUE : $started_servers[$session_name] = FALSE;
            }
        } else {
            $result = $this->executeStartCommand($user_choice, $this->start_commands[$user_choice]);
            $session_name = $this->shards[$user_choice][HelperService::GAME_CONFIG]['SessionSettings']['SessionName'];
            ($result) ? $started_servers[$session_name] = TRUE : $started_servers[$session_name] = FALSE;
        }
        return $started_servers;
    }

    /**
     * Executes the start command for the ark shard via exec().
     * 
     * @param string $shard_name - The shard name such as shard_1
     * @param string $cmd - The compiled start command from $this->buildStartCommands()
     * @return boolean - If we do execute the command, we return TRUE, else return FALSE
     */
    private function executeStartCommand($shard_name, $cmd)
    {
        if ($this->shards[$shard_name]['Status']['Running'] == 'No') {
            ErrorHandler::call('exec', $cmd);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Takes the compiled data from ShardService aka $this->shards and builds the commands required to start each Ark shard.
     * Validation is done via $this->errorCheck to ensure that data used is expected. Additionally, we make a call to
     * $this->determineActiveEvent() and append whatever the current seasonal event is.
     * 
     * Built start commands are saved to $this->start_commands
     */
    private function buildStartCommands()
    {
        foreach ($this->shards as $key_name => $data) {
            if (strpos($key_name, 'shard_') !== FALSE && $this->errorCheck($this->shards[$key_name], $key_name) === FALSE) {
                $string = $this->shards[$key_name]['Path'] . SELF::BINARY_PATH;
                $string = $string . $this->shards[$key_name][HelperService::SHARD_CONFIG]['Server_Map'] . SELF::LISTEN;
                $string = $string . SELF::QUERY . $this->shards[$key_name][HelperService::SHARD_CONFIG]['QueryPort'];
                $string = $string . SELF::PORT . $this->shards[$key_name][HelperService::SHARD_CONFIG]['GamePort'];
                $string = $string . SELF::RCON . $this->shards[$key_name][HelperService::SHARD_CONFIG]['RCONPort'];
                $string = $string . SELF::PLAYERS . $this->shards[$key_name][HelperService::SHARD_CONFIG]['MaxPlayers'];
                $string = $string . SELF::RAW_SOCKETS;
                if ($this->shards[$key_name][HelperService::SHARD_CONFIG]['battle_eye'] == 'false') {
                    $string = $string  . SELF::BATTLE_EYE;
                }
                $event = $this->determineActiveEvent();
                if ($event) {
                    $string = $string . $event;
                }
                $string = $string . SELF::COMMAND_TAIL;
                $this->start_commands[$key_name] = $string;
            }
        }
    }

    /**
     * Checks the shard data and ensures that required configuration is found.
     * 
     * @param array $shard_data - The array of shard_data passed for validation
     * @param string $shard_name - The name of the shard we are currently validating such as shard_1
     * @return boolean - If no errors are found we return FALSE, else an exception is thrown.
     */
    private function errorCheck($shard_data, $shard_name)
    {
        if (!isset($shard_data[HelperService::SHARD_CONFIG])) {
            throw new \RuntimeException(HelperService::SHARD_CONFIG . SELF::NOT_SET);
        }
        $required_numeric_keys = ['QueryPort', 'GamePort', 'RCONPort', 'MaxPlayers'];
        foreach ($required_numeric_keys as $key) {
            if (!isset($shard_data[HelperService::SHARD_CONFIG][$key])) {
                throw new \RuntimeException($key . SELF::NOT_SET);
            }
            if (!is_numeric($shard_data[HelperService::SHARD_CONFIG][$key])) {
                throw new \RuntimeException($key . SELF::NOT_SET);
            }
        }
        if (!isset($shard_data[HelperService::SHARD_CONFIG]['Server_Map']) || empty($shard_data[HelperService::SHARD_CONFIG]['Server_Map'])) {
            throw new \RuntimeException('Server_Map' . SELF::NOT_SET);
        }
        $binary = trim($shard_data['Path'] . SELF::BINARY_PATH);
        if (file_exists($binary) !== TRUE) {
            throw new \RuntimeException(SELF::BINARY_MISSING . $shard_name);
        }
        return FALSE;
    }

    /**
     * Looks at the current date and determines if a seasonal event should be active
     * 
     * Events
	 * -ActiveEvent=<eventname> (command line flag for the start command)
     * eventname            Description                                            Dates Active
     * Easter               Allows for the Easter Event to be activated            March 29th - April 10th
     * Arkaeology           Allows for the Arkaeology Event to be activated.       June 15th - July 17th
     * WinterWonderland     Allows for Winter Wonderland Event to be activated.    December 18th - January 7th
     * vday                 Allows for Valentine's Day Event to be activated.      February 12th - February 18th
     * Summer               Allows for Summer Bash Event to be activated.          July 2nd - July 19th
     * FearEvolved          Allows for ARK: Fear Evolved 3 to be activated.        October 22nd - November 5th
     * 
     * @return mixed - Either false, or the string for the currently active event.
     */
    private function determineActiveEvent()
	{
		$events = [
			['name' => 'Easter', 'start' => 'March 29', 'end' => 'April 10'],
			['name' => 'Arkaeology', 'start' => 'June 15', 'end' => 'July 1'],
			['name' => 'WinterWonderland', 'start' => 'December 18', 'end' => 'January 7'],
			['name' => 'vday', 'start' => 'February 12', 'end' => 'February 18'],
			['name' => 'Summer', 'start' => 'July 2', 'end' => 'July 19'],
			['name' => 'FearEvolved', 'start' => 'October 22', 'end' => 'November 5']
		];

		$now = new \DateTime();
		
		foreach($events as $event){
			$name = $event['name'];
			$start = new \DateTime(date('Y-m-d', strtotime($event['start'])));
			$end = new \DateTime(date('Y-m-d', strtotime($event['end'])));
			if ($now >= $start && $now <= $end) {
				return ' -ActiveEvent=' . $name;
			}
		}
		return false;
	}
}