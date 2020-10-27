<?php

/**
 * Immocaster SDK
 * Datenspeicherung per Mysql(Datenbank)
 * in PHP Applikationen.
 *
 * @package    ImmobilienScout24 PHP-SDK
 * @author     Norman Braun (medienopfer98.de)
 * @link       http://www.immobilienscout24.de
 */

class Immocaster_Data_Mysql
{

	private $pdo = null;

	/**
     * Object mit der Verbindung
	 * zur Datenbank.
	 *
     * @var object
     */
	 private $_oDataConnection = null;

	/**
     * Name der Datenbank für
	 * die Datenspeicherung.
	 *
     * @var string
     */
	 private $_oDatabaseDb = null;

	/**
	 * Name der Tabelle für
	 * die Datenspeicherung.
	 *
     * @var string
	 */
	 private $_sTableName = 'Immocaster_Storage';

	/**
     * Zeit nachdem ein Request-
	 * token gelöscht wird (in Minuten).
	 *
     * @var int
     */
	 private $_iRequestExpire = 60;

    /**
     * Singleton Pattern für die Erstellung
	 * der Instanz von Immocaster_Data_Mysql.
     *
	 * @var array Verbindungsdaten für die Datenbank
	 * @var string Alternativer Name für die Tabelle
     * @return Immocaster_Data_Mysql
     */
	static private $instance = null;
	static public function getInstance($aConnection=array(),$sTableName=null)
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self($aConnection,$sTableName);
		}
		return self::$instance;
	}

	/**
     * Verbindung zur Datenbank aufbauen und Tabelle
	 * erzeugen, sofern diese noch nicht existiert.
     *
	 * @var array Verbindungsdaten für die Datenbank
	 * @var string Alternativer Name für die Tabelle
     * @return boolean
     */
	public function __construct($aConnection,$sTableName)
	{
		if($sTableName)
		{
			$this->_sTableName = $sTableName;
		}
		if($this->connectDatabase($aConnection))
		{
			// if the table do not exist creates it otherwise proof if some fields exist
			if(!$this->getDataTable())
			{
				// creates the table.
				$this->setDataTable();
					return true;
				}
			else
			{
				// proof if the 'ic_username' exist. If not exist adds the field
				// to the table
				$this->updateDataTableFields();
				return true;
			}
		}
		return false;
	}

	/**
     * MySQL-Datenbank konnektieren.
     *
	 * @var array Verbindungsdaten für die Datenbank
     * @return boolean
     */
	private function connectDatabase($aConnection=array())
	{
		$this->pdo = new PDO("mysql:dbname=$aConnection[4];host=$aConnection[1]", $aConnection[2], $aConnection[3]);
		if($this->pdo)
		{
			return true;
		}
		return false;
	}

	/**
     * Prüfen ob die Storage-Tabelle in der
	 * Datenbank existiert.
     *
     * @return boolean
     */
	private function getDataTable()
	{
		$sql = 'SHOW TABLES';
		$query = $this->pdo->query($sql);
		$tables = $query->fetchAll(PDO::FETCH_COLUMN);
		foreach ($tables as $table) {
			if($table === $this->_sTableName)
			{
				return true;
			}
		}
		return false;
	}

	/**
     * Storage-Tabelle in der
	 * MySql-Datenbank anlegen.
     *
     * @return void
     */
	private function setDataTable()
	{
		// the new code returns a boolean instead of void
		if(!$this->getDataTable())
		{

			$sql = "CREATE TABLE  `". $this->_sTableName."` (
			`ic_id` INT( 16 ) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ic_desc` VARCHAR( 32 ) NOT NULL,
            `ic_key` VARCHAR( 128 ) NOT NULL,
            `ic_secret` VARCHAR( 128 ) NOT NULL,
            `ic_expire` DATETIME NOT NULL,
            `ic_username` VARCHAR(60),
            PRIMARY KEY (  `ic_id` )
            ) ENGINE = MYISAM";

			$this->pdo->exec($sql);
		}

	}

	/**
     * Prüfen ob bestimmte Felder in der
	 * Datenbank existieren und bei Bedarf
	 * hinzufügen.
     *
     * @return boolean
     */
	private function updateDataTableFields()
	{
		$sql = "SHOW COLUMNS FROM `".$this->_sTableName."`";
		$query = $this->pdo->query($sql);
		$fields = $query->fetchAll(PDO::FETCH_ASSOC);

		$fieldExist = false;

		foreach ($fields as $key => $value) {
			if($value["Field"] === 'ic_username')
			{
				$fieldExist = true;
		}
	}

		if (!$fieldExist)
		{
			$sql = "ALTER TABLE `".$this->_sTableName."` ADD ic_username VARCHAR(60) NOT NULL;";
			$this->pdo->exec($sql);
		}

	}

	/**
     * Requesttoken speichern.
     *
	 * @var string Token
	 * @var string Secret
     * @return boolean
     */
	public function saveRequestToken($sToken,$sSecret)
	{
		$this->cleanRequestToken();
		if(strlen($sToken)>8)
		{
			$sql = "INSERT INTO `".$this->_sTableName."` (
			`ic_desc`,`ic_key`,`ic_secret`,`ic_expire`
			) VALUES (
			'REQUEST','".$sToken."','".$sSecret."','".date("Y-m-d H:i:s", strtotime ("+".$this->_iRequestExpire." minutes"))."'
			);";

			$query = $this->pdo->exec($sql); // returns the number of rows that were modified or deleted (1)

			if($query > 0)
			{
				return true;
			}
		}
		return false;
	}

	/**
     * Requesttoken ermitteln und zurückliefern.
     *
	 * @var string Token
     * @return mixed
     */
	public function getRequestToken($sToken=null)
	{
		if(strlen($sToken)<8){return false;}
		$sql = "SELECT * FROM `".$this->_oDatabaseDb."`.`".$this->_sTableName."` WHERE ic_desc='REQUEST' AND ic_key='".$sToken."'";
		$result = mysqli_query($sql,$this->_oDataConnection);
		$obj = mysqli_fetch_object($result);
		return $obj;
	}

    /**
     * Einen Requesttoken ohne Session ermitteln und zurückliefern.
     *
     * @return mixed
     */
    public function getRequestTokenWithoutSession()
    {
		$sql = "SELECT * FROM `".$this->_sTableName."` WHERE ic_desc='REQUEST' order by ic_id desc LIMIT 1";
		$query = $this->pdo->query($sql); // returns the number of rows that were modified or deleted (1)
		return $query->fetch(PDO::FETCH_OBJ); // if no object return false
    }

	/**
     * Requesttoken nach einer
	 * bestimmten Zeit löschen.
     *
     * @return void
     */
	private function cleanRequestToken()
	{
		$dNow = date("Y-m-d H:i:s");
		$sql = "SELECT * FROM `".$this->_sTableName."` WHERE ic_desc='REQUEST'";
		$query = $this->pdo->query($sql); // returns the number of rows that were modified or deleted (1)
		$obj = $query->fetch(PDO::FETCH_OBJ); // if no object return false

		if($obj)
		{
			if($obj->ic_expire < $dNow)
			{
				$this->deleteRequestTokenById($obj->ic_id);
			}
		}
	}

	/**
     * Alle Requesttoken der
	 * Applikation löschen.
     *
     * @return void
     */
	public function deleteRequestToken()
	{
		$sql = "DELETE FROM `".$this->_sTableName."` WHERE ic_desc='REQUEST'";
		$result = $this->pdo->exec($sql);
	}

	/**
     * Requesttoken anhand einer
	 * einzelnen ID löschen.
     *
	 * @param int Id des zu löschenden Tokens
     * @return boolean
     */
	public function deleteRequestTokenById($iId)
	{
		$sql = "DELETE FROM `".$this->_sTableName."` WHERE ic_desc='REQUEST' AND ic_id=".$iId;
		$this->pdo->query($sql);
	}

	/**
     * Accesstoken für die
	 * Applikation speichern.
     *
	 * @var string Token
	 * @var string Secret
     * @return boolean
     */
    public function saveApplicationToken($sToken,$sSecret,$sUser)
    {
        if(strlen($sToken)>8)
        {
            $sql = 'SET sql_mode=(SELECT REPLACE(@@sql_mode,"NO_ZERO_DATE", ""));';

            $this->pdo->exec($sql);

            $sql = "INSERT INTO `".$this->_sTableName."` (
			`ic_desc`,`ic_key`,`ic_secret`,`ic_expire`,`ic_username`
			) VALUES (
			'APPLICATION','".$sToken."','".$sSecret."','0000-00-00 00:00:00','".$sUser."'
			);";

            $result = $this->pdo->exec($sql);

            if($result > 0)
            {
                $this->deleteRequestToken();
                return true;
            }
        }
        return false;
    }

	/**
     * Accesstoken für die Application
	 * ermitteln und zurückliefern.
     *
     * @return object
     */
	public function getApplicationToken($sUser)
	{
		$sql = "SELECT * FROM `".$this->_sTableName."` WHERE ic_desc='APPLICATION' AND ic_username='".$sUser."'";
		$query = $this->pdo->query($sql);
		$obj = $query->fetch(PDO::FETCH_OBJ);

		if(!empty((array)$obj))
		{
			return $obj;
		}
		else
		{
			return false;
		}
	}



	/**
     * Alle Accesstoken für die Application
	 * ermitteln und zurückliefern.
     *
     * @return array
     */
	public function getAllApplicationUsers()
	{
		$aUsers = array();
		$sql = "SELECT * FROM `".$this->_oDatabaseDb."`.`".$this->_sTableName."` WHERE ic_desc='APPLICATION'";
		$result = mysqli_query($sql,$this->_oDataConnection);
		while($obj = mysqli_fetch_object($result))
		{
			array_push($aUsers,$obj->ic_username);
		}
		return $aUsers;
	}

	/**
     * Accesstoken für die
	 * Applikation löschen.
     *
     * @return void
     */
	public function deleteApplicationToken()
	{
		$sql = "DELETE FROM `".$this->_oDatabaseDb."`.`".$this->_sTableName."` WHERE ic_desc='APPLICATION'";
		mysqli_query($sql,$this->_oDataConnection);
	}

}
