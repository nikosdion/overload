<?php
/**
 * @package       overload
 * @copyright (c) 2011-2020 Nicholas K. Dionysopoulos
 * @license       GNU General Public License version 3 or later
 */

use Faker\Provider\Biased;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Table\Content as ContentTable;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

// region Composer autoloader
/** @var \Composer\Autoload\ClassLoader $autoloader */
$autoloader = require_once(__DIR__ . '/vendor/autoload.php');

if (!is_object($autoloader))
{
	die('Please run composer install in the Overload working directory before running this script.');
}

$autoloader->addPsr4('Overload\\', __DIR__ . '/Overload');
// endregion

// region Boilerplate
define('_JEXEC', 1);
define('JDEBUG', 0);

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

ini_set('display_errors', 1);
error_reporting(E_ALL);

class OverloadCLI extends OverloadApplicationCLI
{
	/**
	 * Faker's generator
	 *
	 * @var   \Faker\Generator
	 * @since 2.0.0
	 */
	private $faker;

	/**
	 * User IDs that can create categories.
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	private $categoryCreators;

	/**
	 * User IDs that can create articles
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	private $articleCreators;

	/**
	 * The main entry point of the application
	 *
	 * @return  void
	 * @since   2.0.0
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

		// Pretend that a Super User is logged in
		$suIDs     = $this->getSuperUsers();
		$superUser = \Joomla\CMS\User\User::getInstance($suIDs[0]);
		Factory::getSession()->set('user', $superUser);

		if (empty($rootCategory))
		{
			if ($catCount == 0)
			{
				$this->out('You need to use --root-catid when setting --categories-count=0');

				$this->close(1);
			}

			// Get the categories root
			$rootCategory = $this->getCategoriesRoot();
		}
		else
		{
			// Verify the $rootCategory or fail early
			$this->verifyCategory($rootCategory);
		}

		$this->out(sprintf("Articles and categories creation using category node %d as root.", $rootCategory));

		if ($catDelete && ($catCount > 0))
		{
			$this->out("Deleting existing categories");

			// Find existing categories under the root
			$catIDs = $this->getAllChildrenCategoryIDs($rootCategory);
			$this->out(sprintf("  Found %d categories to delete", count($catIDs)));

			// For each category: delete its articles and delete the category itself
			foreach ($catIDs as $catId)
			{
				$this->out(sprintf("  Deleting category %d", $catId));
				$this->deleteCategory($catId);
			}
		}

		$catIDs = [$rootCategory];

		// Create categories, $catLevels levels deep. Store IDs in $catIDs.
		if ($catCount > 0)
		{
			$this->out('Creating categories');
			$catIDs                 = [];
			$previousLevelIDs       = [$rootCategory];
			$this->categoryCreators = $this->getGroupCreators();

			for ($i = 0; $i < $catLevels; $i++)
			{
				$this->out(sprintf('  Creating categories %d level(s) from the root', $i + 1));
				$thisLevelIDs   = [];
				$createCatCount = $catCount;

				foreach ($previousLevelIDs as $parentId)
				{
					if ($catRandomize)
					{
						$createCatCount = $this->faker->biasedNumberBetween(1, $catCount, [
							Biased::class, 'linearHigh',
						]);
					}

					$this->out(sprintf('    %d categories under root ID %d', $createCatCount, $parentId));

					for ($j = 0; $j < $createCatCount; $j++)
					{
						$thisLevelIDs[] = $this->createCategory($parentId);
					}
				}

				$catIDs           = array_merge($catIDs, $thisLevelIDs);
				$previousLevelIDs = $thisLevelIDs;
			}
		}

		$this->out(sprintf('We have a total of %d categories to create articles in.', count($catIDs)));

		foreach ($catIDs as $catId)
		{
			// Find out which users can create articles in this category
			$this->articleCreators = $this->getCategoryAuthors($catId);

			// Delete articles unless $articlesDelete is false
			if ($articlesDelete)
			{
				$this->out(sprintf('  Deleting old articles from category %d', $catId));
				$this->deleteArticlesInCategory($catId);
			}

			// Create articles
			$createArticleCount = $articlesCount;

			if ($articlesRandomize)
			{
				$createArticleCount = $this->faker->biasedNumberBetween(1, $articlesCount, [
					Biased::class, 'linearHigh',
				]);
			}

			$this->out(sprintf('  Creating %d articles for category %d', $createArticleCount, $catId));

			for ($j = 0; $j < $createArticleCount; $j++)
			{
				$this->createArticle($catId);
			}
		}
	}

	/**
	 * Returns the site Router object.
	 *
	 * @param   string|null  $name     The name of the application.
	 * @param   array        $options  An optional associative array of configuration settings.
	 *
	 * @return  Router|null  A JRouter object
	 * @since   2.0.0
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
	 * @return  AbstractMenu|null
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function getMenu($name = null, $options = []): ?AbstractMenu
	{
		return AbstractMenu::getInstance($name, $options);
	}

	public function isClient($client)
	{
		return $client === 'administrator';
	}

	public function getClientId(): int
	{
		return 1;
	}

	/**
	 * Initializes the site routing under CLI
	 *
	 * @param   string  $siteURL  The URL to the site
	 *
	 * @throws  ReflectionException
	 * @since   2.0.0
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

		/**
		 * Set up the SEF mode in the router.
		 *
		 * Only applicable on Joomla 3. The site router in Joomla 4 sets itself up automatically.
		 */
		if (version_compare(JVERSION, '3.9.9999', 'le'))
		{
			$this->getRouter()->setMode($this->get('sef', 0));
		}

	}

	/**
	 * Show the help text
	 *
	 * @since   2.0.0
	 */
	private function showHelp(): void
	{
		echo file_get_contents(__DIR__ . '/help.txt');

		$this->close();
	}

	/**
	 * Create a category and return its ID or NULL if creation failed
	 *
	 * @param   int  $parent_id  Parent category ID
	 *
	 * @return  int|null  Created category ID or NULL if creation failed
	 *
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function createCategory(int $parent_id = 1): ?int
	{
		$title = $this->faker->sentence(8);
		$alias = ApplicationHelper::stringURLSafe($title);
		$uid   = $this->faker->randomElement($this->categoryCreators);

		/** @var CategoriesModelCategory $model */
		$model  = BaseDatabaseModel::getInstance('Category', 'CategoriesModel');
		$parent = $model->getItem($parent_id);

		$data = [
			'parent_id'       => $parent_id,
			'level'           => $parent->level + 1,
			'extension'       => 'com_content',
			'title'           => $title,
			'alias'           => $alias,
			'description'     => $this->getRandomParagraphs(3, true),
			'access'          => 1,
			'params'          => ['target' => '', 'image' => ''],
			'metadata'        => ['page_title' => '', 'author' => '', 'robots' => '', 'tags' => ''],
			'hits'            => 0,
			'language'        => '*',
			'associations'    => [],
			'published'       => 1,
			'created_user_id' => $uid,
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

	/**
	 * Creates an article in the specified category
	 *
	 * @param   int  $cat_id  The category to create the article in
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function createArticle($cat_id = 1): void
	{
		$title = $this->faker->sentence(8);
		$alias = ApplicationHelper::stringURLSafe($title);

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
			'created_by'       => $this->faker->randomElement($this->articleCreators),
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
	 * @since   2.0.0
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

	/**
	 * Get the ID of the root of all categories.
	 *
	 * @return  int
	 *
	 * @since   2.0.0
	 */
	private function getCategoriesRoot(): int
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__categories'))
			->where($db->qn('parent_id') . ' = ' . $db->q(0))
			->where($db->qn('level') . ' = ' . $db->q(0));

		return $db->setQuery($query)->loadResult() ?? 0;
	}

	/**
	 * Verify a category exists and belongs to com_content
	 *
	 * @param   int  $catID  The category ID to check
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function verifyCategory(int $catID): void
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__categories'))
			->where($db->qn('extension') . ' = ' . $db->q('com_content'))
			->where($db->qn('parent_id') . ' > ' . $db->q(0))
			->where($db->qn('level') . ' > ' . $db->q(0))
			->where($db->qn('id') . ' = ' . $db->q($catID));

		if ($db->setQuery($query)->loadResult() != $catID)
		{
			throw new RuntimeException(sprintf('Category ID %d was not found or does not belong to Joomla articles (com_content).', $catID));
		}
	}

	/**
	 * Get all the content categories that are children (infinite levels deep) of the root category.
	 *
	 * @param   int  $catID  Root category ID
	 *
	 * @return  array  Children category IDs, ordered from leaf nodes down (leaves first, immediate root children last)
	 *
	 * @since   2.0.0
	 */
	private function getAllChildrenCategoryIDs(int $catID): array
	{
		$db = Factory::getDbo();

		// First, I need the lft and rgt of my root category
		$query    = $db->getQuery(true)
			->select([
				$db->qn('lft'),
				$db->qn('rgt'),
			])
			->from($db->qn('#__categories'))
			->where($db->qn('id') . ' = ' . $db->q($catID));
		$rootInfo = $db->setQuery($query)->loadAssoc();

		if (empty($rootInfo))
		{
			throw new RuntimeException(sprintf("Could not retrieve information for category %d", $catID));
		}

		/**
		 * Now, I can find the IDs of the subtree nodes.
		 *
		 * Make sure to filter for extension because the root node contains everything, not just com_content.
		 *
		 * Categories are returned in descending level order. This way the category nuking will go from the leaf nodes
		 * towards the root nodes. It wouldn't work the other way around since we'd be trying to delete a non-empty
		 * category.
		 */
		$query = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__categories'))
			->where($db->qn('extension') . ' = ' . $db->q('com_content'))
			->where($db->qn('lft') . ' > ' . $db->q($rootInfo['lft']))
			->where($db->qn('rgt') . ' < ' . $db->q($rootInfo['rgt']))
			->order($db->qn('level') . ' DESC');

		return $db->setQuery($query)->loadColumn() ?? [];
	}

	/**
	 * Deletes a category and all of its articles
	 *
	 * @param   int  $catID  THe category to delete
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function deleteCategory(int $catID): void
	{
		// Delete all of the category articles.
		$this->deleteArticlesInCategory($catID);

		// Delete the category itself.
		/** @var CategoriesTableCategory $table */
		$table = Table::getInstance('Category', 'CategoriesTable');

		/** @var CategoriesModelCategory $model */
		$model = BaseDatabaseModel::getInstance('Category', 'CategoriesModel');
		$pks   = [$catID];

		// Joomla requires me to trash before deleting
		$table->publish($pks, -2);

		if (!$model->delete($pks))
		{
			throw new RuntimeException($model->getError());
		}
	}

	/**
	 * Delete all articles in a category
	 *
	 * @param   int  $catID  The category ID the articles of which are going to be deleted
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function deleteArticlesInCategory(int $catID): void
	{
		$db         = Factory::getDbo();
		$query      = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__content'))
			->where($db->qn('catid') . ' = ' . $db->q($catID));
		$articleIDs = $db->setQuery($query)->loadColumn() ?? [];

		if (empty($articleIDs))
		{
			return;
		}

		/** @var ContentModelArticle $model */
		$model = BaseDatabaseModel::getInstance('Article', 'ContentModel');
		/** @var ContentTable $table */
		$table = ContentTable::getInstance('Content');

		// We must trash the articles before deleting them because that's the One True Joomla! Way
		$table->publish($articleIDs, -2);

		if (!$model->delete($articleIDs))
		{
			throw new RuntimeException($model->getError());
		}
	}

	/**
	 * Return all user group IDs known to Joomla
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	private function getAllUserGroups(): array
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__usergroups'));

		return $db->setQuery($query)->loadColumn() ?? [];
	}

	/**
	 * Returns the user IDs which can create groups
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	private function getGroupCreators(): array
	{
		$authorGroups = array_filter($this->getAllUserGroups(), function ($gid) {
			return Access::checkGroup($gid, 'core.create') ||
				Access::checkGroup($gid, 'core.admin') ||
				Access::checkGroup($gid, 'core.create', 'com_categories');
		});

		$users = [];

		foreach ($authorGroups as $gid)
		{
			$users = array_merge($users, Access::getUsersByGroup($gid));
		}

		return $users;
	}

	/**
	 * Returns the user IDs of Super Users
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	private function getSuperUsers(): array
	{
		$authorGroups = array_filter($this->getAllUserGroups(), function ($gid) {
			return Access::checkGroup($gid, 'core.admin');
		});

		$users = [];

		foreach ($authorGroups as $gid)
		{
			$users = array_merge($users, Access::getUsersByGroup($gid));
		}

		return $users;
	}

	/**
	 * Returns all the users who have core.create privileges for the given category.
	 *
	 * @param   int  $catId
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	private function getCategoryAuthors(int $catId): array
	{
		Access::preload('com_content.category.' . $catId);

		$authorGroups = array_filter($this->getAllUserGroups(), function ($gid) use ($catId) {
			return Access::checkGroup($gid, 'core.create') ||
				Access::checkGroup($gid, 'core.admin') ||
				Access::checkGroup($gid, 'core.create', 'com_content.category.' . $catId);
		});

		$users = [];

		foreach ($authorGroups as $gid)
		{
			$users = array_merge($users, Access::getUsersByGroup($gid));
		}

		return $users;
	}
}

OverloadApplicationCLI::getInstance('OverloadCLI')->execute();