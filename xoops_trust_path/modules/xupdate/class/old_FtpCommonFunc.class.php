<?php

// Xupdate_ftp class object
require_once XUPDATE_TRUST_PATH .'/class/Root.class.php';
require_once XUPDATE_TRUST_PATH . '/class/Ftp.class.php';

class Xupdate_FtpCommonFunc {

	public /*** XCube_Root ***/ $mRoot = null;
	public /*** Xupdate_Module ***/ $mModule = null;
	public /*** Xupdate_AssetManager ***/ $mAsset = null;

	public $Xupdate  ;	// Xupdate instance
	public $Ftp  ;	// FTP instance
	public $Func ;	// Functions instance
	public $mod_config ;
	public $content ;

	public $downloadDirPath;
	public $exploredDirPath;
	public $downloadUrlFormat;

	public $target_key;
	public $target_type;

	public function __construct() {

		$this->mRoot =& XCube_Root::getSingleton();
		$this->mModule =& $this->mRoot->mContext->mModule;
		$this->mAsset =& $this->mModule->mAssetManager;

		$this->Xupdate = new Xupdate_Root ;// Xupdate instance
		$this->Ftp =& $this->Xupdate->Ftp ;		// FTP instance
		$this->Func =& $this->Xupdate->func ;		// Functions instance
		$this->mod_config = $this->mRoot->mContext->mModuleConfig ;	// mod_config

		$this->downloadDirPath = $this->Xupdate->params['temp_path'];
		$this->downloadUrlFormat = $this->mod_config['Mod_download_Url_format'];

	}


	public function _downloadFile()
		{
		$realDirPath = $this->downloadDirPath;
		$realDirPath = realpath($realDirPath);
		if (empty($realDirPath) ) {
			$this->_set_error_log('downloadDirPath not found error in: '.$this->downloadDirPath);
			return false;
		}
		if (! chdir($realDirPath) ) {
			$this->_set_error_log('chdir error in: '.$this->downloadDirPath);
			return false;//chdir error
		}
		@mkdir($this->target_key);
		$this->exploredDirPath = realpath($this->downloadDirPath.'/'.$this->target_key);
		//directory traversal check
		if (strpos($this->exploredDirPath, $realDirPath) === false){
			$this->_set_error_log('directory traversal error in: '.$this->downloadDirPath.'/'.$this->target_key);
			return false;
		}
		if (!is_dir($this->exploredDirPath)){
			$this->_set_error_log('not is_dir error in: '.$this->downloadDirPath.'/'.$this->target_key);
			return false;//chdir error
		}

		$this->Ftp->appendMes('downladed in: '.$this->downloadDirPath.'<br />');
		$this->content.= 'downladed in: '.$this->downloadDirPath.'<br />';
		if (! chdir($this->exploredDirPath) ) {
			$this->_set_error_log('chdir error in: '.$this->downloadDirPath);
			return false;//chdir error
		}

		// TODO ファイルNotFound対策
		$url = $this->_getDownloadUrl();

		$downloadedFilePath = $this->_getDownloadFilePath();

		try {
			try {
				if(!function_exists('curl_init') ){
					throw new Exception('curl_init function no found fail',1);
				}
			} catch (Exception $e) {
				$this->_set_error_log($e->getMessage());
				return false;
			}

			$ch = curl_init($url);
			if($ch === false ){
				throw new Exception('curl_init fail',2);
			}
			$this->Ftp->appendMes('curl_init OK<br />');
		} catch (Exception $e) {
			$this->_set_error_log($e->getMessage());
			return false;
		}

		$fp = fopen($downloadedFilePath, "w");

		try {
			$setopt1 = curl_setopt($ch, CURLOPT_FILE, $fp);
			$setopt2 = curl_setopt($ch, CURLOPT_HEADER, 0);
			$setopt3 = curl_setopt($ch, CURLOPT_FAILONERROR, true);
			if(!$setopt1 || !$setopt2 || !$setopt2 ){
				throw new Exception('curl_setopt fail',3);
			}
		} catch (Exception $e) {
			$this->_set_error_log($e->getMessage());

			fclose($fp);
			return false;
		}

		try {
			try {
				if(!function_exists('curl_exec') ){
					throw new Exception('curl_exec function not found fail',4);
				}
			} catch (Exception $e) {
				$this->_set_error_log($e->getMessage());
				return false;
			}

			$result = curl_exec($ch);
			if($result === false ){
				throw new Exception('curl_exec fail',5);
			}
			$this->Ftp->appendMes('curl exec OK<br />');
		} catch (Exception $e) {
			$this->_set_error_log($e->getMessage());

			fclose($fp);
			return false;
		}

		fclose($fp);

		return true;
	}


