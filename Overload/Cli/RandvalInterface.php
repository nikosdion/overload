<?php
/**
 * @package   overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later
 */

namespace Overload\Cli;

// Protect from unauthorized access
defined('_JEXEC') or die();

interface RandvalInterface
{
	/**
	 *
	 * Returns a cryptographically secure random value.
	 *
	 * @return string
	 *
	 */
	public function generate();
}
