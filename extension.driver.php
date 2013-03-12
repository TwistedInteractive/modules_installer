<?php
/**
 * Created by Symphony Extension Developer.
 * 2012-10-29
 */

require_once(EXTENSIONS.'/modules_installer/lib/class.modules_installer.php');

class Extension_Modules_installer extends Extension {

	const DS_PARSE_TRUE  = 100;
	const NO_FILE_FOUND  = 101;
	const IS_MODULAR	 = 200;
	const CAN_BE_MODULAR = 300;

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
			),
			array(
				'page'		=> '/backend/',
				'delegate'	=> 'AdminPagePreGenerate',
				'callback'	=> 'actionAdminPagePreGenerate'
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

	public function actionAdminPagePreGenerate($context)
	{
		$callback = Administration::instance()->getPageCallback();
		if($callback['driver'] == 'blueprintsdatasources')
		{
			// Check if there are actions set:
			if(isset($_GET['make-modular-ds']))
			{
				$this->makeModularDataSource($_GET['make-modular-ds']);
			}

			if(!empty($callback['context']))
			{
				if($callback['context'][0] == 'edit')
				{

				}
			} else {
				// Index page, add an extra column:
				$header = Administration::instance()->Page->Form->
					getChildByName('table', 0)->getChildByName('thead', 0)->getChildByName('tr', 0);
				$header->appendChild(
					new XMLElement('th', __('Is Modular'))
				);

				$rows = Administration::instance()->Page->Form->
					getChildByName('table', 0)->getChildByName('tbody', 0)->getChildren();

				foreach($rows as $row)
				{
					$elementName = $row->getChild(0)->getChild(0)->getAttribute('title');

					$check = $this->checkIfModularDatasource($elementName);
					$class = 'inactive';
					if($check == self::CAN_BE_MODULAR)
					{
						$anchor = Widget::Anchor(__('No'), '?make-modular-ds='.$elementName);
						$class = '';
					} elseif($check == self::DS_PARSE_TRUE) {
						$anchor = new XMLElement('span', __('-'), array('title' => 'Cannot be made modular automatically (allowEditorToParse returns false)'));
					} elseif($check == self::NO_FILE_FOUND) {
						$anchor = new XMLElement('span', __('-'), array('title' => 'Cannot be made modular automatically (no file found)'));
					} elseif($check == self::IS_MODULAR) {
						$anchor = new XMLElement('span', __('Yes'));
						$class = '';
					} else {
						$anchor = new XMLElement('span', '-');
					}

					$row->appendChild(new XMLElement('td', $anchor, array('class'=>$class)));
				}
			}
		}
	}

	/**
	 * Make a datasource modular
	 * @param $dsName
	 */
	private function makeModularDataSource($dsName)
	{
		$content = file_get_contents(DATASOURCES.'/data.'.$dsName.'.php');

		// getSource function:
		// Get the handle of the used section:
		$id = $this->getSourceId($content);
		$handle = SectionManager::fetch($id)->get('handle');

		// Replace it:
		$content = $this->searchAndReplaceGetSource($content, $id,
			'SectionManager::fetchIDFromHandle(\''.$handle.'\')');

		// Filters:
		$filters = $this->getFilters($content);
		print_r($filters);

		echo '<xmp>'.$content;
		die();
	}

	/**
	 * Search and replace the return value of the getSource()-function
	 * @param $str
	 * @param $old
	 * @param $new
	 * @return mixed
	 */
	private function searchAndReplaceGetSource($str, $old, $new)
	{
		$str = preg_replace('/public function getSource\(\)\{\s+return \''.$old.'\';(.*)\s}/msU',
			"public function getSource(){\n\t\t\treturn $new;\n\t\t}", $str);
		return $str;
	}

	/**
	 * Get the source ID
	 * @param $str
	 * @return mixed
	 */
	private function getSourceId($str)
	{
		$str = preg_match('/public function getSource\(\)\{\s+return \'(.*)\';(.*)\s}/msU', $str, $matches);
		return $matches[1];
	}

	/**
	 * Get allow editor to parse value
	 * @param $str
	 * @return bool
	 */
	private function getAllowEditorToParse($str)
	{
		$str = preg_match('/public function allowEditorToParse\(\)\{\s+return (.*);(.*)\s}/msU', $str, $matches);
		return $matches[1] == 'true';
	}

	private function getFilters($str)
	{
		$str = preg_match('/public \$dsParamFILTERS = array\(\s+(.*)\s+\);/msU', $str, $matches);
		$filters = explode(',', $matches[1]);
		$arr = array();
		foreach($filters as $filter)
		{
			$filter = trim($filter);
			if(!empty($filter))
			{
				$a = explode('=>', $filter);
				$key = trim(str_replace('\'', '', $a[0]));
				$value = trim(str_replace('\'', '', $a[1]));
				$arr[$key] = $value;
			}
		}
		return $arr;
	}

	/**
	 * Check if the data source is modular
	 * @param $dsName
	 * @return int
	 */
	private function checkIfModularDatasource($dsName)
	{
		if(file_exists(DATASOURCES.'/data.'.$dsName.'.php'))
		{
			$content = file_get_contents(DATASOURCES.'/data.'.$dsName.'.php');
			if($this->getAllowEditorToParse($content) == false) {
				return self::DS_PARSE_TRUE;
			}
			// Check if the file already is modular:
			if(!is_numeric($this->getSourceId($content))) {
				return self::IS_MODULAR;
			}
			return self::CAN_BE_MODULAR;
		} else {
			return self::NO_FILE_FOUND;
		}
	}
}