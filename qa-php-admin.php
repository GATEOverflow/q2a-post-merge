<?php
//This is admin page code where init and default options defined
class qa_merge_admin {

	function allow_template($template)
	{
			return ($template!='admin');
	}

	function option_default($option) {
		
		switch($option) {
			case 'merge_question_merged':
				return 'Redirected from merged question ^post';
			default:
				return null;				
		}
		
	}
	
	function init_queries($tableslc) {
		require_once QA_INCLUDE_DIR."db/selects.php";
		$queries = array();
		$tablename = qa_db_add_table_prefix('postmeta');

		// Create table if not exists
		if (!in_array($tablename, $tableslc)) {
			$queries[] = "
				CREATE TABLE `$tablename` (
					meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					post_id bigint(20) unsigned NOT NULL,
					meta_key varchar(255) DEFAULT '',
					meta_value longtext,
					site_prefix varchar(64) DEFAULT NULL,
					is_from_blog tinyint(1) NOT NULL DEFAULT 0,
					is_to_blog tinyint(1) NOT NULL DEFAULT 0,
					PRIMARY KEY (meta_id),
					KEY post_id (post_id),
					KEY meta_key (meta_key)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		}

		// Check existing columns
		$sqlCols = 'SHOW COLUMNS FROM '.$tablename;
		$fields = qa_db_read_all_values(qa_db_query_sub($sqlCols));

		// Required extra columns (column => definition)
		$requiredColumns = array(
			'site_prefix' => "varchar(64) DEFAULT NULL",
			'is_from_blog'     => "tinyint(1) NOT NULL DEFAULT 0",
			'is_to_blog'     => "tinyint(1) NOT NULL DEFAULT 0",
		);

		foreach ($requiredColumns as $column => $definition) {
			if (!in_array($column, $fields)) {
				$queries[] = "ALTER TABLE $tablename ADD $column $definition";

				if ($column === 'site_prefix') {
					// Set all existing rows to current prefix for consistency
					$queries[] = "UPDATE $tablename SET site_prefix = '".QA_MYSQL_TABLE_PREFIX."'";
				}
				if ($column === 'is_from_blog') {
					$queries[] = "UPDATE $tablename SET is_from_blog = 0";
				}
				if ($column === 'is_to_blog') {
					$queries[] = "UPDATE $tablename SET is_to_blog = 0";
				}
			}
		}

		return $queries;
	}

	function admin_form(&$qa_content)
	{
		$ok = null;

		// Save option when admin clicks "Save"
		if (qa_clicked('save_options_submit')) {
			qa_opt('merge_question_merged', qa_post_text('merge_question_merged'));
			$ok = "Settings saved.";
		}

		$fields = array();

		$fields[] = array(
			'label' => 'Message to show when a question is merged:',
			'tags'  => 'NAME="merge_question_merged"',
			'value' => qa_opt('merge_question_merged'),
		);

		$buttons = array(
			array(
				'label' => 'Save Options',
				'tags'  => 'NAME="save_options_submit"',
			),
		);

		return array(
			'ok'      => $ok,
			'fields'  => $fields,
			'buttons' => $buttons,
		);
	}

}


/*
        Omit PHP closing tag to help avoid accidental output
*/
