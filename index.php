<?php

/**
 * This file is part of the osWFrame package
 *
 * @author Juergen Schwind
 * @copyright Copyright (c) JBS New Media GmbH - Juergen Schwind (https://jbs-newmedia.com)
 * @package osWFrame Installer
 * @link https://oswframe.com
 * @license MIT License
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
	private const CLASS_MINOR_VERSION=1;

	/**
	 * Release-Version der Klasse.
	 */
	private const CLASS_RELEASE_VERSION=1;

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
	 * @var int
	 */
	private int $chmod_dir=0;

	/**
	 * @var int
	 */
	private int $chmod_file=0;

	/**
	 * Installer constructor.
	 *
	 * @param int $chmod_dir
	 * @param int $chmod_file
	 */
	public function __construct(int $chmod_dir=0, int $chmod_file=0) {
		if ($chmod_dir==0) {
			$chmod_dir=0755;
		}
		if ($chmod_file==0) {
			$chmod_file=0644;
		}
		$this->installer_sha1=sha1_file(__FILE__);
		$this->frame_path=dirname(__FILE__).DIRECTORY_SEPARATOR;
		$this->tools_path=$this->frame_path.'oswtools'.DIRECTORY_SEPARATOR;
		$this->chmod_dir=$chmod_dir;
		$this->chmod_file=$chmod_file;
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
					@chmod($this->frame_path.$stat['name'], $this->chmod_dir);
				} else {
					#file
					$data=$Zip->getFromIndex($i);
					file_put_contents($this->frame_path.$stat['name'], $data);
					@chmod($this->frame_path.$stat['name'], $this->chmod_file);
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

	/**
	 * @return object
	 */
	public function writeHTAccess():object {
		$file_ht=$this->tools_path.'.htaccess';
		if (file_exists($file_ht)!==true) {
			file_put_contents($file_ht, "# osWFrame .htaccess permission begin #\n\n# osWFrame .htaccess permission end #\n\n# osWFrame .htaccess block begin #\n\nRewriteEngine on\n\nRewriteRule ^tools.([a-z0-9-_]+).stable$ ?module=tools.$1.stable&%{QUERY_STRING} [L]\nRewriteRule ^tools.([a-z0-9-_]+).stable/$ ?module=tools.$1.stable&%{QUERY_STRING} [L]\nRewriteRule ^tools.([a-z0-9-_]+).stable/([a-z0-9-_]+)$ ?module=tools.$1.stable&action=$2&%{QUERY_STRING} [L]\nRewriteRule ^tools.([a-z0-9-_]+).stable/([a-z0-9-_]+)/$ ?module=tools.$1.stable&action=$2&%{QUERY_STRING} [L]\n\nRewriteRule ^([a-zA-Z0-9-_]+)/([a-zA-Z0-9-]+)?_([0-9]+)$ ?module=$1&element_id=$3&%{QUERY_STRING} [L]\nRewriteRule ^([a-zA-Z0-9-_]+)$ ?module=$1&%{QUERY_STRING} [L]\n\nErrorDocument 400 ?module=_errorlogger&error_status=400\nErrorDocument 401 ?module=_errorlogger&error_status=401\nErrorDocument 402 ?module=_errorlogger&error_status=402\nErrorDocument 403 ?module=_errorlogger&error_status=403\nErrorDocument 404 ?module=_errorlogger&error_status=404\nErrorDocument 405 ?module=_errorlogger&error_status=405\nErrorDocument 406 ?module=_errorlogger&error_status=406\nErrorDocument 407 ?module=_errorlogger&error_status=407\nErrorDocument 408 ?module=_errorlogger&error_status=408\nErrorDocument 409 ?module=_errorlogger&error_status=409\nErrorDocument 410 ?module=_errorlogger&error_status=410\nErrorDocument 411 ?module=_errorlogger&error_status=411\nErrorDocument 412 ?module=_errorlogger&error_status=412\nErrorDocument 413 ?module=_errorlogger&error_status=413\nErrorDocument 414 ?module=_errorlogger&error_status=414\nErrorDocument 415 ?module=_errorlogger&error_status=415\nErrorDocument 416 ?module=_errorlogger&error_status=416\nErrorDocument 417 ?module=_errorlogger&error_status=417\n\n# osWFrame .htaccess block end #");
			chmod($file_ht, $this->chmod_file);

			$file_pw=$this->tools_path.'.htpasswd';
			if (file_exists($file_pw)===true) {
				file_put_contents($file_ht, preg_replace('/# osWFrame .htaccess permission begin #(.*)# osWFrame .htaccess permission end #/Uis', '# osWFrame .htaccess permission begin #'."\n\nAuthType Basic\nAuthName \"osWTools\"\nAuthUserFile \"".$this->tools_path.".htpasswd\"\nrequire valid-user\n\n".'# osWFrame .htaccess permission end #', file_get_contents($file_ht)));
			}
		}

		return $this;
	}

	/**
	 *
	 */
	public function finish():void {
		if ($this->error_messages!==[]) {
			echo '<strong>Installer failed:</strong><br/>';
			echo implode('<br/>', $this->error_messages);
			die();
		}

		$installer_sha1=sha1_file(__FILE__);
		if ($installer_sha1==$this->installer_sha1) {
			$this->delFile(__FILE__);
		}

		$this->writeHTAccess();

		header('Location: oswtools/');
	}

}

$Installer=new Installer(0755, 0644);
$Installer->setServerList('oswframe2k20', '#$__SERVERLIST__$#');
$Installer->connectServerList();
$Installer->installPackage('tools.main', 'stable', 'oswframe2k20');
$Installer->installPackage('tools.toolmanager', 'stable', 'oswframe2k20');
$Installer->finish();

?>