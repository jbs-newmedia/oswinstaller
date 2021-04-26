<?php

/**
 * This file is part of the osWFrame package
 *
 * @author Juergen Schwind
 * @copyright Copyright (c) JBS New Media GmbH - Juergen Schwind (https://jbs-newmedia.com)
 * @package osWFrame Installer
 * @link https://oswframe.com
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License 3
 */

date_default_timezone_set('Europe/Berlin');

class Installer {

	/**
	 * Major-Version der Klasse.
	 */
	private const CLASS_MAJOR_VERSION=1;

	/**
	 * Minor-Version der Klasse.
	 */
	private const CLASS_MINOR_VERSION=0;

	/**
	 * Release-Version der Klasse.
	 */
	private const CLASS_RELEASE_VERSION=0;

	/**
	 * Extra-Version der Klasse.
	 * Zum Beispiel alpha, beta, rc1, rc2 ...
	 */
	private const CLASS_EXTRA_VERSION='beta';

	/**
	 * @var string
	 */
	private string $installer_sha1='';

	/**
	 * @var string
	 */
	private string $frame_path='';

	/**
	 * @var string
	 */
	private string $tools_path='';

	/**
	 * @var array
	 */
	private array $serverlist=[];

	/**
	 * @var array
	 */
	private array $server_connected=[];

	/**
	 * @var array
	 */
	private array $package_installed=[];

	/**
	 * @var array
	 */
	private array $error_messages=[];

	/**
	 * Installer constructor.
	 */
	public function __construct() {
		$this->installer_sha1=sha1_file(__FILE__);
		$this->frame_path=dirname(__FILE__).DIRECTORY_SEPARATOR;
		$this->tools_path=$this->frame_path.'oswtools'.DIRECTORY_SEPARATOR;
	}

	/**
	 * @param string $serverlist
	 * @param string $json_data
	 * @return object
	 */
	public function setServerList(string $serverlist, string $json_data):object {
		$this->serverlist[$serverlist]=json_decode($json_data, true);

		return $this;
	}

	/**
	 * @return object
	 */
	public function connectServerList():object {
		foreach ($this->serverlist as $serverlist=>$serverlist_details) {
			foreach ($serverlist_details['data'] as $key=>$server_details) {
				$_content=$this->getUrlData($server_details['server_url']);
				if ((strlen($_content)>=26)&&(strlen($_content)<=128)) {
					if (stristr($_content, 'osWFrame Release Server')) {
						$this->server_connected[$serverlist]=$server_details;
						$this->server_connected[$serverlist]['connected']=true;
						$this->server_connected[$serverlist]['server_name_real']=$_content;

						return $this;
					}
				}
			}
		}

		return $this;
	}

