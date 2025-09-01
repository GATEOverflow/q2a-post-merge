<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

qa_register_plugin_layer('qa-merge-layer.php', 'Merge Layer');

qa_register_plugin_module('module', 'qa-php-admin.php', 'qa_merge_admin', 'Merge Plugin Admin form and table creation');

// merge button on duplicate question page (only for admin)
qa_register_plugin_layer('qa-merge-layer-ondup.php', 'Merge Button for Duplicate Question');

global $qa_overrides;
$qa_overrides['qa_page_q_close_q_submit'] = null;	//To make the 'qa_page_q_close_q_submit' function as overridable.

qa_register_plugin_overrides('qa-merge-overrides.php', 'Question Close Override');

//---- DB Functions for merging the questions supports network sites -----

//1. Listing all the network sites that are stored in the current site options including current site.
function qa_network_sites_list()
{
	$sites = [];
	$i = 0;

	while (true) {
		$prefix = qa_opt('network_site_' . $i . '_prefix');
		$url    = qa_opt('network_site_' . $i . '_url');
		$title  = qa_opt('network_site_' . $i . '_title');

		if (empty($prefix) && empty($url) && empty($title)) {
			break; // stop when no more sites
		}
		if (empty($prefix) || empty($url) || empty($title)) { 
			continue; // skip incomplete site
		}

		$sites[] = [
			'prefix' => $prefix,
			'url'    => rtrim($url, '/'),
			'title'  => $title,
		];

		$i++;
	}

	// Always add the current site as the first option
	array_unshift($sites, [
		'prefix' => QA_MYSQL_TABLE_PREFIX,
		'url'    =>  rtrim(qa_opt('site_url'),'/'),
		'title'  => qa_opt('site_title'),
	]);

	return $sites;
}

//2. Get the URL of the site based on the prefix or prefix of the database based on the URL
function qa_network_site_url($prefix = null, $url = null) {
    static $sitesList = null;

    // Load network sites once
    if ($sitesList === null) {
        $sitesList = qa_network_sites_list(); // returns array of ['prefix' => ..., 'url' => ...]
    }

    // If prefix is provided, return URL
    if ($prefix !== null) {
        $sites = array_column($sitesList, 'url', 'prefix'); // ['prefix' => 'url']
        return isset($sites[$prefix]) ? $sites[$prefix] : null;
    }

    // If URL is provided, return prefix
    if ($url !== null) {
        $url = rtrim($url, '/');
        $sites = array_column($sitesList, 'prefix', 'url'); // ['url' => 'prefix']
        return isset($sites[$url]) ? $sites[$url] : null;
    }

    return null; // if both are null
}



// 3. Actual function which is performing Coping or Merging of the question

