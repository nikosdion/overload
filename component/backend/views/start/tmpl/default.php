<?php
/**
 * @package overload
 * @author Nicholas K. Dionysopoulos <nicholas@akeebabackup.com>
 * @license GNU GPL v3 or later
 * @version 1.0
 */

defined('_JEXEC') or die(); ?>

<h2>Overload - Mass content creator</h2>

<div>
	<p>
		<strong>WARNING!</strong> Do not use this tool on a live site, unless
		you want to most likely bring it down. This is to be used only for
		testing purposes by members of the Joomla! Bug Squad and other people
		doing large dataset optimisations for the Joomla! core. You have been
		warned.
	</p>
</div>

<fieldset id="overload-wrapper">
	<legend>Content creation options</legend>

	<!-- 1000 * (3^3 + 3^2 + 3^1) -->
	
	<table>
		<tr>
			<td>
				<label for="overload-cats">Number of categories per level</label>
			</td>
			<td>
				<input type="text" size="5" id="overload-cats" name="categories" value="10" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="overload-depth">Categories depth</label>
			</td>
			<td>
				<input type="text" size="5" id="overload-depth" name="depth" value="3" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="overload-articles">Articles per category</label>
			</td>
			<td>
				<input type="text" size="8" id="overload-articles" name="articles" value="1500" />
			</td>
		</tr>
		<tr>
			<td>
				Projected number of articles
			</td>
			<td>
				<span id="overload-projected-articles"></span>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<button id="overload-start">Start!</button>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset id="overload-results-wrapper" style="display: none">
	<legend>Content creation process</legend>
	<p>This is going to take <em>a very long time</em>...</p>
	<table>
		<tr>
			<td>Category</td>
			<td>
				<span id="overload-results-donecats">0</span> of <span id="overload-results-totalcats">unknown</span>
			</td>
		</tr>
		<tr>
			<td>Article</td>
			<td>
				<span id="overload-results-article">0</span> of <span id="overload-results-articles">unknown</span>
			</td>
		</tr>
	</table>
</fieldset>

<script type="text/javascript">
	ajax_url = '<?php echo JURI::base().'index.php?option=com_overload&view=process' ?>';
</script>