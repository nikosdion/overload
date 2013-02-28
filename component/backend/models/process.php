<?php

defined('_JEXEC') or die();

class OverloadModelProcess extends FOFModel
{
	/** @var int Stores the timestamp when the real processing started */
	private $_timerStart = null;
	
	/**
	 * Starts the operation timer, used to safeguard us against server timeouts
	 */
	private function startTimer()
	{
		$this->_timerStart = microtime(true);
	}
	
	/**
	 * Checks if there is enough time to continue processing. This is an
	 * inflexible implementation which assumes that up to 4 seconds per
	 * operation are allowed. While it is very low with regards to common
	 * timeout values, we want to have some decent degree of verbosity.
	 * Increasing it to 10 seconds doesn't make the process considerably faster,
	 * but frustrates the user as he's staring at an apparently stuck page for
	 * ten seconds before seeing anything happening. Not good!
	 * 
	 * @return bool True if we are within our time constraints.
	 */
	private function haveEnoughTime()
	{
		$now = microtime(true);
		if( ($now - $this->_timerStart) >= 4) return false;
		return true;
	}

	/**
	 * Generates a category level mapping, i.e. an array containing a category
	 * hierarchy based on the category and depth preferences.
	 * 
	 * @param type $categories
	 * @param type $depth
	 * @param type $prefix
	 * @return array
	 */
	private function makeLevelmap($categories, $depth, $prefix = '')
	{
		$ret = array();
		$prefix = empty($prefix) ? '' : $prefix.'.';
		for($i = 1; $i <= $categories; $i++) {
			$partial = $i;
			$ret[] = (string)$partial;
			if($depth > 1) {
				$fulls = $this->makeLevelmap($categories, $depth - 1, $partial);
				foreach($fulls as $something) {
					$ret[] = $partial.'.'.$something;
				}
			}
		}
		
		return $ret;
	}
	
	/**
	 * Begins the content overload process
	 * @return bool 
	 */
	public function start()
	{
		$categories = $this->getState('categories');
		$depth = $this->getState('depth');
		$logger = $this->getState('logger');
		
		JLog::add('Calculating total number of categories', JLog::DEBUG);
		
		$totalcats = 0;
		for($i = $depth; $i > 0; $i --) {
			$totalcats += pow($categories, $i);
		}
		
		JLog::add('Creating level map', JLog::DEBUG);
		
		$killme = $this->makeLevelmap($categories, $depth);
		$levelmap = array();
		foreach($killme as $key) {
			$levelmap[$key] = 0;
		}
		
		$this->setState('totalcats', $totalcats);
		$this->setState('donecats', 0);
		$this->setState('level', 0);
		$this->setState('levelmap', $levelmap);
		
		JLog::add('Starting the engines!', JLog::DEBUG);
		
		$this->startTimer();
		$this->makeCategories();
		return $this->process();
	}
	
	/**
	 * Internal function called to persist the model state between successive
	 * AJAX calls.
	 * 
	 */
	private function suspend()
	{
		$logger = $this->getState('logger');
		JLog::add('Saving model state to the session', JLog::DEBUG);
		
		$saveData = array(
			'level'				=> $this->getState('level', 0),
			'levelmap'			=> $this->getState('levelmap', array()),
			'categories'		=> $this->getState('categories'),
			'depth'				=> $this->getState('depth'),
			'articles'			=> $this->getState('articles'),
			'articlesstate'		=> $this->getState('articlesstate'),
			'startfromarticle' 	=> $this->getState('startfromarticle',0),
			'totalcats'			=> $this->getState('totalcats', 0),
			'donecats'			=> $this->getState('donecats', 0)
		);
		
		$saveData = base64_encode(gzcompress(serialize($saveData), 9));
		if(strlen($saveData) > 10240) die("The serialized data is too big for Joomla! 1.6+ to handle in the tiny-weenie session storage: ".strlen($saveData));
		
		JFactory::getSession()->set('savedata', $saveData, 'comoverload');
	}
	
