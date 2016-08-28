<?php namespace ProcessWire;
/**
 * Flysystem FileField (0.0.1)
 * 
 * @author Benjamin Milde
 * 
 * ProcessWire 2.x
 * Copyright (C) 2011 by Ryan Cramer
 * Licensed under GNU/GPL v2, see LICENSE.TXT
 * 
 * http://www.processwire.com
 * http://www.ryancramer.com
 * 
 */

abstract class FlysystemAbstract extends Wire implements Module {

	/**
	 * @return string Unique key for the adapter
	 */
	abstract protected function key();

	/**
	 * @return string Label for the adapter
	 */
	abstract protected function label();

	/**
	 * @param InputfieldFieldset $fieldset
	 * @param Field $field
	 * @return void
	 */
	abstract protected function getConfigFields(InputfieldFieldset $fieldset, Field $field);

	/**
	 * @param Field $field
	 * @return \League\Flysystem\AdapterInterface
	 */
	abstract public function constructAdapter(Field $field);

	/**
	 * @param Field $field
	 * @return callable Callback which does receive a file path and should return an url
	 */
	public function urlCallback(Field $field) {
		return function(){ return '#'; };
	}

	public function init()
	{
		$this->addHookAfter('FieldtypeFileFlysystem::getAdapters', $this, 'addAdapter');
	}

	protected function addAdapter(HookEvent $event)
	{
		$adapters = $event->return;
		$adapters[$this->key()] = [
				'label' => $this->label(),
				'managerPrefix' => $this->key(),
				'adapterSettings' => function(Field $field) {
					$fieldset = $this->modules->get('InputfieldFieldset');
					$fieldset->label = $this->label();
					$this->getConfigFields($fieldset, $field);
					return $fieldset;
				},
				'constructAdapter' => [$this, 'constructAdapter'],
				'urlCallback' => [$this, 'urlCallback']
		];
		$event->return = $adapters;
	}

}