<?php
/**
 * Created by Symphony Extension Developer.
 * 2012-10-29
 */

require_once(EXTENSIONS.'/modules_installer/lib/class.modules_installer.php');

class Extension_Modules_installer extends Extension {

	/**
	 * About information
	 * For if you want to create a pre-2.3-extension
	 */
	public function about() {
		return array(
			'name'			=> 'Modules Installer',
			'version'		=> '1.0',
			'release-date'	=> '2012-10-29',
			'author'		=> array(
				array(
					'name' => 'Giel Berkers',
					'website' => 'http://www.gielberkers.com',
					'email' => 'info@gielberkers.com'
				)
			)
		);
	}

	/**
	 * Get the subscribed delegates
	 * @return array
	 */
	public function getSubscribedDelegates() {
		return array(
			array(
				'page'		=> '/frontend/',
				'delegate'	=> 'FrontendPageResolved',
				'callback'	=> 'actionFrontendPageResolved'
			)
		);
	}

	/*
	 * Delegate 'DataSourcePreExecute' function
	 * @param $context
	 *  Provides the following parameters:
	 *  - datasource (boolean) : The Datasource object
	 *  - xml (mixed) : The XML output of the data source. Can be an  or string.
	 *  - param_pool (mixed) : The existing param pool including output parameters of any previous data sources
	 */
	public function actionFrontendPageResolved($context)
	{
		// Your code goes here...

	}

}