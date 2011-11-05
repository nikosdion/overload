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

$c = JRequest::getCmd('view','start');
JRequest::setVar('view', $c);
$path = JPATH_COMPONENT_ADMINISTRATOR.DS.'controllers'.DS.$c.'.php';
if(JFile::exists($path)) {
	require_once($path);
} else {
	JError::raiseError('500','Unknown controller '.$c);
}

jimport('joomla.utilities.string');
$controller_class = 'OverloadController'.ucfirst($c);
$controller = new $controller_class();

$controller->execute(JRequest::getCmd('task','display'));

$controller->redirect();