	/**
	 * Resumes the content overloading process
	 * @return type 
	 */
	public function resume()
	{
		$logger = $this->getState('logger');
		
		JLog::add('Loading the model state from the session', JLog::DEBUG);
		
		$saveData = JFactory::getSession()->get('savedata', '', 'comoverload');
		$saveData = unserialize(gzuncompress(base64_decode($saveData)));
		
		if(empty($saveData)) {
			JLog::add('Nothing is saved in the session. Brace yourself, we are crashing!', JLog::ERROR);
			die('Nothing is saved in the session. Brace yourself, we are crashing! ......... KA-BOOM! All passengers dead.');
		}
		
		$this->setState('level', $saveData['level']);
		$this->setState('levelmap', $saveData['levelmap']);
		$this->setState('categories', $saveData['categories']);
		$this->setState('depth', $saveData['depth']);
		$this->setState('articles', $saveData['articles']);
		$this->setState('articlesstate', $saveData['articlesstate']);
		$this->setState('startfromarticle', $saveData['startfromarticle']);
		$this->setState('totalcats', $saveData['totalcats']);
		$this->setState('donecats', $saveData['donecats']);
		
		JLog::add('(Re-)starting the engines!', JLog::DEBUG);
		
		$this->startTimer();
		return $this->process();
	}
	
	/**
	 * The main feature of this model: creating faux articles!
	 * @return type 
	 */
	private function process()
	{
		$logger = $this->getState('logger');
		JLog::add('Entering main processing loop');
		
		$articles = $this->getState('articles');
		$levelmap = $this->getState('levelmap');
		$level = $this->getState('level');
		
		$currentArticle = 0;
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_content/tables');
		
		while($this->haveEnoughTime() && !empty($levelmap))
		{
			if($level == 0) {
				$keys = array_keys($levelmap);
				$level = array_shift($keys);
				JLog::add('Beginning content creation in category '.$level);
				$startFromArticle = 0;
			} else {
				$startFromArticle = $this->getState('startfromarticle', 0);
				JLog::add("Resuming content creation (article #$startFromArticle)", JLog::DEBUG);
			}
			
			$copy = $levelmap;
			$level_id = array_shift($copy);
			
			JLog::add("Level ID $level_id", JLog::DEBUG);
			
			for($currentArticle = $startFromArticle; $currentArticle < $articles; $currentArticle++) {
				if(!$this->haveEnoughTime()) break;
				$this->createArticle_usingModel($level_id, $level, $currentArticle);
			}
			
			if($currentArticle == $articles) {
				JLog::add("Finished processing category", JLog::DEBUG);
				$currentArticle = 0;
				$level = 0;
				array_shift($levelmap);
				$donecats = $this->getState('donecats');
				$donecats++;
				$this->setState('donecats', $donecats);
			}
		}
		
		JLog::add("Updating model state", JLog::DEBUG);
		$this->setState('levelmap', $levelmap);
		$this->setState('level', $level);
		$this->setState('startfromarticle', $currentArticle);
		
		if(empty($levelmap)) {
			JLog::add("We are finished!");
			return true;
		}
		
		$this->suspend();
		return false;
	}
	
	/**
	 * Generates categories based on the hierarchical level map generated by
	 * the model
	 */
	private function makeCategories()
	{
		$logger = $this->getState('logger');
		JLog::add('Creating categories');
		
		$levelMap = $this->getState('levelmap');
		foreach($levelMap as $key => $id) {
			$parts = explode('.',$key);
			$level = count($parts);
			$parent = ($level == 1) ? 1 : $levelMap[ implode('.',  array_slice($parts, 0, count($parts) - 1)) ];
			$id = $this->createCategory($level, $key, $parent);
			$levelMap[$key] = $id;
			
			// Remove articles from category
			$db = $this->getDbo();

			$query = 'DELETE FROM #__assets WHERE `id` IN (SELECT `asset_id` FROM `#__content` WHERE `catid` = '.$db->q($id).')';
			$db->setQuery($query);
			$db->query(); // Whoosh!

			$query = $db->getQuery(true);
			$query->delete('#__content')
				->where($db->qn('catid').' = '.$db->q($id));
			$db->setQuery($query);
			$db->query();
		}
		
		JLog::add("Updating levelmap in model state", JLog::DEBUG);
		
		$this->setState('levelmap', $levelMap);
	}
	
