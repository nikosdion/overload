<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 */

// No direct access
defined('_JEXEC') or die;

class OverloadToolbar extends FOFToolbar
{
	private function setToolbarTitle()
	{
		$subtitle_key = FOFInput::getCmd('option', 'com_overload', $this->input).
						'_TITLE_'.strtoupper(FOFInput::getCmd('view', '', $this->input));
	
		JToolBarHelper::title(
						JText::_( FOFInput::getCmd('option', 'com_overload', $this->input)).
						' &ndash; <small>'.JText::_($subtitle_key).'</small>',
						'generic.png');
	}
	
	public function onStartsEdit()
    {
		$this->setToolbarTitle();
	}
}