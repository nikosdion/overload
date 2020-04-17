<?php
/**
 * @package       overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 3 or later
 */

namespace Overload\Cli;

defined('_JEXEC') or die;

/**
 * Intercept calls to PHP functions.
 *
 * Based on the Session package of Aura for PHP – https://github.com/auraphp/Aura.Session
 *
 * @method  function_exists(string $function)
 * @method  mcrypt_list_algorithms()
 * @method  hash_algos()
 *
 * @since   2.0.0
 */
class Phpfunc
{
	/**
	 * Magic call to intercept any function pass to it.
	 *
	 * @param   string  $func  The function to call.
	 *
	 * @param   array   $args  Arguments passed to the function.
	 *
	 * @return mixed The result of the function call.
	 *
	 * @since   2.0.0
	 */
	public function __call($func, $args)
	{
		return call_user_func_array($func, $args);
	}
}
