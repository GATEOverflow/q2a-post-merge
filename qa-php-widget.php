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
		$tablename=qa_db_add_table_prefix('postmeta');
		if(!in_array($tablename, $tableslc)) {
			$queries[] = "
				CREATE TABLE `$tablename` (
				meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) DEFAULT '',
				meta_value longtext,
				site_prefix varchar(64) DEFAULT NULL,
				PRIMARY KEY (meta_id),
				KEY post_id (post_id),
				KEY meta_key (meta_key)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		}
		$sqlCols = 'SHOW COLUMNS FROM '.$tablename;
		$fields = qa_db_read_all_values(qa_db_query_sub($sqlCols));	
		$column = 'site_prefix';
		if(!in_array($column, $fields)) {
			$queries [] =  'ALTER TABLE '.$tablename.' ADD '.$column. ' varchar(64) DEFAULT NULL';
			$queries [] =  'Update '.$tablename.'set site_prefix = '. QA_MYSQL_TABLE_PREFIX; //So, at start, all links are mapped internally.
		}
		return $queries;
	} 
	function admin_form(&$qa_content)
    {
        $ok = null;

        if (qa_clicked('delete_q_submit')) {
            $postid = (int) qa_post_text('delete_qid');

            if ($postid) {
                // Fetch base type of post
                $result = qa_db_read_one_assoc(
                    qa_db_query_sub(
                        'SELECT postid, LEFT(type,1) AS basetype FROM ^posts WHERE postid = #',
                        $postid
                    ),
                    true
                );

                if (!empty($result)) {
                    require_once QA_INCLUDE_DIR . 'app/posts.php';
                    require_once QA_INCLUDE_DIR . 'app/format.php';

                    // call your recursive delete function
                    delete_post_and_children($result['postid'], $result['basetype']);
                    $ok = "Post #{$postid} and its children have been deleted.";
                } else {
                    $ok = "Post ID {$postid} not found.";
                }
            } else {
                $ok = "Please enter a valid Post ID.";
            }
        }

        $fields = array();

        $fields[] = array(
            'label' => 'Enter Post ID (qid) to delete:',
            'tags'  => 'NAME="delete_qid"',
            'value' => qa_html(qa_post_text('delete_qid')),
        );

        $buttons = array(
            array(
                'label' => 'Delete Post',
                'tags'  => 'NAME="delete_q_submit"',
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
