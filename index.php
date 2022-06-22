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

class Checker {

	/**
	 * Major-Version der Klasse.
	 */
	protected const CLASS_MAJOR_VERSION=1;

	/**
	 * Minor-Version der Klasse.
	 */
	protected const CLASS_MINOR_VERSION=0;

	/**
	 * Release-Version der Klasse.
	 */
	protected const CLASS_RELEASE_VERSION=0;

	/**
	 * Extra-Version der Klasse.
	 * Zum Beispiel alpha, beta, rc1, rc2 ...
	 */
	protected const CLASS_EXTRA_VERSION='';

	/**
	 * @var string
	 */
	protected string $base_path='';

	/**
	 * @var array
	 */
	protected array $phpinfo=[];

	/**
	 * @var string
	 */
	protected string $error_message='';

	/**
	 * Checker constructor.
	 */
	function __construct() {
		$this->setBasePath(realpath(__DIR__).DIRECTORY_SEPARATOR);
		$this->parsePHPInfo();
	}

	/**
	 * @param string $base_path
	 */
	public function setBasePath(string $base_path):void {
		$this->base_path=$base_path;
	}

	/**
	 * @return string
	 */
	public function getBasePath():string {
		return $this->base_path;
	}

	/**
	 * @return void
	 *
	 * @source https://gist.github.com/sbmzhcn/6255314
	 */
	protected function parsePHPInfo():void {
		ob_start();
		phpinfo(INFO_MODULES);
		$s=ob_get_contents();
		ob_end_clean();
		$s=strip_tags($s, '<h2><th><td>');
		$s=preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $s);
		$s=preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $s);
		$t=preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
		$this->phpinfo=[];
		$count=count($t);
		$p1='<info>([^<]+)<\/info>';
		$p2='/'.$p1.'\s*'.$p1.'\s*'.$p1.'/';
		$p3='/'.$p1.'\s*'.$p1.'/';
		for ($i=1; $i<$count; $i++) {
			if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $t[$i], $matchs)) {
				$name=trim($matchs[1]);
				$vals=explode("\n", $t[$i+1]);
				foreach ($vals as $val) {
					if (preg_match($p2, $val, $matchs)) { // 3cols
						$this->phpinfo[$name][trim($matchs[1])]=[trim($matchs[2]), trim($matchs[3])];
					} elseif (preg_match($p3, $val, $matchs)) { // 2cols
						$this->phpinfo[$name][trim($matchs[1])]=trim($matchs[2]);
					}
				}
			}
		}
	}

	/**
	 * @param string $core
	 * @param string $key
	 * @param string $value
	 * @return bool
	 */
	protected function checkValue(string $core, string $key, string $value):bool {
		if (!isset($this->phpinfo[$core])) {
			return false;
		}

		if (!isset($this->phpinfo[$core][$key])) {
			return false;
		}

		if ($this->phpinfo[$core][$key]!=$value) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function checkEnvironment():bool {
		$errors=[];

		if ((!defined('PHP_VERSION_ID'))||(PHP_VERSION_ID<80000)) {
			$errors[]='This version of osWFrame requires PHP 8.0 or higher (You are currently running PHP '.phpversion().').';
		}

		if ($this->checkValue('bcmath', 'BCMath support', 'enabled')!==true) {
			$errors[]='BCMath support is missing (php-bcmath)';
		}
		if ($this->checkValue('curl', 'cURL support', 'enabled')!==true) {
			$errors[]='cURL support is missing (php-curl)';
		}
		if ($this->checkValue('gd', 'GD Support', 'enabled')!==true) {
			$errors[]='GD support is missing (php-gd)';
		}
		if ($this->checkValue('intl', 'Internationalization support', 'enabled')!==true) {
			$errors[]='Internationalization support is missing (php-intl)';
		}
		if ($this->checkValue('mbstring', 'Multibyte Support', 'enabled')!==true) {
			$errors[]='Multibyte support is missing (php-mbstring)';
		}
		if ($this->checkValue('mysqli', 'MysqlI Support', 'enabled')!==true) {
			$errors[]='MysqlI support is missing (php-mysqli)';
		}
		if ($this->checkValue('xml', 'XML Support', 'active')!==true) {
			$errors[]='XML support is missing (php-xml)';
		}
		if ($this->checkValue('zip', 'Zip', 'enabled')!==true) {
			$errors[]='Zip support is missing (php-zip)';
		}

		$time=time();

		$file=$this->getBasePath().$time.'_file.dummy';
		@file_put_contents($file, $time);
		if (!file_exists($file)) {
			$errors[]='Can\'t create files (chmod)';
		} else {
			$content=@file_get_contents($file);
			if ($content!=$time) {
				$errors[]='Can\'t read files (chmod)';
			} else {
				@unlink($file);
				if (file_exists($file)) {
					$errors[]='Can\'t remove files (chmod)';
				}
			}
		}

		$dir=$this->getBasePath().$time.'_dir.dummy';
		@mkdir($dir);
		if (!is_dir($dir)) {
			$errors[]='Can\'t create directories (chmod)';
		} else {
			@rmdir($dir);
			if (is_dir($dir)) {
				$errors[]='Can\'t remove directories (chmod)';
			}
		}

		if ($errors!=[]) {
			$this->setErrorMessage(implode('<br/>', $errors));

			return false;
		}

		$this->setErrorMessage('');

		return true;
	}

	/**
	 * @param string $error_message
	 */
	protected function setErrorMessage(string $error_message):void {
		$this->error_message=$error_message;
	}

	/**
	 * @return string
	 */
	public function getErrorMessage():string {
		return $this->error_message;
	}

}

