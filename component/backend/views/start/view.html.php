<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 */

defined('_JEXEC') or die();

jimport('joomla.application.component.view');

class OverloadViewStart extends FOFViewHtml
{
	public function display($tpl = null) {
		JToolBarHelper::title(JText::_('COM_OVERLOAD_TITLE'));
		JHtml::_('behavior.framework');
		
		$script = <<<ENDSCRIPT
window.addEvent('domready', function() {
	$('overload-cats').addEvent('change',recalc);
	$('overload-depth').addEvent('change',recalc);
	$('overload-articles').addEvent('change',recalc);
	$('overload-start').addEvent('click', overload_start);
});

function recalc()
{
	var cats = $('overload-cats').value;
	var depth = $('overload-depth').value;
	var articles = $('overload-articles').value;
	
	var totalcats = 0;
	var totalarticles = 0;
	
	for(i=depth; i>0; i--) {
		totalcats += Math.pow(cats,i);
	}
	
	totalarticles = totalcats * articles;
	
	$('overload-projected-articles').set('html', '<b>' + totalarticles + '</b>');
}

/** @var The AJAX proxy URL */
var ajax_url = "";

function doAjax(data, successCallback)
{
	var structure =
	{
		onSuccess: function(msg, responseXML)
		{
			try {
				var data = JSON.parse(msg);
			} catch(err) {
				alert(msg);
				return;
			}

			// Call the callback function
			successCallback(data);
		},
		onFailure: function(req) {
			var message = 'AJAX Loading Error: '+req.statusText;
			alert(message);
		}
	};

	var ajax_object = null;
	if(typeof(XHR) == 'undefined') {
		structure.url = ajax_url;
		ajax_object = new Request(structure);
		ajax_object.send(data);
	} else {
		ajax_object = new XHR(structure);
		ajax_object.send(ajax_url, data);
	}
}

function overload_start()
{
	var cats = $('overload-cats').value;
	var depth = $('overload-depth').value;
	var articles = $('overload-articles').value;
	var articlesstate = $('overload-articles-state').value;

	var data = 'categories='+cats+'&depth='+depth+'&articles='+articles+'&articlesstate='+articlesstate+'&task=start';
	
	$('overload-wrapper').setStyle('display','none');
	$('overload-results-wrapper').setStyle('display','block');
	
	doAjax(data, overload_process);
	
	return false;
}

function overload_process(msg)
{
	$('overload-results-donecats').set('html',msg.donecats);
	$('overload-results-totalcats').set('html',msg.totalcats);
	$('overload-results-article').set('html',msg.article);
	$('overload-results-articles').set('html',msg.articles);

	if(msg.done) {
		alert('All done!');
		return;
	} else {
		doAjax('task=resume', overload_process);
	}
}

ENDSCRIPT;
		JFactory::getDocument()->addScriptDeclaration($script);
		
		parent::display($tpl);
	}
}