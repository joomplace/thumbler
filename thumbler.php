<?php
/**
 * @package     JoomPlace.Plugin
 * @subpackage  System.sef
 *
 * @copyright   Copyright (C) 2005 - 2017 Joomplace. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Joomla! Thumbler Plugin.
 *
 * @since  3.5
 */
class PlgSystemThumbler extends JPlugin
{
	/**
	 * Import and register thumbler as JHTML function
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onAfterDispatch()
	{
		jimport('plugins.system.thumbler.Processor',JPATH_SITE);
        \joomplace\plugins\system\thumbler\Processor::$params = $this->params->toString();
        JHtml::register('thumbler.generate','\joomplace\plugins\system\thumbler\Processor::getThumb');
	}
}
