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
					'name' => 'Twisted Interactive',
					'website' => 'http://www.twisted.nl'
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
		if($callback['driver'] == 'blueprintsdatasources' || $callback['driver'] == 'blueprintsevents')
		{
			// Check if there are actions set:
			if(isset($_GET['make-modular-ds']))
			{
				$this->makeModularDataSource($_GET['make-modular-ds']);
			}

            if(isset($_GET['make-modular-event']))
            {
                $this->makeModularEvent($_GET['make-modular-event']);
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

                    if($callback['driver'] == 'blueprintsdatasources')
                    {
					    $check = $this->checkIfModularDatasource($elementName);
                        $linkName = 'make-modular-ds';
                    } else {
                        $check = $this->checkIfModularEvent($elementName);
                        $linkName = 'make-modular-event';
                    }

					$class = 'inactive';
					if($check == self::CAN_BE_MODULAR)
					{
						$anchor = Widget::Anchor(__('No'), '?'.$linkName.'='.$elementName);
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
		$filename = DATASOURCES.'/data.'.$dsName.'.php';

		$content = file_get_contents($filename);

		// getSource function:
		// Get the handle of the used section:
		$id = $this->getSourceId($content);
		$handle = SectionManager::fetch($id)->get('handle');

		// Replace it:
		$content = $this->searchAndReplaceGetSource($content, $id,
			'SectionManager::fetchIDFromHandle(\''.$handle.'\')');

		// Group by:
		$content = $this->updateGroupBy($content);

		// Filters:
		$filters = $this->getFilters($content);
		$content = $this->injectRequireOnce($content, 'TOOLKIT.\'/class.sectionmanager.php\'');
		$code = '
		$sectionID = SectionManager::fetchIDFromHandle(\''.$handle.'\');
		$this->dsParamFILTERS = array(
';

        foreach($filters as $key => $value) {
			if(is_numeric($key)) {
				$field = FieldManager::fetch($key);
				$code .= "\t\t\t".'FieldManager::fetchFieldIDFromElementName(\''.$field->get('element_name').'\', $sectionID) => \''.$value.'\','."\n";
			} else {
				$code .= '\''.$key.'\' => \''.$value.'\','."\n";
			}
		}
		$code .= '		);';
		$content = $this->injectConstructorCode($content, $code);

		file_put_contents($filename, $content);

		redirect('/symphony/blueprints/datasources/');
	}

    /**
     * Make event modular
     * @param $eventName
     */
    private function makeModularEvent($eventName)
    {
        $filename = EVENTS.'/event.'.$eventName.'.php';

        $content = file_get_contents($filename);

        // getSource function:
        // Get the handle of the used section:
        $id = $this->getSourceId($content);
        $handle = SectionManager::fetch($id)->get('handle');

        // Replace it:
        $content = $this->searchAndReplaceGetSource($content, $id,
            'SectionManager::fetchIDFromHandle(\''.$handle.'\')');

        // Group by:
        $content = $this->updateGroupBy($content);

        $content = $this->injectRequireOnce($content, 'TOOLKIT.\'/class.sectionmanager.php\'');
        $code = '
		$sectionID = SectionManager::fetchIDFromHandle(\''.$handle.'\');
		$this->dsParamFILTERS = array(
';

        $content = $this->injectConstructorCode($content, $code);

        file_put_contents($filename, $content);

        redirect('/symphony/blueprints/events/');
    }

	/**
	 * Inject code in the constructor
	 * @param $str
	 * @param $code
	 * @return mixed
	 */
	private function injectConstructorCode($str, $code)
	{
		preg_match('/public function __construct\((.*)\)\s*\{\s*(.*)\s*}/msU', $str, $matches);
		$str = str_replace($matches[2], $code."\n".$matches[2], $str);
		return $str;
	}

	/**
	 * Inject a require_once statement in the code:
	 * @param $str
	 * @param $file
	 * @return string
	 */
	private function injectRequireOnce($str, $file)
	{
		return str_replace('<?php', '<?php require_once('.$file.');'."\n", $str);
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
		$str = preg_replace('/public (|static )function getSource\(\)\s*\{\s*return \''.$old.'\';(.*)\s*}/msU',
			"public \\1function getSource(){\n\t\t\treturn $new;\n\t\t}", $str);
		return $str;
	}

	/**
	 * Get the source ID
	 * @param $str
	 * @return mixed
	 */
	private function getSourceId($str)
	{
		preg_match('/public (|static )function getSource\(\)\s*\{\s*return \'(.*)\';(.*)\s*}/msU', $str, $matches);
		return $matches[2];
	}

	/**
	 * Get allow editor to parse value
	 * @param $str
	 * @return bool
	 */
	private function getAllowEditorToParse($str)
	{
		preg_match('/public (|static )function allowEditorToParse\(\)\s*\{\s*return (.*);(.*)\s*}/msU', $str, $matches);
		return $matches[2] == 'true';
	}

	/**
	 * Get the filters:
	 * @param $str
	 * @return array
	 */
	private function getFilters($str)
	{
		preg_match('/public \$dsParamFILTERS = array\(\s*(.*)\s*\);/msU', $str, $matches);
        // Seperate the filters (seperate by quote-comma, to prevent splitting commas in filter values):
		$filters = explode('\',', $matches[1]);

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

	private function updateGroupBy($str)
	{
		preg_match('/public \$dsParamGROUP = (.*);/msU', $str, $matches);
		if(!empty($matches))
		{
			$id = trim(str_replace('\'', '', $matches[1]));
			$field = FieldManager::fetch($id);
			$str = $this->injectConstructorCode($str, '
		$this->dsParamGROUP = FieldManager::fetchFieldIDFromElementName(\''.$field->get('element_name').'\', $sectionID);
			');
		}
		return $str;
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

			if(is_null($this->getSourceId($content))) {
				return self::IS_MODULAR;
			}
			return self::CAN_BE_MODULAR;
		} else {
			return self::NO_FILE_FOUND;
		}
	}

    /**
     * Check if an event is modular
     * @param $eventName
     * @return int
     */
    private function checkIfModularEvent($eventName)
    {
        if(file_exists(EVENTS.'/event.'.$eventName.'.php'))
        {
            $content = file_get_contents(EVENTS.'/event.'.$eventName.'.php');
            if($this->getAllowEditorToParse($content) == false) {
                return self::DS_PARSE_TRUE;
            }
            // Check if the file already is modular:

            if(is_null($this->getSourceId($content))) {
                return self::IS_MODULAR;
            }
            return self::CAN_BE_MODULAR;
        } else {
            return self::NO_FILE_FOUND;
        }
    }
}