	/**
	 * Create a single category and return its ID. If the category alias already
	 * exists, return the ID of that specific category alias.
	 * 
	 * @param type $level
	 * @param type $levelpath
	 * @param type $parent_id
	 * @return type 
	 */
	private function createCategory($level = 1, $levelpath = '1', $parent_id = 1)
	{
		$logger = $this->getState('logger');
		$title = 'Overload ';
		$alias = 'overload-';
		$title .= $levelpath;
		$alias .= str_replace('.', '-', $levelpath);
		
		$data = array(
			'parent_id'		=> $parent_id,
			'level'			=> $level,
			'extension'		=> 'com_content',
			'title'			=> $title,
			'alias'			=> $alias,
			'description'	=> '<p>Sample content generated by Overload</p>',
			'access'		=> 1,
			'params'		=> array('target' => '', 'image' => ''),
			'metadata'		=> array('page_title' => '', 'author' => '', 'robots' => ''),
			'hits'			=> 0,
			'language'		=> '*',
			'published'		=> 1
		);

		$basePath = JPATH_ADMINISTRATOR . '/components/com_categories';
		require_once $basePath . '/models/category.php';
		$config = array('table_path' => $basePath . '/tables');
		$model = new CategoriesModelCategory($config);
		$result = $model->save($data);
		
		if($result === false) {
			$db = $this->getDbo();
			$query = $db->getQuery(true);
			$query
				->select('id')
				->from( $db->qn('#__categories') )
				->where($db->qn('alias').' = '.$db->q($alias));
			$db->setQuery($query);
			$id = $db->loadResult();
			JLog::add("Existing category $levelpath, ID $id", JLog::DEBUG);
			
			// Enable an existing category
			$cat = $model->getItem($id);
			if(!$cat->published) {
				$cat->published = 1;
			}
			$cat = (array)$cat;
			$model->save($cat);
			
			return $id;
		} else {
			$id = $model->getState($model->getName().'.id');
			JLog::add("New category $levelpath, ID $id", JLog::DEBUG);
			return $id;
		}
	}
	
	/**
	 * Creates a faux article inside the specified category
	 * 
	 * @param type $cat_id
	 * @param type $levelpath
	 * @param type $currentArticle 
	 */
	private function createArticle($cat_id = '1', $levelpath = '1', $currentArticle = 1)
	{
		$data = $this->getArticleData($cat_id, $levelpath, $currentArticle);
		
		$db = $this->getDbo();
		$data = (object)$data;
		$data->attribs = json_encode($data->attribs);
		$result = $db->insertObject('#__content', $data, 'id');
		if(!$result) {
			die($db->getErrorMsg());
		}
	}
	
		/**
	 * Creates a faux article inside the specified category
	 * 
	 * @param type $cat_id
	 * @param type $levelpath
	 * @param type $currentArticle 
	 */
	private function createArticle_usingModel($cat_id = '1', $levelpath = '1', $currentArticle = 1)
	{
		$data = $this->getArticleData($cat_id, $levelpath, $currentArticle);
		
		require_once JPATH_ADMINISTRATOR.'/components/com_content/models/article.php';
		$model = new ContentModelArticle();
		$result = $model->save($data);
	}
	
