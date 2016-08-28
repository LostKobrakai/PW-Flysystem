<?php namespace ProcessWire;

class FlyingPagefiles extends Pagefiles {

	/**
	 * Per the WireArray interface, items must be of type Pagefile
	 * 
	 * #pw-internal
	 * 
	 * @param mixed $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof FlyingPagefile;
	}

	/**
	 * Per the WireArray interface, return a blank Pagefile
	 * 
	 * #pw-internal
	 * 
	 * @return Pagefile
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new FlyingPagefile($this, '')); 
	}

	/**
	 * Get a value from this Pagefiles instance
	 *
	 * You may also specify a file's 'tag' and it will return the first Pagefile matching the tag.
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		return parent::get($key);
	}

	/**
	 * Get for direct access to properties
	 * 
	 * @param int|string $key
	 * @return bool|mixed|Page|Wire|WireData
	 * 
	 */
	public function __get($key) {
		return parent::__get($key); 
	}

	/**
	 * Add a new Pagefile item or filename
	 * 
	 * If give a filename (string) it will create the new `Pagefile` item from it and add it.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Pagefile|string $item If item is a string (filename) it will create the new `Pagefile` item from it and add it.
	 * @return $this
	 *
	 */
	public function add($item) {

		if(is_string($item)) {
			$item = $this->wire(new FlyingPagefile($this, $item)); 
		}

		return parent::add($item); 
	}

	/**
	 * Return the full disk path where files are stored
	 * 
	 * @return string
	 *
	 */
	public function path() {
		return str_replace($this->config->paths->files, '', $this->page->filesManager->path());;
	}

	/**
	 * Returns the web accessible index URL where files are stored
	 * 
	 * @return string
	 *
	 */
	public function url() {
		return '';
	}

	/**
	 * Given a basename, this method returns a clean version containing valid characters 
	 * 
	 * #pw-internal
	 *
	 * @param string $basename May also be a full path/filename, but it will still return a basename
	 * @param bool $originalize If true, it will generate an original filename if $basename already exists
	 * @param bool $allowDots If true, dots "." are allowed in the basename portion of the filename. 
	 * @param bool $translate True if we should translate accented characters to ascii equivalents (rather than substituting underscores)
	 * @return string
	 *
	 */ 
	public function cleanBasename($basename, $originalize = false, $allowDots = true, $translate = false) {

		$basename = strtolower($basename); 
		$dot = strrpos($basename, '.'); 
		$ext = $dot ? substr($basename, $dot) : ''; 
		$basename = basename($basename, $ext);
		$test = str_replace(array('-', '_', '.'), '', $basename);
		
		if(!ctype_alnum($test)) {
			if($translate) {
				$basename = $this->wire('sanitizer')->filename($basename, Sanitizer::translate); 
			} else {
				$basename = preg_replace('/[^-_.a-z0-9]/', '_', $basename);
				$basename = $this->wire('sanitizer')->filename($basename);
			}
		}
		
		if(!ctype_alnum(ltrim($ext, '.'))) $ext = preg_replace('/[^a-z0-9.]/', '_', $ext); 
		if(!$allowDots && strpos($basename, '.') !== false) $basename = str_replace('.', '_', $basename); 
		$basename .= $ext;

		if($originalize) { 
			$path = $this->path(); 
			$n = 0; 
			$p = pathinfo($basename);
			while($this->getPrimaryFlysystem()->has($path . $basename)) {
				$n++;
				$basename = "$p[filename]-$n.$p[extension]"; // @hani
				// $basename = (++$n) . "_" . preg_replace('/^\d+_/', '', $basename); 
			}
		}

		return $basename; 
	}

	/**
	 * Returns true if the given Pagefile is temporary, not yet published. 
	 * 
	 * You may also provide a 2nd argument boolean to set the temp status or check if temporary AND deletable.
	 * 
	 * #pw-internal
	 *
	 * @param Pagefile $pagefile
	 * @param bool|string $set Optionally set the temp status to true or false, or specify string "deletable" to check if file is temporary AND deletable.
	 * @return bool
	 *
	 */
	public function isTemp(Pagefile $pagefile, $set = null) {

		$isTemp = Pagefile::createdTemp == $pagefile->created;
		$checkDeletable = ($set === 'deletable' || $set === 'deleteable');
		
		if(!is_bool($set)) { 
			// temp status is not being set
			if(!$isTemp) return false; // if not a temp file, we can exit now
			if(!$checkDeletable) return $isTemp; // if not checking deletable, we can exit now
		}
		
		$now = time();
		$session = $this->wire('session');
		$pageID = $this->page ? $this->page->id : 0;
		$fieldID = $this->field ? $this->field->id : 0;
		$sessionKey = "tempFiles_{$pageID}_{$fieldID}";
		$tempFiles = $pageID && $fieldID ? $session->get($this, $sessionKey) : array();
		if(!is_array($tempFiles)) $tempFiles = array();
		
		if($isTemp && $checkDeletable) {
			$isTemp = false; 
			if(isset($tempFiles[$pagefile->basename])) {
				// if file was uploaded in this session and still temp, it is deletable
				$isTemp = true; 		
			} else if($pagefile->modified < ($now - 14400)) {
				// if file was added more than 4 hours ago, it is deletable, regardless who added it
				$isTemp = true; 
			}
			// isTemp means isDeletable at this point
			if($isTemp) {
				$this->getPrimaryFlysystem()->delete($tempFiles[$pagefile->basename]); 	
				// remove file from session - note that this means a 'deletable' check can only be used once, for newly uploaded files
				// as it is assumed you will be removing the file as a result of this method call
				if(count($tempFiles)) $session->set($this, $sessionKey, $tempFiles); 
					else $session->remove($this, $sessionKey); 
			}
		}

		if($set === true) {
			// set temporary status to true
			$pagefile->created = Pagefile::createdTemp;
			$pagefile->modified = $now; 
			$isTemp = true;
			if($pageID && $fieldID) { 
				$tempFiles[$pagefile->basename] = 1; 
				$session->set($this, $sessionKey, $tempFiles); 
			}

		} else if($set === false && $isTemp) {
			// set temporary status to false
			$pagefile->created = $now;
			$pagefile->modified = $now; 
			$isTemp = false;
			
			if(isset($tempFiles[$pagefile->basename])) {
				$this->getPrimaryFlysystem()->delete($tempFiles[$pagefile->basename]); 
				if(count($tempFiles)) {
					// set temp files back to session, minus current file
					$session->set($this, $sessionKey, $tempFiles); 
				} else {
					// if temp files is empty, we can remove it from the session
					$session->remove($this, $sessionKey); 
				}
			}
		}

		return $isTemp;
	}

	public function getPrimaryFlysystem()
	{
		return $this->field->flysystem->getFilesystem($this->field->adapter);
	}

}
