<?php
/**
 * @package   overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later
 */

// Do not put the JEXEC or die check on this file

use Overload\Cli\Traits\CGIModeAware;
use Overload\Cli\Traits\CustomOptionsAware;
use Overload\Cli\Traits\JoomlaConfigAware;
use Overload\Cli\Traits\MemStatsAware;
use Overload\Cli\Traits\TimeAgoAware;
use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Factory;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;
use Joomla\Session\SessionInterface;

/**
 * Load the legacy Joomla! include files
 *
 * Despite Joomla complaining about it with an E_DEPRECATED notice, if you use bootstrap.php instead of
 * import.legacy.php you get an HTML error page (yes, under CLI!) which is kinda daft.
 */
if (function_exists('error_reporting'))
{
	$oldErrorReporting = @error_reporting(E_ERROR | E_NOTICE | E_DEPRECATED);
}

include_once JPATH_LIBRARIES . '/import.legacy.php';

if (function_exists('error_reporting'))
{
	@error_reporting($oldErrorReporting);
}


// Load the CMS import file if it exists (newer Joomla! 3 versions and Joomla! 4)
$cmsImportFilePath = JPATH_LIBRARIES . '/cms.php';

if (@file_exists($cmsImportFilePath))
{
	@include_once $cmsImportFilePath;
}

/**
 * Base class for a Joomla! command line application. Adapted from JCli / JApplicationCli
 */
abstract class OverloadCliApplicationJoomla4 extends CliApplication
{
	use CGIModeAware, CustomOptionsAware, JoomlaConfigAware, MemStatsAware, TimeAgoAware;

	private $allowedToClose = false;

	public static function getInstance($name = null)
	{
		$instance = parent::getInstance($name);

		Factory::$application = $instance;

		return $instance;
	}

	public function __construct(\Joomla\Input\Input $input = null, Registry $config = null, \Joomla\CMS\Application\CLI\CliOutput $output = null, \Joomla\CMS\Application\CLI\CliInput $cliInput = null, \Joomla\Event\DispatcherInterface $dispatcher = null, \Joomla\DI\Container $container = null)
	{
		// Some servers only provide a CGI executable. While not ideal for running CLI applications we can make do.
		$this->detectAndWorkAroundCGIMode();

		// Initialize custom options handling which is a bit more straightforward than Input\Cli.
		$this->initialiseCustomOptions();

		// Default configuration: Joomla Global Configuration
		if (empty($config))
		{
			$config = new Registry($this->fetchConfigurationData());
		}

		if (empty($dispatcher))
		{
			$dispatcher = new Dispatcher();
		}

		parent::__construct($input, $config, $output, $cliInput, $dispatcher, $container);

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
	 * @codeCoverageIgnore
	 * @since   1.0
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
	 * @since   4.0.0
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
	 * @since   4.0.0
	 */
	public function getMenu($name = null, $options = [])
	{
		return null;
	}

	/**
	 * Method to get the application session object.
	 *
	 * @return  SessionInterface  The session object
	 *
	 * @since   4.0.0
	 */
	public function getSession()
	{
		return $this->getContainer()->get('session.cli');
	}

}
