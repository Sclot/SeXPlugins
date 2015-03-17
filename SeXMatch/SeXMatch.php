<?
namespace ManiaLivePlugins\SeXPlugins\SeXMatch;

use Exception;
use ManiaLib\Utils\Formatting;
use ManiaLib\Utils\Path;
use ManiaLive\Application\Application;
use ManiaLive\PluginHandler\Dependency;
use ManiaLive\Utilities\Time;
use ManiaLivePlugins\eXpansion\AdminGroups\AdminGroups;
use ManiaLivePlugins\eXpansion\AdminGroups\Permission;
use ManiaLivePlugins\eXpansion\AdminGroups\types\Boolean;
use ManiaLivePlugins\eXpansion\AdminGroups\types\Integer;
use ManiaLivePlugins\eXpansion\AdminGroups\types\Time_ms;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Controls\BannedPlayeritem;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Controls\BlacklistPlayeritem;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Controls\GuestPlayeritem;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Controls\IgnoredPlayeritem;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Windows\GenericPlayerList;
use ManiaLivePlugins\eXpansion\Chat_Admin\Gui\Windows\ParameterDialog;
use ManiaLivePlugins\eXpansion\Chat_Admin\Structures\ActionDuration;
use ManiaLivePlugins\eXpansion\Core\Config;
use ManiaLivePlugins\eXpansion\Core\Core;
use ManiaLivePlugins\eXpansion\Core\types\ExpPlugin;
use ManiaLivePlugins\eXpansion\Helpers\Helper;
use ManiaLivePlugins\eXpansion\Helpers\Storage;
use ManiaLivePlugins\eXpansion\Helpers\TimeConversion;
use Maniaplanet\DedicatedServer\Structures\GameInfos;
use Maniaplanet\DedicatedServer\Structures\Player;
use Maniaplanet\DedicatedServer\Structures\PlayerBan;
use Phine\Exception\Exception as Exception2;

class SeXMatch extends \ManiaLivePlugins\eXpansion\Core\types\BasicPlugin
{
	
	private $confdatei, $conf;
	
	public function exp_onInit()
	{
		parent::exp_onInit();
		ParameterDialog::$mainPlugin = $this;
		$this->addDependency(new Dependency('\ManiaLivePlugins\eXpansion\AdminGroups\AdminGroups'));
	}
	
	public function exp_onLoad()
	{
		parent::exp_onLoad();

		$this->confdatei = "./config/SeXPlugin_Match.json";
		// Wenn die Config noch nicht existiert, muss sie erstellt werden
		if(!file_exists($this->confdatei))
		{
			$this->conf['ICalURL'] = "";
			$this->conf['SRVpwd'] = "";
			$this->conf['MatchListe'] = "";
			$jsonconf = json_encode($this->conf);
			file_put_contents($this->confdatei,$jsonconf);
		}
		// Wenn es die Config gibt, muss diese eingelesen werden
		if(file_exists($this->confdatei))
		{
			$this->conf = json_decode(file_get_contents($this->confdatei),true);
		} 
		
		$admingroup = AdminGroups::getInstance();
		
		$cmd = AdminGroups::addAdminCommand('sexmatch update', $this, 'sexmatch_update', Permission::server_password);
		$cmd->setHelp('Update der Match-Liste');
		
		$cmd = AdminGroups::addAdminCommand('sexmatch set url', $this, 'sexmatch_seturl', Permission::server_password);
		$cmd->setHelp('Setzt die ICal-URL zur Matchliste');
		$cmd = AdminGroups::addAdminCommand('sexmatch get url', $this, 'sexmatch_geturl', Permission::server_password);
		$cmd->setHelp('Gibt die ICal-URL zur Matchliste');
		
		$cmd = AdminGroups::addAdminCommand('sexmatch set pwd', $this, 'sexmatch_setpwd', Permission::server_password);
		$cmd->setHelp('Setzt das Server-Passwort');
		$cmd = AdminGroups::addAdminCommand('sexmatch get pwd', $this, 'sexmatch_getpwd', Permission::server_password);
		$cmd->setHelp('Gibt das Server-Passwort');
		
		$this->enableTickerEvent();
	}