class Installer {

	/**
	 * Major-Version der Klasse.
	 */
	protected const CLASS_MAJOR_VERSION=1;

	/**
	 * Minor-Version der Klasse.
	 */
	protected const CLASS_MINOR_VERSION=2;

	/**
	 * Release-Version der Klasse.
	 */
	protected const CLASS_RELEASE_VERSION=0;

	/**
	 * Extra-Version der Klasse.
	 * Zum Beispiel alpha, beta, rc1, rc2 ...
	 */
	protected const CLASS_EXTRA_VERSION='';

	/**
	 * @var string
	 */
	protected string $installer_sha1='';

	/**
	 * @var string
	 */
	protected string $frame_path='';

	/**
	 * @var string
	 */
	protected string $tools_path='';

	/**
	 * @var array
	 */
	protected array $serverlist=[];

	/**
	 * @var array
	 */
	protected array $server_connected=[];

	/**
	 * @var array
	 */
	protected array $package_installed=[];

	/**
	 * @var array
	 */
	protected array $error_messages=[];

	/**
	 * @var int
	 */
	protected int $chmod_dir=0;

	/**
	 * @var int
	 */
	protected int $chmod_file=0;

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
	 * @return $this
	 */
	public function setServerList(string $serverlist, string $json_data):self {
		$this->serverlist[$serverlist]=json_decode($json_data, true);

		return $this;
	}

