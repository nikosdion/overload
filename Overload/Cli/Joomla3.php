<?php
/**
 * @package       overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 3 or later
 */

// Do not put the JEXEC or die check on this file

use Overload\Cli\CliSessionHandler;
use Overload\Cli\Traits\CGIModeAware;
use Overload\Cli\Traits\CustomOptionsAware;
use Overload\Cli\Traits\JoomlaConfigAware;
use Overload\Cli\Traits\MemStatsAware;
use Overload\Cli\Traits\TimeAgoAware;
use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Input\Cli;

// Load the legacy Joomla! include files (Joomla! 3 only)
include_once JPATH_LIBRARIES . '/import.legacy.php';

// Load the CMS import file if it exists (newer Joomla! 3 versions and Joomla! 4)
$cmsImportFilePath = JPATH_LIBRARIES . '/cms.php';

if (@file_exists($cmsImportFilePath))
{
	@include_once $cmsImportFilePath;
}

// Load requirements for various versions of Joomla!. This should NOT be required since circa Joomla! 3.7.
if (version_compare("$joomlaMajorVersion.$joomlaMinorVersion", '3.8', 'lt'))
{
	JLoader::import('joomla.base.object');
	JLoader::import('joomla.application.application');
	JLoader::import('joomla.application.applicationexception');
	JLoader::import('joomla.log.log');
	JLoader::import('joomla.registry.registry');
	JLoader::import('joomla.filter.input');
	JLoader::import('joomla.filter.filterinput');
	JLoader::import('joomla.factory');
}

/**
 * Base class for a Joomla! command line application. Adapted from JCli / JApplicationCli
 *
 * @since   2.0.0
 */
abstract class OverloadCliApplicationJoomla3 extends CliApplication
{
	use CGIModeAware, CustomOptionsAware, JoomlaConfigAware, MemStatsAware, TimeAgoAware;

	private $allowedToClose = false;

	/** @inheritDoc */
	public static function getInstance($name = null)
	{
		// Create a CLI-specific session
		JFactory::$session = JSession::getInstance('none', [
			'expire' => 84400,
		], new CliSessionHandler());

		$instance = parent::getInstance($name);

		JFactory::$application = $instance;

		return $instance;
	}

	/** @inheritDoc */
	public function __construct(Cli $input = null, \Joomla\Registry\Registry $config = null, \JEventDispatcher $dispatcher = null)
	{
		// Some servers only provide a CGI executable. While not ideal for running CLI applications we can make do.
		$this->detectAndWorkAroundCGIMode();

		// Initialize custom options handling which is a bit more straightforward than Input\Cli.
		$this->initialiseCustomOptions();

		parent::__construct($input, $config, $dispatcher);

		/**
		 * Allow the application to close.
		 *
		 * This is required to allow CliApplication to execute under CGI mode. The checks performed in the parent
		 * constructor will call close() if the application does not run pure CLI mode. However, some hosts only provide
		 * the PHP CGI binary for executing CLI scripts. While wrong it will work in most cases. By default close() will
		 * do nothing, thereby allowing the parent constructor to call it without a problem. Finally, we set this flag
		 * to true to allow doExecute() to call close() and actually close the application properly. Yeehaw!
		 */
		$this->allowedToClose = true;
	}

	/**
	 * Method to close the application.
	 *
	 * See the constructor for details on why it works the way it works.
	 *
	 * @param   integer  $code  The exit code (optional; default is 0).
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	public function close($code = 0)
	{
		// See the constructor for details
		if (!$this->allowedToClose)
		{
			return;
		}

		exit($code);
	}

	/**
	 * Gets the name of the current running application.
	 *
	 * @return  string  The name of the application.
	 *
	 * @since   2.0.0
	 */
	public function getName()
	{
		return get_class($this);
	}

	/**
	 * Get the menu object.
	 *
	 * @param   string  $name     The application name for the menu
	 * @param   array   $options  An array of options to initialise the menu with
	 *
	 * @return  \Joomla\CMS\Menu\AbstractMenu|null  A AbstractMenu object or null if not set.
	 *
	 * @since   2.0.0
	 */
	public function getMenu($name = null, $options = [])
	{
		return null;
	}
}