	public function exp_onReady()
	{
		$this->enableDedicatedEvents();
		
		\ManiaLive\Utilities\Logger::getLog('SeXPlugin')->write("SeXMatch geladen...");
		$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> SeXMatch Plugin geladen...');
	}
	
	public function onTick()
	{
		if (time() % 10 == 0) {
			$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> tick ('.time().') ...');
		}
	}

	
	function sexmatch_update($fromLogin, $params)
	{
		\ManiaLive\Utilities\Logger::getLog('SeXPlugin')->write("/admin sexmatch update aufgerufen");
		
		if(empty($this->conf['ICalURL']))
		{
			$this->connection->chatSendServerMessage('$<$fffSeXMatch:$z$s$> Update abgebrochen - Grund: Es wurde keine ICal-URL hinterlegt!');
			return false;
		}
		else
		{
			$this->connection->chatSendServerMessage('$<$fffSeXMatch:$z$s$> Update der Match-Liste...');
		}
		
		date_default_timezone_set('Europe/Berlin');
		require_once 'ical.class.php';

		$ical = new ical($this->conf['ICalURL']);
		$array = $ical->events();
		
		for($i=0;$i<=$ical->event_count;$i++)
		{
			$date = $array[$i]['DTSTART'];
			$ts = strtotime($date);
			$msg = $ts." ".date("d.m.Y H:i:s",$ts)."/".$array[$i]['SUMMARY']."/".$array[$i]['LOCATION']."\r\n";
			$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> '.$msg.'...');
			
			$match['timestamp'] 	= $ts;
			$match['start'] 		= date("d.m.Y H:i:s",$ts);
			$match['settings'] 		= $array[$i]['LOCATION'];
			$match['titel'] 		= $array[$i]['SUMMARY'];
			$match['beschreibung'] 	= $array[$i]['DESCRIPTION'];
			
			$this->conf['MatchListe'][$i] = $match;
			$match = "";
			$i++;
		}
		
		$this->save_config();
		
	}
	
	function sexmatch_seturl($fromLogin, $params)
	{
		$this->conf['ICalURL'] = $params[0];
		$this->save_config();
	}
	
	function sexmatch_geturl($fromLogin, $params)
	{
		$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> '.$this->conf['ICalURL'].'...');
	}
	
	function sexmatch_setpwd($fromLogin, $params)
	{
		$this->conf['SRVpwd'] = $params[0];
		$this->save_config();
	}
	
	function sexmatch_getpwd($fromLogin, $params)
	{
		$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> '.$this->conf['SRVpwd'].'...');
	}
	
	function sexmatch_do($fromLogin, $params)
	{
		// Player-liste ausgeben
		foreach ($this->connection->getPlayerList(-1, 0, 2) as $user) 
		{
			$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> Login: '.$user->login.'...');
		}
		
		// Logeintrag
		\ManiaLive\Utilities\Logger::getLog('eXpsclot')->write("MatchSettings/neu.txt geladen...");
		
		// Matchsettings laden
		$this->connection->loadMatchSettings("MatchSettings/neu.txt");
		
		try {
			$player = $this->storage->getPlayerObject("sclot");						// Player-Object vom Spieler "sclot" erstellen 
			$this->connection->kick($player, "match!");								// Spieler "sclot" kicken (mit grund "Match!")
			$this->connection->chatSendServerMessage('$<$fffServer:$z$s$> Match!');	// Ausgabe im Chat 
			$this->connection->setServerPassword("lala"); 							// serverpasswort setzen
			$this->connection->setServerPasswordForSpectator("lala"); 				// Spec pwd setzen
		} catch (Exception $e) {
			$this->sendErrorChat($fromLogin, $e->getMessage());
		}
	}
	
	function save_config()
	{
		$jsonconf = json_encode($this->conf);
		file_put_contents($this->confdatei,$jsonconf);
	}
	
	public function exp_onUnLoad()
	{
		
	}
}

?>