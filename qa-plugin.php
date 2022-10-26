<?php

/*
	Plugin Name: Merge
	Plugin URI: https://github.com/NoahY/q2a-merge
	Plugin Update Check URI: https://raw.github.com/NoahY/q2a-merge/master/qa-plugin.php
	Plugin Description: Provides merging capabilities
	Plugin Version: 0.2
	Plugin Date: 2011-10-15
	Plugin Author: NoahY
	Plugin Author URI: http://www.question2answer.org/qa/user/NoahY
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.4
 */


if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

qa_register_plugin_layer('qa-merge-layer.php', 'Merge Layer');

qa_register_plugin_module('module', 'qa-php-widget.php', 'qa_merge_admin', 'Merge Admin');

// merge button on duplicate question page (only for admin)
qa_register_plugin_layer('qa-merge-layer-ondup.php', 'Merge Button for Duplicate Question');

function qa_merge_do_merge() {
	qa_opt('merge_question_merged',qa_post_text('merge_question_merged'));

	$from = (int)qa_post_text('merge_from');
	$to = (int)qa_post_text('merge_to');
        
	$titles = qa_db_read_all_assoc(
		qa_db_query_sub(
			"SELECT postid,title,acount,selchildid,tags FROM ^posts WHERE postid IN (#,#) order by postid",
				qa_post_text('merge_from'),qa_post_text('merge_to')
		)
	);
	if(count($titles) != 2) {
		$error1 = null;
		$error2 = null;
		if(empty($titles)) {
			$error1 = 'Post not found.';
			$error2 = $error1;
		}
		else if($titles[0]['postid'] == $from){
			$error2 = 'Post not found.';
		}
		else if($titles[0]['postid'] == $to){
			$error1 = 'Post not found.';
		}
		else $error1 = 'unknown error.';
		return array($error1,$error2);
	}
	else {
	$force_source_title = isset($_POST["force_source_title"]);
	$force_source_tags = isset($_POST["force_source_tags"]);
		$acount = (int)$titles[0]['acount']+(int)$titles[1]['acount'];
		$index_from = (int)($from > $to); 
		$index_to = (int)($from < $to); 
		$fromtitle = $titles[$index_from]['title'];
		$fromtags = $titles[$index_from]['tags'];
		$totags = $titles[$index_to]['tags'];
		$fromselchildid = $titles[$index_from]['selchildid'];
		$toselchildid = $titles[$index_to]['selchildid'];
		$text = '<div class="qa-content-merged"> '.str_replace('^post',qa_path(qa_q_request((int)qa_post_text('merge_to'), ($titles[0]['postid'] == $to?$titles[0]['title']:$titles[1]['title'])), null, qa_opt('site_url')),qa_opt('merge_question_merged')).' </div>';

		if(empty($toselchildid) && !empty($fromselchildid)) {
			qa_db_query_sub(
				"UPDATE ^posts SET selchildid=# WHERE postid=#",
				$fromselchildid,$to
			);
		}
		if((empty($totags) && !empty($fromtags)) || $force_source_tags ) {
			qa_db_query_sub(
				"UPDATE ^posts SET tags=$ WHERE postid=#",
				$fromtags,$to
			);
		}
		if($force_source_title) {
			qa_db_query_sub(
				"UPDATE ^posts SET title=$ WHERE postid=#",
				$fromtitle,$to
			);
		}
		qa_db_query_sub(
			"UPDATE ^posts SET parentid=# WHERE parentid=#",
			$to, $from
		);

		qa_db_query_sub(
			"UPDATE ^posts SET acount=# WHERE postid=#",
			$acount,$to
		);

		qa_db_query_sub(
			'CREATE TABLE IF NOT EXISTS ^postmeta (
				meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) DEFAULT \'\',
				meta_value longtext,
				PRIMARY KEY (meta_id),
				KEY post_id (post_id),
				KEY meta_key (meta_key)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8'
			);			

			qa_db_query_sub(
				"INSERT INTO ^postmeta (post_id,meta_key,meta_value) VALUES (#,'merged_with',#)",
				$from,$to
			);                                

			require_once QA_INCLUDE_DIR.'qa-app-posts.php';

			$postowner = qa_db_read_one_value(
			qa_db_query_sub(
				"SELECT userid FROM ^posts WHERE postid =  #",
				$from
			), true
		);
		/*	qa_report_event("q_merge", qa_get_logged_in_userid(), qa_get_logged_in_handle(), qa_cookie_get_create(), array(
			"postid" => qa_post_text('merge_to'),
			//"postid" => $from,
			"to" => $to
		));*/
			qa_report_event("in_q_merge", $postowner, qa_get_logged_in_handle(), qa_cookie_get_create(), array(
			"postid" => qa_post_text('merge_to'),
		));
			/*deleting possible notification of post close*/
			$query = "delete from ^eventlog where event like 'in_q_close' and params like 'postid=#%'";
			qa_db_query_sub($query, $from);

			qa_post_delete($from);
			return true;
		}

	}

/*
	Omit PHP closing tag to help avoid accidental output
*/
