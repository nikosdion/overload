<?php
/**
 * @package       overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 3 or later
 */

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

// region Composer autoloader
/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once(__DIR__ . '/vendor/autoload.php');

if (!is_object($autoloader))
{
	die('Please run composer install in the Overload working directory before running this script.');
}

$autoloader->addPsr4('\\Overload\\', __DIR__ . '/Overload');
// endregion

// region Boilerplate
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $curdir)
{
	if (file_exists($curdir . '/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/defines.php';

		break;
	}

	if (file_exists($curdir . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be executed from the cli folder of your site.');

require_once __DIR__ . '/Overload/Cli/Application.php';

// endregion

class OverloadCLI extends OverloadApplicationCLI
{
	/**
	 * Faker's generator
	 *
	 * @var \Faker\Generator
	 */
	private $faker;

	/**
	 * The main entry point of the application
	 *
	 * @return void
	 */
	public function doExecute(): void
	{
		// Show help if necessary
		if ($this->input->getBool('help', false))
		{
			$this->showHelp();
		}

		// Read the configuration from the command line
		$siteURL           = $this->input->get('site-url', 'https://www.example.com');
		$rootCategory      = $this->input->getInt('root-catid', 0);
		$catLevels         = $this->input->getInt('categories-levels', 4);
		$catCount          = $this->input->getInt('categories-count', 3);
		$catDelete         = !$this->input->getBool('categories-nozap', false);
		$catRandomize      = $this->input->getBool('categories-randomize', false);
		$articlesCount     = $this->input->getInt('articles-count', 10);
		$articlesDelete    = !$this->input->getBool('articles-nozap', false);
		$articlesRandomize = $this->input->getBool('articles-randomize', false);

		// Initialize CLI routing
		$this->initCliRouting($siteURL);

		// Create the Faker object
		$this->faker = Faker\Factory::create();

		// Tell Joomla where to find models and tables
		BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/models');
		BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/models');
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/tables');
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');

		if (empty($rootCategory))
		{
			if ($catCount == 0)
			{
				$this->out('You need to use --root-catid when setting --categories-count=0');

				$this->close(1);
			}

			// TODO Get the root of com_content categories
		}
		else
		{
			// TODO Verify the $rootCategory or fail early
		}

		if ($catDelete && ($catCount > 0))
		{
			// TODO Find existing categories under the root
			// TODO For each category: delete its articles and delete the category itself
		}

		if ($catCount > 0)
		{
			// TODO Create categories, $catLevels levels deep. Store IDs in $catIDs.
		}
		else
		{
			$catIDs = [$rootCategory];
		}

		// TODO Foreach $catIDs
			// TODO Find out which users can create articles in this category
			// TODO Delete articles unless $articlesDelete is false
			// TODO Create articles
	}

	/**
	 * Returns the site Router object.
	 *
	 * @param   string|null  $name     The name of the application.
	 * @param   array        $options  An optional associative array of configuration settings.
	 *
	 * @return  Router|null  A JRouter object
	 */
	public function getRouter($name = null, array $options = []): ?Router
	{
		try
		{
			return Router::getInstance('site', $options);
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	/**
	 * Returns a Joomla menu object.
	 *
	 * @param   string|null  $name
	 * @param   array        $options
	 *
	 * @return AbstractMenu|null
	 * @throws Exception
	 */
	public function getMenu($name = null, $options = []): ?AbstractMenu
	{
		return AbstractMenu::getInstance($name, $options);
	}

	/**
	 * Initializes the site routing under CLI
	 *
	 * @param   string  $siteURL  The URL to the site
	 *
	 * @throws  ReflectionException
	 */
	private function initCliRouting(string $siteURL = 'https://www.example.com')
	{
		// Set up the base site URL in JUri
		$uri                    = Uri::getInstance($siteURL);
		$_SERVER['HTTP_HOST']   = $uri->toString(['host', 'port']);
		$_SERVER['REQUEST_URI'] = $uri->getPath();

		$refClass     = new ReflectionClass(Uri::class);
		$refInstances = $refClass->getProperty('instances');
		$refInstances->setAccessible(true);
		$instances           = $refInstances->getValue();
		$instances['SERVER'] = $uri;
		$refInstances->setValue($instances);

		$base = [
			'prefix' => $uri->toString(['scheme', 'host', 'port']),
			'path'   => rtrim($uri->toString(['path']), '/\\'),
		];

		$refBase = $refClass->getProperty('base');
		$refBase->setAccessible(true);
		$refBase->setValue($base);

		// Set up the SEF mode in the router
		$this->getRouter()->setMode($this->get('sef', 0));
	}

	private function showHelp(): void
	{
		echo file_get_contents(__DIR__ . '/help.txt');

		$this->close();
	}

	private function createCategory(int $parent_id = 1, int $level = 1): ?int
	{
		$title = $this->faker->sentence(8);
		$alias = ApplicationHelper::stringURLSafe($title);

		// TODO I should get the level from the parent category and simply add 1 to it.

		$data = [
			'parent_id'    => $parent_id,
			'level'        => $level,
			'extension'    => 'com_content',
			'title'        => $title,
			'alias'        => $alias,
			'description'  => $this->getRandomParagraphs(3, true),
			'access'       => 1,
			'params'       => ['target' => '', 'image' => ''],
			'metadata'     => ['page_title' => '', 'author' => '', 'robots' => '', 'tags' => ''],
			'hits'         => 0,
			'language'     => '*',
			'associations' => [],
			'published'    => 1,
			// TODO Maybe randomize the author here?
		];

		// Save the category
		/** @var CategoriesModelCategory $model */
		$model  = BaseDatabaseModel::getInstance('Category', 'CategoriesModel');
		$result = $model->save($data);

		// If the save succeeded return the numeric category ID
		if ($result !== false)
		{
			return $model->getState($model->getName() . '.id');
		}

		// Let's try to load a category of the same alias
		$db    = Factory::getDbo();
		$query =
			$db->getQuery(true)
				->select('id')
				->from($db->qn('#__categories'))
				->where($db->qn('alias') . ' = ' . $db->q($alias));
		$db->setQuery($query);
		$id = $db->loadResult() ?? 0;

		// Nope. No dice. Return null.
		if (!$id)
		{
			return null;
		}

		// Enable an existing category
		$cat = $model->getItem($id);

		if (!$cat->published)
		{
			$cat->published = 1;
		}

		$cat = (array) $cat;
		$model->save($cat);

		return $id;
	}

	private function createArticle($cat_id = 1)
	{
		$title = $this->faker->sentence(8);
		$alias = ApplicationHelper::stringURLSafe($title);

		// TODO Set up the created_by based on the users who can create articles in this category

		$data = [
			'id'               => 0,
			'title'            => $title,
			'alias'            => $alias,
			'introtext'        => $this->getRandomParagraphs(1, false),
			'fulltext'         => $this->getRandomParagraphs(6, true),
			'state'            => 1,
			'sectionid'        => 0,
			'mask'             => 0,
			'catid'            => $cat_id,
			'created'          => (new Date($this->faker->dateTimeBetween('-5 years', 'now')->getTimestamp()))->toSql(),
			'created_by_alias' => $this->faker->name,
			'attribs'          => [
				"show_title"           => "",
				"link_titles"          => "",
				"show_intro"           => "",
				"show_category"        => "",
				"link_category"        => "",
				"show_parent_category" => "",
				"link_parent_category" => "",
				"show_author"          => "",
				"link_author"          => "",
				"show_create_date"     => "",
				"show_modify_date"     => "",
				"show_publish_date"    => "",
				"show_item_navigation" => "",
				"show_icons"           => "",
				"show_print_icon"      => "",
				"show_email_icon"      => "",
				"show_vote"            => "",
				"show_hits"            => "",
				"show_noauth"          => "",
				"alternative_readmore" => "",
				"article_layout"       => "",
			],
			'version'          => 1,
			'parentid'         => 0,
			'ordering'         => 0,
			'metakey'          => '',
			'metadesc'         => '',
			'access'           => 1,
			'hits'             => 0,
			'featured'         => 0,
			'language'         => '*',
			'associations'     => [],
			'metadata'         => '{"tags":[]}',
		];

		/** @var ContentModelArticle $model */
		$model  = BaseDatabaseModel::getInstance('Article', 'ContentModel');
		$result = $model->save($data);
	}

	/**
	 * Get a number of random paragraphs of HTML text
	 *
	 * @param   int   $howMany      How many paragraphs do you want
	 * @param   bool  $randomCount  Should I randomize the number of paragraphs, max $howMany?
	 *
	 * @return  string  The HTML string of your random paragraphs
	 */
	private function getRandomParagraphs(int $howMany, bool $randomCount = true): string
	{
		if ($randomCount)
		{
			$howMany = $this->faker->numberBetween(1, $howMany);
		}

		return implode(',', array_map(function ($p) {
			return "<p>" . $p . "</p>";
		}, $this->faker->paragraphs($howMany, false)));
	}
}

OverloadApplicationCLI::getInstance('OverloadCLI')->execute();