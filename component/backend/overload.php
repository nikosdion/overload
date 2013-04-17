<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 * 
 * Overload - Mass content creator for testing purposes
 * Copyright (C) 2011  Nicholas K. Dionysopoulos
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

defined('_JEXEC') or die();

// Load FOF
if (file_exists(JPATH_LIBRARIES.'/fof/include.php')) {
	include_once JPATH_LIBRARIES.'/fof/include.php';
} else {
	include_once JPATH_COMPONENT_ADMINISTRATOR.'/fof/include.php';
}
if(!defined('FOF_INCLUDED')) {
	JFactory::getApplication()->enqueueMessage('Your Overload installation is broken; please re-install. Alternatively, extract the installation archive and copy the fof directory inside your site\'s libraries directory.', 'error');
	return false;
}

// Dispatch
FOFDispatcher::getTmpInstance('com_overload')->dispatch();
