<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 */

defined('_JEXEC') or die(); ?>

<h2><?php echo JText::_('COM_OVERLOAD_SUBTITLE');?></h2>

<div>
	<p><?php echo JText::_('COM_OVERLOAD_WARNING') ?></p>
</div>

<fieldset id="overload-wrapper">
	<legend><?php echo JText::_('COM_OVERLOAD_CCOPTS_LEGEND') ?></legend>

	<!-- 1000 * (3^3 + 3^2 + 3^1) -->
	
	<table>
		<tr>
			<td>
				<label for="overload-cats"><?php echo JText::_('COM_OVERLOAD_CCOPTS_CATS_TITLE')?></label>
			</td>
			<td>
				<input type="text" size="5" id="overload-cats" name="categories" value="10" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="overload-depth"><?php echo JText::_('COM_OVERLOAD_CCOPTS_DEPTH_TITLE')?></label>
			</td>
			<td>
				<input type="text" size="5" id="overload-depth" name="depth" value="3" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="overload-articles"><?php echo JText::_('COM_OVERLOAD_CCOPTS_ARTICLES_TITLE');?></label>
			</td>
			<td>
				<input type="text" size="8" id="overload-articles" name="articles" value="1500" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="overload-articles-state"><?php echo JText::_('COM_OVERLOAD_CCOPTS_ARTICLES_STATE');?></label>
			</td>
			<td>
				<input type="text" size="8" id="overload-articles-state" name="articlesstate" value="1" />
			</td>
		</tr>
		<tr>
			<td>
				<?php echo JText::_('COM_OVERLOAD_PROJECTED_TITLE') ?>
			</td>
			<td>
				<span id="overload-projected-articles">
					<?php echo JText::_('COM_OVERLOAD_PROJECTED_DEFAULT') ?>
				</span>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<button id="overload-start"><?php echo JText::_('COM_OVERLOAD_BUTTON_START')?></button>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset id="overload-results-wrapper" style="display: none">
	<legend><?php echo JText::_('COM_OVERLOAD_CCP_LEGEND')?></legend>
	<p><?php echo JText::_('COM_OVERLOAD_CCP_LONGTIME')?></p>
	<table>
		<tr>
			<td><?php echo JText::_('COM_OVERLOAD_CCP_CAT')?></td>
			<td>
				<span id="overload-results-donecats">0</span> <?php echo JText::_('COM_OVERLOAD_CCP_OF')?> <span id="overload-results-totalcats"><?php echo JText::_('COM_OVERLOAD_CCP_UNKNOWN')?></span>
			</td>
		</tr>
		<tr>
			<td><?php echo JText::_('COM_OVERLOAD_CCP_ARTICLE')?></td>
			<td>
				<span id="overload-results-article">0</span> <?php echo JText::_('COM_OVERLOAD_CCP_OF')?> <span id="overload-results-articles"><?php echo JText::_('COM_OVERLOAD_CCP_UNKNOWN')?></span>
			</td>
		</tr>
	</table>
</fieldset>

<script type="text/javascript">
	ajax_url = '<?php echo JURI::base().'index.php?option=com_overload&view=process' ?>';
</script>