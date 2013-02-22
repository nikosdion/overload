<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 */

defined('_JEXEC') or die();

jimport('joomla.application.component.controller');

class OverloadControllerProcess extends JController
{
	public function start($cachable = false, $urlparams = false) {
		$model = $this->getModel('Process', 'OverloadModel');
		
		$model->setState('categories', JRequest::getInt('categories',3));
		$model->setState('depth',  JRequest::getInt('depth',3));
		$model->setState('articles', JRequest::getInt('articles', 1000));
		$model->setState('articlesstate', JRequest::getInt('articlesstate', 1));

		JLog::add('Initializing content overload');
		JLog::add('Categories: '.$model->getState('categories'), JLog::DEBUG);
		JLog::add('Depth: '.$model->getState('depth'), JLog::DEBUG);
		JLog::add('Articles: '.$model->getState('articles'), JLog::DEBUG);
		JLog::add('Articles state: '.$model->getState('articlesstate'), JLog::DEBUG);

		$done = $model->start();
		
		echo json_encode(array(
			'done'			=> $done,
			'totalcats'		=> $model->getState('totalcats', 0),
			'donecats'		=> $model->getState('donecats', 0),
			'article'		=> $model->getState('startfromarticle', 0),
			'articles'		=> $model->getState('articles'),
			'articlesstate'	=> $model->getState('articlesstate')
		));
		
		JLog::add('Preparing to sleep', JLog::DEBUG);
		JLog::add('Total categories: '.$model->getState('totalcats',0), JLog::DEBUG);
		JLog::add('Done categories: '.$model->getState('donecats',0), JLog::DEBUG);
		JLog::add('Total articles in category: '.$model->getState('articles',0), JLog::DEBUG);
		JLog::add('Resume from article: '.$model->getState('article',0), JLog::DEBUG);
		JLog::add('Going to sleep');
		
		JFactory::getSession()->close();
		JFactory::getApplication()->close();
	}
	
	public function resume($cachable = false, $urlparams = false) {
		JLog::add('Waking up');
		
		$model = $this->getModel('Process', 'OverloadModel');
		$done = $model->resume();
		
		echo json_encode(array(
			'done'			=> $done,
			'totalcats'		=> $model->getState('totalcats', 0),
			'donecats'		=> $model->getState('donecats', 0),
			'article'		=> $model->getState('startfromarticle', 0),
			'articles'		=> $model->getState('articles'),
			'articlesstate'	=> $model->getState('articlesstate')
		));
		
		JLog::add('Preparing to sleep', JLog::DEBUG);
		JLog::add('Total categories: '.$model->getState('totalcats',0), JLog::DEBUG);
		JLog::add('Done categories: '.$model->getState('donecats',0), JLog::DEBUG);
		JLog::add('Total articles in category: '.$model->getState('articles',0), JLog::DEBUG);
		JLog::add('Resume from article: '.$model->getState('article',0), JLog::DEBUG);
		JLog::add('Going to sleep');
		
		JFactory::getSession()->close();
		JFactory::getApplication()->close();
	}
}