#Modules Installer

A simple extension which helps with the installation of Modules.

This is more or less a 'hack' to make Symphony work more modular. This extension only aims at the goal to make custom
datasources & events configurable (like when you're working with custom fields).

When you've created a custom datasource, make the following adjustments:

**1. Add the following in your `__construct()`-function:**

	ModuleInstaller::checkParameters(array(
		'variable_name_one',
		'variable_name_two',
		'variable_name_three',
	));

**2. Edit the `about()`-function to add a fieldpicker to the description:**

	return array(
		'name' => '...',
		'author' => array(...),
		'version' => 'Symphony 2.3',
		'release-date' => '2012-10-16T17:17:52+00:00',
		'description' =>
			ModuleInstaller::fieldIDPicker(array(
				'variable_name_one',
				'variable_name_two',
				'variable_name_three',
			)).
			ModuleInstaller::submitButton()
	);

Don't forget the `ModuleInstaller::submitButton()`!

**3. In your custom code, when refering to `tbl_entries_data_XXX`-fields, use:**

	$result = Symphony::Database()->fetch(
		sprintf('SELECT DISTINCT `handle`, `value` FROM `tbl_entries_data_%d` ORDER BY `value` ASC;',
		ModuleInstaller::get('variable_name_one'))
	);

For example...