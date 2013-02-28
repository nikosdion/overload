<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 */

defined('_JEXEC') or die();

class OverloadControllerStart extends FOFController
{
	public function execute($task) {
		if(!in_array($task, array('cancel','save','apply'))) $task = 'edit';
		parent::execute($task);
	}

	public function edit()
	{
		parent::edit();
		JRequest::setVar('hidemainmenu', false);
	}
}