function qa_copy_or_merge($frompostid, $topostid, $from_site_prefix, $to_site_prefix, $force_source_title, $force_source_tags, $action)
{
    if (empty($from_site_prefix)) $from_site_prefix = QA_MYSQL_TABLE_PREFIX;
    if (empty($to_site_prefix))   $to_site_prefix   = QA_MYSQL_TABLE_PREFIX;

    // --- Fetch posts from correct sites
	$from_table = "{$from_site_prefix}posts";
	$to_table   = "{$to_site_prefix}posts";

    $titles_from = qa_db_read_all_assoc(
        qa_db_query_raw("
            SELECT postid,title,acount,selchildid,tags 
            FROM $from_table 
            WHERE postid=$frompostid
        ")
    );
    $titles_to = qa_db_read_all_assoc(
        qa_db_query_raw("
            SELECT postid,title,acount,selchildid,tags 
            FROM $to_table 
            WHERE postid=$topostid
        ")
    );

    if (empty($titles_from) || empty($titles_to)) {
        $error1 = empty($titles_from) ? 'From Post not found.' : null;
        $error2 = empty($titles_to) ? 'To Post not found.' : null;
        return array($error1, $error2);
    }

    $from_post = $titles_from[0];
    $to_post   = $titles_to[0];
	$userid = qa_get_logged_in_userid();

    // --- If action is merge with in the site, then we need just update the parent id of the childrens
    if ($action === "merge" && $from_site_prefix === $to_site_prefix) {
        qa_db_query_raw("UPDATE {$to_site_prefix}posts 
						SET parentid = $topostid,
							updated = NOW(),
							updatetype = 'M',
							lastuserid = " . $userid . "
						WHERE parentid = $frompostid");

        if (empty($to_post['selchildid']) && !empty($from_post['selchildid'])) {
            qa_db_query_raw("UPDATE {$to_site_prefix}posts 
							SET selchildid = " . (int)$from_post['selchildid'] . ",
								updated = NOW(),
								updatetype = 'M',
								lastuserid = " . $userid  . "
							WHERE postid = $topostid");
        }	

    } else if($action === "copy" || ($action === "merge" && $from_site_prefix != $to_site_prefix)){
        // --- copy with in site or across sites , Cross-site merge - we need to insert the posts 
        $children = qa_db_read_all_assoc(
            qa_db_query_raw("SELECT * FROM {$from_site_prefix}posts WHERE parentid=$frompostid")
        );

        $idmap = [];

        foreach ($children as $child) {
			// Skip notes
			if (substr($child['type'], 0, 1) === 'N') {
				continue;
			}
			//$categoryid = $child['categoryid'] ?? NULL;
			qa_db_query_raw(
				"INSERT INTO {$to_site_prefix}posts (
					type, parentid, categoryid, acount, selchildid,
					userid, cookieid, createip, title, content,
					tags, format, netvotes, lastuserid, lastip,
					lastviewip, views, hotness, flagcount, closedbyid, created, updated, updatetype
				) VALUES (
					'" . qa_db_escape_string($child['type']) . "',
					$topostid,
					NULL,
					" . (int)$child['acount'] . ",
					NULL,
					" . (int)$child['userid'] . ",
					" . (int)$child['cookieid'] . ",
					'" . qa_db_escape_string($child['createip']) . "',
					" . (isset($child['title']) ? "'" . qa_db_escape_string($child['title']) . "'" : "NULL") . ",
					'" . qa_db_escape_string($child['content']) . "',
					'" . qa_db_escape_string($child['tags']) . "',
					'" . qa_db_escape_string($child['format']) . "',
					" . (int)$child['netvotes'] . ",
					" . $userid . ",  -- lastuserid is the user performing merge
					'" . qa_db_escape_string($child['lastip']) . "',
					'" . qa_db_escape_string($child['lastviewip']) . "',
					" . (int)$child['views'] . ",
					" . (float)$child['hotness'] . ",
					" . (int)$child['flagcount'] . ",
					NULL,
					'" . $child['created'] . "',        -- original creation time
					NOW(),                               -- updated time
					'M'                                   -- update type = M (moved)
				)"
			);

			$newid = qa_db_last_insert_id();
            $idmap[$child['postid']] = $newid;
        }

        if (empty($to_post['selchildid']) && !empty($from_post['selchildid']) && isset($idmap[$from_post['selchildid']])) {
            $new_sel = (int)$idmap[$from_post['selchildid']];
            qa_db_query_raw("UPDATE {$to_site_prefix}posts SET selchildid=$new_sel WHERE postid=$topostid");
        }
    }
    // --- Common updates ---
    $acount = (int)$from_post['acount'] + (int)$to_post['acount'];
    qa_db_query_raw("UPDATE {$to_site_prefix}posts 
						SET acount=$acount,
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");

    if ((empty($to_post['tags']) && !empty($from_post['tags'])) || $force_source_tags) {
        qa_db_query_raw("UPDATE {$to_site_prefix}posts 
						SET tags='" . qa_db_escape_string($from_post['tags']) . "',
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");
    }

    if ($force_source_title) {
        qa_db_query_raw("UPDATE {$to_site_prefix}posts 
						SET title='" . qa_db_escape_string($from_post['title']) . "',
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");
    }
    return true;
}


//4. When a question 'x' merges with 'y', and 'y' merges with 'z', update the merge table 
function qa_network_update_merges($frompostid, $topostid, $from_site_prefix, $to_site_prefix, $is_from_blog=0, $is_to_blog=0) {
    $sites = qa_network_sites_list();
    foreach ($sites as $site) {
		$prefix = $site['prefix'];
		$table = $prefix . 'postmeta';
		$exists = qa_db_read_one_value(
			qa_db_query_raw("SHOW TABLES LIKE '" . qa_db_escape_string($table) . "'"),
			true
		);

		if ($exists) {
			 $columns = qa_db_read_all_assoc(
                qa_db_query_raw("SHOW COLUMNS FROM " . qa_db_escape_string($table))
            );
            $colnames = array_column($columns, 'Field');

            $hasSitePrefix = in_array('site_prefix', $colnames);
            $hasIsToBlog   = in_array('is_to_blog', $colnames);

            if ($hasSitePrefix && $hasIsToBlog) {
				qa_db_query_raw("
						UPDATE {$table}
						SET meta_value='" . (int)$topostid . "',
							site_prefix='" . qa_db_escape_string($to_site_prefix) . "',
							is_to_blog=" . (int)$is_to_blog . "
						WHERE meta_key='merged_with'
						  AND meta_value='" . (int)$frompostid . "'
						  AND site_prefix='" . qa_db_escape_string($from_site_prefix) . "'
						  AND is_to_blog = ".$is_from_blog."
					");
			}
		}
    }
}

//5. Delete the childrens first after that delete the post.
function delete_post_and_children($postid, $basetype) {
    if ($basetype === 'Q' || $basetype === 'A') {
        // Questions and Answers can have children
        $children = qa_db_read_all_assoc(
            qa_db_query_sub('SELECT postid, LEFT(type,1) AS basetype FROM ^posts WHERE parentid = # and LEFT(type,1) != \'N\'', $postid)
        );

        foreach ($children as $child) {
            delete_post_and_children($child['postid'], $child['basetype']);
        }   

    }
	if($basetype === 'Q')
	{
		//if you are reaching here means, all answers and comments of the question deleted. Now we need to delete notes of a question if exist.
		
		//remove the closeid for time-being as there is a circular constraint exist between closed note and question post
		qa_db_query_sub('UPDATE ^posts SET closedbyid = NULL WHERE postid = #', $postid);

        // Delete the note directly
        qa_db_query_sub('DELETE FROM ^posts WHERE parentid = # AND LEFT(type,1) = "N"', $postid);
	}

	qa_post_delete($postid);
}


function delete_and_redirect_linking($frompostid, $topostid, $from_site_prefix, $to_site_prefix, $is_from_blog=0, $is_to_blog=0){
	
	//This code is exclusively for the lists plugin. As we are trying to delete the first and then report the event, we will save what are the lists associated with the given $frompostid
	$table = $from_site_prefix . 'userquestionlists';
	$exists = qa_db_read_one_value(
		qa_db_query_raw("SHOW TABLES LIKE '" . qa_db_escape_string($table) . "'"),
		true
	);

	$oldpost_userquestionlists=[];
    if (!$is_from_blog && !$is_to_blog && $exists  && ($from_site_prefix === $to_site_prefix)) {
        $oldpost_userquestionlists = qa_db_read_all_assoc(
            qa_db_query_raw(
                "SELECT userid, listids 
                 FROM {$from_site_prefix}userquestionlists 
                 WHERE questionid = " . (int)$frompostid
            )
        );
    }
	
	//Delete the Post - we are trying to delete the post at the begining itself. So, if any error occured, the linkage, favorites table update and event report will not take place.
	if($is_from_blog){
		qas_blog_post_delete_recursive($frompostid);
	}
	else{
		delete_post_and_children($frompostid, "Q");
	}
	
	//linkage in postmeta table of the current site.
	qa_db_query_raw("
		INSERT INTO {$from_site_prefix}postmeta (post_id, meta_key, meta_value, site_prefix, is_from_blog, is_to_blog)
		VALUES ($frompostid, 'merged_with', '" . qa_db_escape_string($topostid) . "', '" . qa_db_escape_string($to_site_prefix) . "',". $is_from_blog. "," . $is_to_blog. ")
	");
	qa_network_update_merges($frompostid, $topostid, $from_site_prefix, $to_site_prefix, $is_from_blog, $is_to_blog);

	//Report this event. So, other plugins may read and update relevant tables.
	require_once QA_INCLUDE_DIR.'qa-app-posts.php';
	$from_table = $is_from_blog ? "{$from_site_prefix}blogs" : "{$from_site_prefix}posts";
	$postowner = qa_db_read_one_value(
		qa_db_query_raw("SELECT userid FROM {$from_table} WHERE postid=$frompostid"), true
	);
	qa_report_event("in_q_merge", $postowner, qa_get_logged_in_handle(), qa_cookie_get_create(), array(
		"postid"    => $topostid,
		"oldpostid" => $frompostid,
		"from_site" => $from_site_prefix,
		"to_site"   => $to_site_prefix,
		"is_from_blog"	=> $is_from_blog,
		"is_to_blog"	=>	$is_to_blog,
		"oldpost_userquestionlists"	=>	$oldpost_userquestionlists
	));
	
	// Update favorites
	
	// Determine entity types
	$from_entitytype = $is_from_blog ? 'P' : 'Q';
	$to_entitytype   = $is_to_blog   ? 'P' : 'Q';

	// --- Get all users who favorited the source post/blog
	$favoriters = qa_db_read_all_assoc(
		qa_db_query_raw("
			SELECT userid 
			FROM {$from_site_prefix}userfavorites
			WHERE entitytype = '$from_entitytype'
			  AND entityid = " . (int)$frompostid . "
		")
	);

	foreach ($favoriters as $f) {
		$userid = (int)$f['userid'];

		// --- Check if already favorited in target
		$exists = qa_db_read_one_value(
			qa_db_query_raw("
				SELECT COUNT(*)
				FROM {$to_site_prefix}userfavorites
				WHERE userid = $userid
				  AND entitytype = '$to_entitytype'
				  AND entityid = " . (int)$topostid . "
			"),
			true
		);

		if (!$exists) {
			// --- Insert into target favorites
			qa_db_query_raw("
				INSERT INTO {$to_site_prefix}userfavorites
					(userid, entitytype, entityid, nouserevents)
				VALUES
					($userid, '$to_entitytype', " . (int)$topostid . ", 0)
			");
		}

		// --- Remove old favorite from source
		qa_db_query_raw("
			DELETE FROM {$from_site_prefix}userfavorites
			WHERE userid = $userid
			  AND entitytype = '$from_entitytype'
			  AND entityid = " . (int)$frompostid . "
		");
	}
	return true;
}


/*
	Omit PHP closing tag to help avoid accidental output
*/