	/**
	 * @return $this
	 */
	public function connectServerList():self {
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
	 * @return $this
	 */
	public function writeHTAccess():self {
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
	 * @return void
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

$checker=new Checker();
if ($checker->checkEnvironment()!==true) {
	echo '<html><head><title>osWTools Installer</title></head><body>';
	echo '<div style="width:50%; min-width: 400px; margin:auto auto; margin-top:10%; padding:20px; border:1px solid #999; background-color:#efefef; font-family:verdana; border-radius:3px;"><img style="width:400px;" alt="" src="data:image/svg+xml;base64,PHN2ZyBpZD0iRWJlbmVfMSIgZGF0YS1uYW1lPSJFYmVuZSAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMDguODYgNDAuOTciPjxkZWZzPjxzdHlsZT4uY2xzLTF7ZmlsbDojMWQxZDFiO30uY2xzLTJ7ZmlsbDojRkU3RjAyO30uY2xzLTN7ZmlsbDojZmZmO30uY2xzLTR7ZmlsbDpub25lO308L3N0eWxlPjwvZGVmcz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0yMzYuODUsNDIzLjM2YzAtMy4zMiwxLjgzLTYuNSw1LjQ4LTYuNXM1LjQ3LDMuMTgsNS40Nyw2LjUtMS44NSw2LjQ5LTUuNDcsNi40OVMyMzYuODUsNDI2LjY1LDIzNi44NSw0MjMuMzZabTcuNTcsMGMwLTEuNTYtLjMxLTQuMDctMi4wOS00LjA3cy0yLjA5LDIuNTEtMi4wOSw0LjA3LjMxLDQuMDcsMi4wOSw0LjA3UzI0NC40Miw0MjQuOTIsMjQ0LjQyLDQyMy4zNloiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xODYuODMgLTQwMC40NykiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0yNTcuMjksNDIwLjc4Yy0uMTUtLjg3LS40NC0xLjQ5LTEuNDItMS40OWExLjMxLDEuMzEsMCwwLDAtMS4zMywxLjM4YzAsMi4xMyw2LC41OCw2LDUuMjUsMCwyLjY0LTIuMiwzLjkzLTQuNzMsMy45M3MtNC4xOS0xLjI0LTQuNTItMy44MmwzLjA5LS4zNGMuMDcuODkuNTQsMS43NCwxLjU0LDEuNzQuODIsMCwxLjYtLjM4LDEuNi0xLjM0LDAtMi4xNy02LS40LTYtNS4yNCwwLTIuNTIsMi4xNi00LDQuNTgtNGE0LjIzLDQuMjMsMCwwLDEsNC4zMiwzLjU0WiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTE4Ni44MyAtNDAwLjQ3KSIvPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTI3OC4yOSw0MjkuNTloLTMuNTZsLTIuMzEtMTJoLS4wNWwtMi4zMywxMmgtMy41NmwtMy42NS0xNy4zMWgzLjQzbDIuMDYsMTIuMTloLjA1bDIuMTYtMTIuMTloMy44MmwyLjE2LDEyLjE5aC4wNWwyLjI0LTEyLjE5aDMuNDNaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMTg2LjgzIC00MDAuNDcpIi8+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMjk1Ljc3LDQxNS4yMmgtNy4zdjQuMTNoNS42M3YyLjk0aC01LjYzdjcuM0gyODVWNDEyLjI4aDEwLjc3WiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTE4Ni44MyAtNDAwLjQ3KSIvPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTMwMi41Myw0MTkuMzFjMS4yNy0xLjQ5LDIuMjktMi40NSwzLjgtMi40NUgzMDd2My4zYTQuMzYsNC4zNiwwLDAsMC0xLS4xNCwzLjc4LDMuNzgsMCwwLDAtMy40Nyw0LjF2NS40N2gtMy4yOVY0MTcuMTNoMy4yOVoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xODYuODMgLTQwMC40NykiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0zMTcuMzksNDI4LjQ1YTUuMzMsNS4zMywwLDAsMS0zLjU0LDEuNCwzLjY5LDMuNjksMCwwLDEtMy45Mi0zLjY5YzAtMy41MSw0LjYzLTQuMjIsNy4yOC00LjUxLjIyLTEuNzQtLjM2LTIuMzYtMS41OC0yLjM2YTEuNzgsMS43OCwwLDAsMC0yLDEuNTZsLTMuMTYtLjQ5Yy43My0yLjY1LDIuNTEtMy41LDUuMTItMy41LDMuMzYsMCw0Ljg5LDEuNiw0Ljg5LDMuOTRWNDI3YTUuODIsNS44MiwwLDAsMCwuNDUsMi42aC0zLjIxWm0tLjE4LTQuNTZjLTEuMzYuMjUtNCwuMzgtNCwyLjIsMCwuNzQuMzYsMS4zNCwxLjEzLDEuMzRhMy42NSwzLjY1LDAsMCwwLDIuOTItMS42MloiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xODYuODMgLTQwMC40NykiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0zMjgsNDE4LjQyYTYsNiwwLDAsMSwzLjcxLTEuNTYsMy4yNSwzLjI1LDAsMCwxLDMuMTgsMS43Miw2LDYsMCwwLDEsMy44NS0xLjcyLDMuMzgsMy4zOCwwLDAsMSwzLjUsMy43MnY5SDMzOXYtNy44NmMwLTEsLjA3LTIuMjItMS4zMS0yLjIyYTMuMTgsMy4xOCwwLDAsMC0yLjUxLDEuNDd2OC42MWgtMy4yOXYtNy44NmMwLTEsLjA2LTIuMjItMS4zMi0yLjIyQTMuMTgsMy4xOCwwLDAsMCwzMjgsNDIxdjguNjFoLTMuMjlWNDE3LjEzSDMyOFoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xODYuODMgLTQwMC40NykiLz48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0zNDkuMzcsNDI0LjE0Yy0uMTIsMS41MS44LDMuMjksMi40OSwzLjI5LDEuMTgsMCwxLjgyLTEsMi4wNy0ybDMsLjU1YTUuNTcsNS41NywwLDAsMS01LjQ1LDMuODdjLTMuNjIsMC01LjQ3LTMuMi01LjQ3LTYuNDlzMS44My02LjUsNS40Ny02LjVjMy4xNCwwLDUuNTQsMS40OSw1LjQ1LDcuMjhabTQuMzgtMi4wN2MwLTEuNC0uNTYtMi43OC0yLjE0LTIuNzhzLTIuMDksMS40NC0yLjEzLDIuNzhaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMTg2LjgzIC00MDAuNDcpIi8+PHJlY3QgY2xhc3M9ImNscy0yIiB3aWR0aD0iNDAuOTciIGhlaWdodD0iNDAuOTciIHJ4PSI3LjI2Ii8+PHBhdGggY2xhc3M9ImNscy0zIiBkPSJNMTkzLjE1LDQwOGw4Ljg1LDguODZhMi44OCwyLjg4LDAsMCwwLS4yMSwxLjEzdi4zMmgtOXYtOC42OGEzLjMzLDMuMzMsMCwwLDEsLjM4LTEuNjNtLS4zOCwxNS41OWg5di4zMkEzLjE2LDMuMTYsMCwwLDAsMjAyLDQyNWwtOC44Niw4Ljg2YTQuMTksNC4xOSwwLDAsMS0uNC0xLjZabTExLjkxLTUuMjhIMjEwdjUuMjhoLTUuMjdabS0xLjQ1LDhhMi44OCwyLjg4LDAsMCwwLDEuMTMuMjFoLjMydjlIMTk2YTMuMzMsMy4zMywwLDAsMS0xLjYzLS4zN1ptNi43My4yMWguMzJhMy4xNiwzLjE2LDAsMCwwLDEuMS0uMjRsOC44Niw4Ljg2YTQuMDcsNC4wNywwLDAsMS0xLjYuMzlIMjEwWm0xMS41Myw3LjQyLTguODUtOC44NmEyLjg4LDIuODgsMCwwLDAsLjIxLTEuMTN2LS4zMmg5djguNjhhMy4zMywzLjMzLDAsMCwxLS4zNywxLjYzbS4zNy0xNS41OWgtOVY0MThhMy4xNiwzLjE2LDAsMCwwLS4yNC0xLjFsOC44Ni04Ljg2YTQuMDcsNC4wNywwLDAsMSwuMzksMS42Wm0tMTAuNDUtMi42OGEyLjg4LDIuODgsMCwwLDAtMS4xMy0uMjFIMjEwdi05aDguNjhhMy4zMywzLjMzLDAsMCwxLDEuNjMuMzdabS02LjczLS4yMWgtLjMyYTMuMTYsMy4xNiwwLDAsMC0xLjEuMjRsLTguODYtOC44NmE0LjA3LDQuMDcsMCwwLDEsMS42LS4zOWg4LjY4Wk0xOTAuNTIsNDA5djkuMzJoLTEuOTR2NS4yOGgxLjk0djkuMzJzMCw0Ljg0LDQuODQsNC44NGg5LjMydjEuOTRIMjEwdi0xLjk0aDkuMzJzNC44NCwwLDQuODQtNC44NFY0MjMuNmgxLjk0di01LjI4aC0xLjk0VjQwOXMwLTQuODQtNC44NC00Ljg0SDIxMHYtMS45NGgtNS4yOHYxLjk0aC05LjMycy00Ljg0LDAtNC44NCw0Ljg0IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMTg2LjgzIC00MDAuNDcpIi8+PHJlY3QgY2xhc3M9ImNscy00IiB3aWR0aD0iMjA4Ljg2IiBoZWlnaHQ9IjQwLjk3Ii8+PC9zdmc+" /><br/><br/><br/><strong>Error</strong><pre>'.$checker->getErrorMessage().'</pre></div>';
	echo '</body></html>';
	die();
} else {
	$Installer=new Installer(0755, 0644);
	$Installer->setServerList('oswframe2k20', '{"info":{"name":"osWFrame2k20","package":"tools.main"},"data":{"1":{"server_id":"1","server_name":"osWFrame Release Server #1 (hosted by jbs-newmedia.de)","server_url":"https:\/\/jbs-newmedia.de\/oswsource2k20\/index.php"},"2":{"server_id":"2","server_name":"osWFrame Release Server #2 (hosted by hetzner.de)","server_url":"https:\/\/srcmi.eu\/oswsource2k20\/index.php"},"3":{"server_id":"3","server_name":"osWFrame Release Server #3 (hosted by ionos.de)","server_url":"https:\/\/srcma.eu\/oswsource2k20\/index.php"},"4":{"server_id":"4","server_name":"osWFrame Release Server #4 (hosted by all-inkl.com)","server_url":"https:\/\/srcmc.eu\/oswsource2k20\/index.php"}}}');
	$Installer->connectServerList();
	$Installer->installPackage('tools.main', 'stable', 'oswframe2k20');
	$Installer->installPackage('tools.toolmanager', 'stable', 'oswframe2k20');
	$Installer->finish();
}

?>