	/**
	 * @param string $package
	 * @param string $release
	 * @param string $serverlist
	 * @return bool
	 */
	public function installPackage(string $package, string $release, string $serverlist):bool {
		if ((!isset($this->server_connected[$serverlist]))||($this->server_connected[$serverlist]['connected']!==true)) {
			$this->error_messages[]=$serverlist.': not connected';

			return false;
		}
		$package_checksum=$this->getUrlData($this->server_connected[$serverlist]['server_url'].'?action=get_checksum&package='.$package.'&release='.$release.'&version=0');
		$package_data=$this->getUrlData($this->server_connected[$serverlist]['server_url'].'?action=get_content&package='.$package.'&release='.$release.'&version=0');
		if ($package_checksum==sha1($package_data)) {
			$file=$this->frame_path.$package.'-'.$release.'.zip';
			file_put_contents($file, $package_data);
			if ($this->unpackFile($file, $this->frame_path)===true) {
				$this->package_installed[]=$serverlist.'#'.$package.'#'.$release;
			} else {
				$this->error_messages[]=$serverlist.' '.$package.'-'.$release.': can not unpacked';
			}
			$this->delFile($file);
			$json_file=$this->tools_path.'resources'.DIRECTORY_SEPARATOR.'json'.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.$package.'-'.$release.'.json';
			if (file_exists($json_file)) {
				$json_data=json_decode(file_get_contents($json_file), true);
				if (isset($json_data['required'])) {
					foreach ($json_data['required'] as $package=>$package_data) {
						if (!in_array($package_data['serverlist'].'#'.$package_data['package'].'#'.$package_data['release'], $this->package_installed)) {
							$this->installPackage($package_data['package'], $package_data['release'], $package_data['serverlist']);
						}
					}
				}
			}
		} else {
			$this->error_messages[]=$serverlist.' '.$package.'-'.$release.': checksum mismatched';
		}

		return true;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function unpackFile(string $file):bool {
		$chmod_dir=0755;
		$chmod_file=0644;
		$Zip=new ZipArchive();
		$Zip->open($file);
		if ($Zip->numFiles>0) {
			if (!is_dir($this->frame_path)) {
				mkdir($this->frame_path);
			}
			for ($i=0; $i<$Zip->numFiles; $i++) {
				$stat=$Zip->statIndex($i);
				if (($stat['crc']==0)&&($stat['size']==0)) {
					# dir
					if (!is_dir($this->frame_path.$stat['name'])) {
						mkdir($this->frame_path.$stat['name']);
					}
					@chmod($this->frame_path.$stat['name'], $chmod_dir);
				} else {
					#file
					$data=$Zip->getFromIndex($i);
					file_put_contents($this->frame_path.$stat['name'], $data);
					@chmod($this->frame_path.$stat['name'], $chmod_file);
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function delFile(string $file):bool {
		if (file_exists($file)) {
			unlink($file);
			if (!file_exists($file)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $file
	 * @return string
	 */
	public function getUrlData(string $file):string {
		if (!isset($_SERVER['SERVER_NAME'])) {
			$_SERVER['SERVER_NAME']='';
		}
		if (!strpos($file, '?')) {
			$file.='?server_name='.urlencode($_SERVER['SERVER_NAME']);
		} else {
			$file.='&server_name='.urlencode($_SERVER['SERVER_NAME']);
		}
		$file.='&frame_key=unset';
		if (function_exists('curl_init')) {
			$res=curl_init();
			curl_setopt($res, CURLOPT_URL, $file);
			curl_setopt($res, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($res, CURLOPT_SSL_VERIFYPEER, 0);
			$file=curl_exec($res);
			curl_close($res);
		} else {
			$res=@fopen($file, 'r');
			if (!$res) {
				$file='';
			} else {
				$file='';
				while (feof($res)!=1) {
					$file.=fgets($res, 1024);
				}
				fclose($res);
			}
		}

		return $file;
	}

	public function finish() {
		if ($this->error_messages!==[]) {
			echo '<strong>Installer failed:</strong><br/>';
			echo implode('<br/>', $this->error_messages);
			die();
		}

		$installer_sha1=sha1_file(__FILE__);
		if ($installer_sha1==$this->installer_sha1) {
			$this->delFile(__FILE__);
		}

		header('Location: oswtools/');
	}

}

$Installer=new Installer();
$Installer->setServerList('oswframe2k20', '{"info":{"name":"osWFrame2k20","package":"tools.main"},"data":{"1":{"server_id":"1","server_name":"osWFrame Release Server #1 (hosted by jbs-newmedia.de)","server_url":"https:\/\/jbs-newmedia.de\/oswsource2k20\/index.php"},"2":{"server_id":"2","server_name":"osWFrame Release Server #2 (hosted by hetzner.de)","server_url":"https:\/\/srcmi.eu\/oswsource2k20\/index.php"},"3":{"server_id":"3","server_name":"osWFrame Release Server #3 (hosted by ionos.de)","server_url":"https:\/\/srcma.eu\/oswsource2k20\/index.php"},"4":{"server_id":"4","server_name":"osWFrame Release Server #4 (hosted by all-inkl.com)","server_url":"https:\/\/srcmc.eu\/oswsource2k20\/index.php"}}}');
$Installer->connectServerList();
$Installer->installPackage('tools.main', 'stable', 'oswframe2k20');
$Installer->installPackage('tools.toolmanager', 'stable', 'oswframe2k20');
$Installer->finish();

?>