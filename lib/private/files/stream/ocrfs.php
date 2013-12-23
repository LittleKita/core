<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Stream;

/**
 * a stream wrappers for ownCloud's virtual filesystem
 */
class OCRFS {
	protected static $datadirectory = NULL;

	protected static function setup() {
		if(self::$datadirectory === NULL) {
			self::$datadirectory = \OC_Config::getValue("realdatadirectory", \OC::$SERVERROOT . '/data');
			if(substr(self::$datadirectory,-1) !== "/") {
				self::$datadirectory .= "/";
			}
		}
	}

	public function __construct() {
		self::setup();
	}

	public function getRealPath($path) {
		self::setup();
		$rpath = self::$datadirectory.substr($path, strlen('ocrfs://'));
		while(strpos($rpath,"//") !== false) {
			$rpath = str_replace("//","/",$rpath);
		}
		return $rpath;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$path = $this->getRealPath($path);
		$this->fileSource = fopen($path, $mode);
		if (is_resource($this->fileSource)) {
			$this->meta = stream_get_meta_data($this->fileSource);
		}
		return is_resource($this->fileSource);
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		fseek($this->fileSource, $offset, $whence);
	}

	public function stream_tell() {
		return ftell($this->fileSource);
	}

	public function stream_read($count) {
		return fread($this->fileSource, $count);
	}

	public function stream_write($data) {
		return fwrite($this->fileSource, $data);
	}

	public function stream_set_option($option, $arg1, $arg2) {
		switch ($option) {
			case STREAM_OPTION_BLOCKING:
				stream_set_blocking($this->fileSource, $arg1);
				break;
			case STREAM_OPTION_READ_TIMEOUT:
				stream_set_timeout($this->fileSource, $arg1, $arg2);
				break;
			case STREAM_OPTION_WRITE_BUFFER:
				stream_set_write_buffer($this->fileSource, $arg1, $arg2);
		}
	}

	public function stream_stat() {
		return fstat($this->fileSource);
	}

	public function stream_lock($mode) {
		flock($this->fileSource, $mode);
	}

	public function stream_flush() {
		return fflush($this->fileSource);
	}

	public function stream_eof() {
		return feof($this->fileSource);
	}

	public function url_stat($path) {
		$path = $this->getRealPath($path);
		if (file_exists($path)) {
			return stat($path);
		} else {
			return false;
		}
	}

	public function stream_close() {
		fclose($this->fileSource);
	}

	public function unlink($path) {
		$path = $this->getRealPath($path);
		return unlink($path);
	}

	public function dir_opendir($path, $options) {
		$path = $this->getRealPath($path);
		$this->path = $path;
		$this->dirSource = opendir($path);
		if (is_resource($this->dirSource)) {
			$this->meta = stream_get_meta_data($this->dirSource);
		}
		return is_resource($this->dirSource);
	}

	public function dir_readdir() {
		return readdir($this->dirSource);
	}

	public function dir_closedir() {
		closedir($this->dirSource);
	}

	public function dir_rewinddir() {
		rewinddir($this->dirSource);
	}
}
