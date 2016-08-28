<?php namespace ProcessWire;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

class UrlPlugin implements PluginInterface
{
    protected $filesystem;
    protected $callback;

    public function __construct(callable $callback)
    {
    	$this->callback = $callback;
    }

    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getMethod()
    {
        return 'url';
    }

    public function handle($path = null)
    {
        if(!$this->filesystem->has($path)) return '';
        return call_user_func($this->callback, $path);
    }
}
