<?
namespace ManiaLivePlugins\SeXPlugins\SeXMatch;

class MetaData extends \ManiaLivePlugins\eXpansion\Core\types\Config\MetaData
{
	public function onBeginLoad() 
	{
		parent::onBeginLoad();
		
		$this->setName("SeXMatch");
		$this->setDescription("Match-Helper Plugin for eXpansion");
		
	}
}

?>