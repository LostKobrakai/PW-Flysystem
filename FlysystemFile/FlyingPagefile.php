<?php namespace ProcessWire;

use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

class FlyingPagefile extends Pagefile {

	/**
	 * Install this Pagefile
	 *
	 * Implies copying the file to the correct location (if not already there), and populating its name.
	 * The given $filename may be local (path) or external (URL). 
	 * 
	 * #pw-hooker
	 *
	 * @param string $filename Full path and filename of file to install, or http/https URL to pull file from.
	 * @throws WireException
	 *
	 */
	protected function ___install($filename) {

		$basename = $this->pagefiles->cleanBasename($filename, true, false, true); 
		$pathInfo = pathinfo($basename); 
		$basename = basename($basename, ".$pathInfo[extension]"); 

		$basenameNoExt = $basename; 
		$basename .= ".$pathInfo[extension]"; 

		// ensure filename is unique
		$cnt = 0; 
		while($this->pagefiles->getPrimaryFlysystem()->has($this->pagefiles->path() . $basename)) {
			$cnt++;
			$basename = "$basenameNoExt-$cnt.$pathInfo[extension]";
		}
		
		$destination = $this->pagefiles->path() . $basename; 
		
		if(strpos($filename, '://') !== false) {
			$tempPath = new WireTempDir($this->className);
			$tempDestination = rtrim($tempPath, '/') . '/' .  ltrim($destination, '/');
			$http = $this->wire(new WireHttp());
			// note: download() method throws excepton on failure
			$http->download($filename, $tempDestination);
			// download was successful

			$filename = $tempDestination;
		}

		if(!is_readable($filename)) 
			throw new WireException("Unable to read: $filename");

		$options = [];
		if(!in_array(NotSupportingVisibilityTrait::class, class_uses($this->pagefiles->getPrimaryFlysystem())))
			$options['visibility'] = $this->field->public ? 'public' : 'private';
		if(!$this->pagefiles->getPrimaryFlysystem()->write($destination, file_get_contents($filename), $options))
			throw new WireException("Unable to create $destination from $filename");
		
		$this->changed('file');
		parent::set('basename', $basename);
	}

	/**
	 * Get a value from this Pagefile
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return mixed Returns null if value does not exist
	 *
	 */
	public function get($key) {
		$value = null; 

		switch($key) {
			case 'URL':
				// nocache url
				$value = $this->url() . '?nc=' . $this->getModifiedTime();
				break;
			case 'modified':
			case 'created':
				$value = parent::get($key); 
				if(empty($value)) {
					$value = $this->getModifiedTime(); 
					parent::set($key, $value); 
				}
				break;
			case 'mtime':
				$value = $this->getModifiedTime(); 
				break;
		}
		if(is_null($value)) return parent::get($key); 
		return $value; 
	}

	function getModifiedTime() {
		return $this->pagefiles->getPrimaryFlysystem()->getTimestamp($this->filename());
	}

	/**
	 * Returns the filesize in number of bytes.
	 *
	 * @return int
	 *
	 */
	public function filesize() {
		return $this->pagefiles->getPrimaryFlysystem()->getSize($this->filename()); 
	}

	/**
	 * Delete the physical file on disk, associated with this Pagefile
	 * 
	 * #pw-internal Public API should use removal methods from the parent Pagefiles. 
	 * 
	 * @return bool True on success, false on fail
	 *
	 */
	public function unlink() {
		if(!strlen($this->basename) || !$this->pagefiles->getPrimaryFlysystem()->has($this->filename)) return true; 
		return $this->pagefiles->getPrimaryFlysystem()->delete($this->filename); 	
	}

	/**
	 * Rename this file 
	 * 
	 * Remember to follow this up with a `$page->save()` for the page that the file lives on. 
	 * 
	 * #pw-group-manipulation
	 * 
 	 * @param string $basename New name to use. Must be just the file basename (no path). 
	 * @return string|bool Returns new name (basename) on success, or boolean false if rename failed.
	 *
	 */
	public function rename($basename) {
		$basename = $this->pagefiles->cleanBasename($basename, true); 
		if($this->pagefiles->getPrimaryFlysystem()->rename($this->filename, $this->pagefiles->path . $basename)) {
			$this->set('basename', $basename); 
			return $this->basename();
		}
		return false; 
	}

	/**
	 * Copy this file to the new specified path
	 * 
	 * #pw-internal
	 *
	 * @param string $path Path (not including basename)
	 * @return mixed result of copy() function
	 *
	 */
	public function copyToPath($path) {
		$result = $this->pagefiles->getPrimaryFlysystem()->copy($this->filename, $path . $this->basename());
		return $result;
	}

	/**
	 * Return the web accessible URL to this Pagefile.
	 * 
	 * ~~~~~
	 * // Example of using the url method/property
	 * foreach($page->files as $file) {
	 *   echo "<li><a href='$file->url'>$file->description</a></li>";
	 * }
	 * ~~~~~
	 * 
	 * #pw-hooks
	 * #pw-common
	 * 
	 * @return string
	 * @see Pagefile:httpUrl()
	 *
	 */
	public function url() {
		return $this->wire('hooks')->isHooked('Pagefile::url()') ? $this->__call('url', array()) : $this->___url();
	}
	
	/**
	 * Hookable version of url() method
	 * 
	 * @return string
	 *
	 */
	protected function ___url() {
		$page = $this->pagefiles->getPage();
		$url = substr($page->httpUrl(), 0, -1 * strlen($page->url())); 
		return str_replace($url, '', $this->httpUrl());
	}
	
	/**
	 * Return the web accessible URL (with scheme and hostname) to this Pagefile.
	 * 
	 * @return string
	 * @see Pagefile::url()
	 *
	 */
	public function ___httpUrl() {
		return $this->pagefiles->getPrimaryFlysystem()->url($this->filename);
	}
}

