<?php
/**
 * @package       overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 3 or later
 */

// Do not put the JEXEC or die check on this file

use Joomla\CMS\Application\CliApplication;
use Joomla\CMS\Application\ExtensionNamespaceMapper;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;
use Joomla\Session\SessionInterface;
use Overload\Cli\Traits\CGIModeAware;
use Overload\Cli\Traits\CustomOptionsAware;
use Overload\Cli\Traits\JoomlaConfigAware;
use Overload\Cli\Traits\MemStatsAware;
use Overload\Cli\Traits\TimeAgoAware;

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

// Load the Framework (J4 beta 1 and later) or CMS import file (J4 a12 and lower)
$cmsImportFilePath = JPATH_BASE . '/includes/framework.php';
$cmsImportFilePathOld = JPATH_LIBRARIES . '/cms.php';

if (@file_exists($cmsImportFilePath))
{
	@include_once $cmsImportFilePath;

	// Boot the DI container
	$container = \Joomla\CMS\Factory::getContainer();

	/*
	 * Alias the session service keys to the CLI session service as that is the primary session backend for this application
	 *
	 * In addition to aliasing "common" service keys, we also create aliases for the PHP classes to ensure autowiring objects
	 * is supported.  This includes aliases for aliased class names, and the keys for alised class names should be considered
	 * deprecated to be removed when the class name alias is removed as well.
	 */
	$container->alias('session', 'session.cli')
		->alias('JSession', 'session.cli')
		->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
}
elseif (@file_exists($cmsImportFilePathOld))
{
	@include_once $cmsImportFilePathOld;
}

/**
 * Base class for a Joomla! command line application. Adapted from JCli / JApplicationCli
 *
 * @since   2.0.0
 */
abstract class OverloadCliApplicationJoomla4 extends CliApplication
{
	use ExtensionNamespaceMapper;

	use CGIModeAware, CustomOptionsAware, JoomlaConfigAware, MemStatsAware, TimeAgoAware;

	private $allowedToClose = false;

	/** @inheritDoc */
	public static function getInstance($name = null)
	{
		$instance = parent::getInstance($name);

		Factory::$application = $instance;

		return $instance;
	}

	/** @inheritDoc */
	public function __construct(\Joomla\Input\Input $input = null, Registry $config = null, \Joomla\CMS\Application\CLI\CliOutput $output = null, \Joomla\CMS\Application\CLI\CliInput $cliInput = null, \Joomla\Event\DispatcherInterface $dispatcher = null, \Joomla\DI\Container $container = null)
	{
		// Some servers only provide a CGI executable. While not ideal for running CLI applications we can make do.
		$this->detectAndWorkAroundCGIMode();

		// We need to tell Joomla to register its default namespace conventions
		$this->createExtensionNamespaceMap();

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

	/**
	 * Method to get the application session object.
	 *
	 * @return  SessionInterface  The session object
	 *
	 * @since   2.0.0
	 */
	public function getSession()
	{
		return $this->getContainer()->get('session.cli');
	}

	/**
	 * Gets a user state.
	 *
	 * @param   string  $key      The path of the state.
	 * @param   mixed   $default  Optional default value, returned if the internal value is null.
	 *
	 * @return  mixed  The user state or null.
	 *
	 * @since   3.2
	 */
	public function getUserState($key, $default = null)
	{
		$registry = $this->getSession()->get('registry');

		if ($registry !== null)
		{
			return $registry->get($key, $default);
		}

		return $default;
	}

	/**
	 * Get the application identity.
	 *
	 * @return  User
	 *
	 * @since   4.0.0
	 */
	public function getIdentity()
	{
		$dummyUser = new Joomla\CMS\User\User();

		return Factory::getSession()->get('user', $dummyUser);
	}

}