	private function getArticleData($cat_id = '1', $levelpath = '1', $currentArticle = 1, $addPictures = true)
	{
		$logger = $this->getState('logger');
		
		$title = 'Overload Sample ';
		$alias = 'overload-sample-';
		$title .= $currentArticle.' in '.str_replace('.', '-', $levelpath);
		$alias .= $currentArticle.'-in-'.str_replace('.', '-', $levelpath);
		
		$url = str_replace('/administrator', '', JURI::base(true));
		$url = rtrim($url,'/');
		$picture1 = $addPictures ? '<img src="'.$url.'/images/sampledata/fruitshop/apple.jpg" align="left" />' : '';
		$picture2 = $addPictures ? '<img src="'.$url.'/images/sampledata/parks/animals/180px_koala_ag1.jpg" align="right" />' : '';
		
		$introtext = <<<ENDTEXT
$picture1<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec auctor velit blandit risus posuere sit amet sollicitudin enim dictum. Nunc a commodo magna. Cras mattis, purus et ornare dictum, velit mi dictum nisl, sed rutrum massa eros nec leo. Sed at nibh nec felis dignissim tristique. Mauris sed posuere velit. Curabitur vehicula dui libero. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean iaculis felis ac elit euismod vitae venenatis dui iaculis. Morbi nec ipsum sit amet erat scelerisque auctor ac eget elit. Phasellus ut mattis ipsum. In auctor lacinia porttitor. Aliquam erat volutpat. In hac habitasse platea dictumst. Pellentesque iaculis mi ut ante tempor pharetra.</p>
ENDTEXT;
		$fulltext = <<<ENDTEXT
<p>Aenean nisl velit, consectetur hendrerit ultricies eu, vehicula eu massa. Nunc elementum enim vitae tortor dignissim eget vulputate quam condimentum. Pellentesque ante felis, venenatis non malesuada a, sodales ut nunc. Morbi sed nulla <a href="http://www.joomla.org">sit amet erat cursus venenatis</a>. Nulla non diam id risus egestas varius vel nec nulla. Nullam pretium congue cursus. Nullam ultricies laoreet porttitor. Proin ultricies aliquam lacinia. Proin porta interdum enim eu ultrices. Maecenas id dui vitae nisl ultrices cursus quis et nisi. Sed rhoncus vestibulum eros vel faucibus. Nulla facilisi. Mauris lacus metus, aliquet eu iaculis vitae, tempor ac metus. Sed sem nunc, tempor vehicula condimentum at, ultricies a tellus. Proin dui velit, accumsan vitae facilisis mollis, tristique aliquet purus. Aliquam porta, orci nec feugiat semper, tortor nunc pulvinar lorem, sed ultricies mauris justo eu orci. Nullam urna leo, vehicula at interdum non, fringilla eget neque. Quisque dui metus, hendrerit ut porttitor non, dignissim eu ipsum.</p>
<p>Pellentesque ultricies adipiscing odio, <em>at interdum dui tempus ac</em>. Aliquam accumsan sem et tortor facilisis sagittis. Sed interdum erat in ante venenatis dignissim. Nulla neque metus, interdum a porta eu, lobortis quis libero. Maecenas condimentum lectus id nisi suscipit tempus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas at neque diam. Suspendisse arcu purus, eleifend accumsan imperdiet in, porta ac ante. Nam lobortis tincidunt erat, non ornare mauris vestibulum non. Vivamus feugiat nunc pretium mi pharetra dictum. Donec auctor tincidunt pulvinar. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.</p>
$picture2<p>Nunc feugiat porta faucibus. Nulla facilisi. Sed viverra laoreet mollis. Morbi ullamcorper lorem a lacus porttitor tristique. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean <strong>consequat</strong> tincidunt lacinia. Maecenas dictum volutpat lacus, nec malesuada ipsum congue sed. Sed nec neque erat. Donec eros urna, vulputate ac elementum sit amet, pharetra sit amet urna. Phasellus in lectus metus. Proin vitae diam augue, vel lacinia lectus. Ut tincidunt, dolor sit amet hendrerit gravida, augue mauris bibendum sapien, nec porta ipsum diam eget erat. In porta nisl eget odio placerat gravida commodo tortor feugiat. Donec in tincidunt dui. In in neque tellus. Phasellus velit lacus, viverra et sodales nec, porta in velit.</p>
<p>Etiam quis velit odio. Nunc dignissim enim vel enim blandit tempus. Integer pellentesque leo ac risus hendrerit sed consequat lacus elementum. Aenean placerat leo vitae nunc bibendum cursus. Ut ac dui diam. Vivamus massa tortor, consectetur at scelerisque eget, hendrerit et elit. Aliquam hendrerit quam posuere tellus sollicitudin sollicitudin. Ut eget lacinia metus. Curabitur vitae orci ac libero vestibulum commodo. Sed id nibh eu erat pretium tempus. Nullam suscipit fringilla tortor, ac pretium metus iaculis eu. Fusce pellentesque volutpat tortor, at interdum tortor blandit at. Morbi rhoncus euismod ultricies. Fusce sed massa at elit lobortis iaculis non id metus. Aliquam erat volutpat. Vivamus convallis mauris ut sapien tempus quis tempor nunc cursus. Quisque in lorem sem.</p>
ENDTEXT;
		jimport('joomla.utilities.date');
		$jNow = new JDate();

		$state  = (int) $this->getState('articlesstate', 1);

		$data = array(
			'id'			=> 0,
			'title'			=> $title,
			'alias'			=> $alias,
			'introtext'		=> $introtext,
			'fulltext'		=> $fulltext,
			'state'			=> $state,
			'sectionid'		=> 0,
			'mask'			=> 0,
			'catid'			=> $cat_id,
			'created'		=> $jNow->toMySQL(),
			'created_by_alias' => 'Overload',
			'attribs'		=> array(
				"show_title"=>"","link_titles"=>"","show_intro"=>"","show_category"=>"","link_category"=>"","show_parent_category"=>"","link_parent_category"=>"","show_author"=>"","link_author"=>"","show_create_date"=>"","show_modify_date"=>"","show_publish_date"=>"","show_item_navigation"=>"","show_icons"=>"","show_print_icon"=>"","show_email_icon"=>"","show_vote"=>"","show_hits"=>"","show_noauth"=>"","alternative_readmore"=>"","article_layout"=>""
			),
			'version'		=> 1,
			'parentid'		=> 0,
			'ordering'		=> 0,
			'metakey'		=> '',
			'metadesc'		=> '',
			'access'		=> 1,
			'hits'			=> 0,
			'featured'		=> 0,
			'language'		=> '*',
			'state'			=> $state
		);
		
		return $data;
	}
}