<?php
Class ModuleInstaller
{
	/**
	 * Check if the parameters exist in the configuration, otherwise redirect:
	 * @param $arr
	 * @return bool
	 */
	public static function checkParameters($arr)
	{
		// First check if there is save going on:
		ModuleInstaller::save();

		foreach($arr as $configurationSetting)
		{
			if(!Symphony::Configuration()->get($configurationSetting, 'modules_installer')) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Create a simple installer/fieldIDPicker according to the prefered Field ID's:
	 * @param $arr
	 * @return string
	 */
	public static function fieldIDPicker($arr)
	{
		$div = new XMLElement('div');
		foreach($arr as $configurationSetting)
		{
			$label = Widget::Label(ucfirst(str_replace(array('_', '-'), ' ', $configurationSetting)));
			$fields = FieldManager::fetch();
			$options = array();
			foreach($fields as $field)
			{
				$field_id = $field->get('id');
				$section  = SectionManager::fetch($field->get('parent_section'));
				$options[] = array($field_id, $field_id == Symphony::Configuration()->get($configurationSetting,
					'modules_installer'), $section->get('name').' : '.$field->get('label'));
			}
			sort($options);
			$select = Widget::Select('modules_installer['.$configurationSetting.']', $options);
			$label->appendChild($select);
			$label->appendChild(new XMLElement('br'));
			$div->appendChild($label);
		}
		return $div->generate();
	}

	/**
	 * Get a configuration setting
	 * @param $configurationSetting
	 * @return array|string
	 */
	public static function get($configurationSetting)
	{
		return Symphony::Configuration()->get($configurationSetting, 'modules_installer');
	}

	/**
	 * Form end string
	 * @return string
	 */
	public static function submitButton()
	{
		return '<input type="submit" class="button" value="Save Settings" />';
	}

	/**
	 * Save configuration
	 */
	public static function save()
	{
		if(isset($_POST['modules_installer']))
		{
			foreach($_POST['modules_installer'] as $key => $value)
			{
				Symphony::Configuration()->set($key, $value, 'modules_installer');
			}
			Symphony::Configuration()->write();
		}
	}
}