	public function _unzipFile()
	{
		// local file name
		$downloadPath = $this->_getDownloadFilePath();
		$downloadPath = realpath($downloadPath);
		if (empty($downloadPath) ) {
			$this->_set_error_log('getDownloadFilePath not found error in: '.$this->_getDownloadFilePath());
			return false;
		}

		if (! chdir($this->exploredDirPath) ) {
			$this->_set_error_log('chdir error in: '.$this->exploredDirPath);
			return false;//chdir error
		}

		try {
			if(!class_exists('ZipArchive') ){
				throw new Exception('ZipArchive class not found fail',1);
			}
		} catch (Exception $e) {
			$this->_set_error_log($e->getMessage());
			return false;
		}
		$zip = new ZipArchive;

//		if ($zip->open($downloadPath) === TRUE) {
		try {
			 $result = $zip->open($downloadPath);
			if($result !==true ){
				throw new Exception('ZipArchive open fail ',2);
			}
		} catch (Exception $e) {
			$zip_open_error_arr = array(
				ZIPARCHIVE::ER_EXISTS => 'ER_EXISTS',
				ZIPARCHIVE::ER_INCONS => 'ER_INCONS',
				ZZIPARCHIVE::ER_INVAL => 'ER_INVAL',
				ZZIPARCHIVE::ER_MEMORY => 'ER_MEMORY',
				ZZIPARCHIVE::ER_NOENT => 'ER_NOENT',
				ZIPARCHIVE::ER_NOZIP => 'ER_NOZIP',
				ZIPARCHIVE::ER_OPEN => 'ER_OPEN',
				ZIPARCHIVE::ER_READ => 'ER_READ',
				ZIPARCHIVE::ER_SEEK => 'ER_SEEK'
			);
			$this->_set_error_log($e->getMessage().(in_array($result,$zip_open_error_arr) ? f : 'undfine' ));
			return false;
		}

		//$zip->extractTo('./');
		try {
			 $result = $zip->extractTo('./');
			if($result !==true ){
				throw new Exception('extractTo fail ',3);
			}
		} catch (Exception $e) {
			$this->_set_error_log($e->getMessage());

			$zip->close();
			return false;
		}

		$zip->close();
		$this->Ftp->appendMes('explored in: '.$this->exploredDirPath.'<br />');
		$this->content.= 'explored in: '.$this->exploredDirPath.'<br />';

		return true;
	}

	public function _upload () {

		$this->Ftp->app_login("127.0.0.1") ;
		//$this->uploadFiles();
		//$this->Ftp->app_logout();

	}

	public function _getDownloadUrl()
	{
		// TODO ファイルNotFound対策
		$url = sprintf($this->downloadUrlFormat, $this->target_key);
		return $url;
	}

	public function _getDownloadFilePath()
	{
		//$downloadPath = sprintf( $this->downloadUrlFormat, 'd3diary') ;
		$downloadPath = $this->downloadDirPath .'/'. $this->target_key . '.tgz';
		//$downloadPath = TP_ADDON_MANAGER_TMP_PATH .'/'. $this->target_key . '.tgz';
		return $downloadPath;
	}

	public function _cleanup($dir)
	{
		if ($handle = opendir("$dir")) {
			while (false !== ($item = readdir($handle))) {
				if ($item != "." && $item != "..") {
					if (is_dir("$dir/$item")) {
						$this->_cleanup("$dir/$item");
						$this->Ftp->appendMes('removing directory: '.$dir.'/'.$item.'<br />');
					} else {
						unlink("$dir/$item");
					}
				}
			}
			closedir($handle);
			rmdir($dir);
		}
	}


	public function _set_error_log($msg)
	{
		$this->Ftp->appendMes('<span style="color:red;">'.$msg.'</span><br />');
		$this->content.= '<span style="color:red;">'.$msg.'</span><br />';
	}

} // end class

?>