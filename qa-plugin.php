<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

qa_register_plugin_layer('qa-merge-layer.php', 'Merge Layer');

qa_register_plugin_module('module', 'qa-php-widget.php', 'qa_merge_admin', 'Merge Plugin');

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



// 3. Actual function which is performing Merging of the question
function qa_merge_do_merge($frompostid, $topostid, $fromsiteprefix, $tositeprefix, $force_source_title, $force_source_tags, $keep_original_post)
{
    if (empty($fromsiteprefix)) $fromsiteprefix = QA_MYSQL_TABLE_PREFIX;
    if (empty($tositeprefix))   $tositeprefix   = QA_MYSQL_TABLE_PREFIX;
	
	// On same-site merge, source post is always removed to avoid duplicates
	if($fromsiteprefix == $tositeprefix)
		$keep_original_post=false;

    // --- Fetch posts from correct sites
    $titles_from = qa_db_read_all_assoc(
        qa_db_query_raw("
            SELECT postid,title,acount,selchildid,tags 
            FROM {$fromsiteprefix}posts 
            WHERE postid=$frompostid
        ")
    );
    $titles_to = qa_db_read_all_assoc(
        qa_db_query_raw("
            SELECT postid,title,acount,selchildid,tags 
            FROM {$tositeprefix}posts 
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
	//$now    = qa_db_format_datetime(qa_now());

    // --- If same site
    if ($fromsiteprefix === $tositeprefix) {
        qa_db_query_raw("UPDATE {$tositeprefix}posts 
						SET parentid = $topostid,
							updated = NOW(),
							updatetype = 'M',
							lastuserid = " . $userid . "
						WHERE parentid = $frompostid");

        if (empty($to_post['selchildid']) && !empty($from_post['selchildid'])) {
            qa_db_query_raw("UPDATE {$tositeprefix}posts 
							SET selchildid = " . (int)$from_post['selchildid'] . ",
								updated = NOW(),
								updatetype = 'M',
								lastuserid = " . $userid  . "
							WHERE postid = $topostid");
        }

        qa_db_query_raw("
            DELETE uf1
            FROM {$tositeprefix}userfavorites uf1
            JOIN {$tositeprefix}userfavorites uf2
              ON uf1.userid = uf2.userid
             AND uf1.entitytype = uf2.entitytype
             AND uf1.entityid = $frompostid
             AND uf2.entityid = $topostid
        ");
        qa_db_query_raw("UPDATE {$tositeprefix}userfavorites SET entityid=$topostid WHERE entityid=$frompostid");

    } else {
        // --- Cross-site merge
        $children = qa_db_read_all_assoc(
            qa_db_query_raw("SELECT * FROM {$fromsiteprefix}posts WHERE parentid=$frompostid")
        );

        $idmap = [];

        foreach ($children as $child) {
			// Skip notes
			if (substr($child['type'], 0, 1) === 'N') {
				continue;
			}
			//$categoryid = $child['categoryid'] ?? NULL;
			qa_db_query_raw(
				"INSERT INTO {$tositeprefix}posts (
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
            qa_db_query_raw("UPDATE {$tositeprefix}posts SET selchildid=$new_sel WHERE postid=$topostid");
        }

        $favs = qa_db_read_all_assoc(
            qa_db_query_raw("SELECT * FROM {$fromsiteprefix}userfavorites WHERE entitytype='Q' AND entityid=$frompostid")
        );
        foreach ($favs as $fav) {
            $exists = qa_db_read_one_value(
                qa_db_query_raw("
                    SELECT COUNT(*) FROM {$tositeprefix}userfavorites
                    WHERE userid=" . (int)$fav['userid'] . " AND entitytype='Q' AND entityid=$topostid
                "), true
            );
            if (!$exists) {
                qa_db_query_raw("
                    INSERT INTO {$tositeprefix}userfavorites (userid, entitytype, entityid, noused)
                    VALUES (" . (int)$fav['userid'] . ", 'Q', $topostid, 0)
                ");
            }
        }
    }

    // --- Common updates ---
    $acount = (int)$from_post['acount'] + (int)$to_post['acount'];
    qa_db_query_raw("UPDATE {$tositeprefix}posts 
						SET acount=$acount,
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");

    if ((empty($to_post['tags']) && !empty($from_post['tags'])) || $force_source_tags) {
        qa_db_query_raw("UPDATE {$tositeprefix}posts 
						SET tags='" . qa_db_escape_string($from_post['tags']) . "',
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");
    }

    if ($force_source_title) {
        qa_db_query_raw("UPDATE {$tositeprefix}posts 
						SET title='" . qa_db_escape_string($from_post['title']) . "',
							updated=Now(),
							updatetype='M',
							lastuserid=".(int)$userid."
						WHERE postid=$topostid");
    }

    // --- Insert merged_with info using site_prefix column. This should happen only when $keep_original_post is false.
    if(!$keep_original_post){
		qa_db_query_raw("
			INSERT INTO {$fromsiteprefix}postmeta (post_id, meta_key, meta_value, site_prefix)
			VALUES ($frompostid, 'merged_with', '" . qa_db_escape_string($topostid) . "', '" . qa_db_escape_string($tositeprefix) . "')
		");
		qa_network_update_merges($frompostid, $topostid, $fromsiteprefix, $tositeprefix);
	
		require_once QA_INCLUDE_DIR.'qa-app-posts.php';

		// --- Report event
		$postowner = qa_db_read_one_value(
			qa_db_query_raw("SELECT userid FROM {$fromsiteprefix}posts WHERE postid=$frompostid"), true
		);
		qa_report_event("in_q_merge", $postowner, qa_get_logged_in_handle(), qa_cookie_get_create(), array(
			"postid"    => $topostid,
			"oldpostid" => $frompostid,
			"from_site" => $fromsiteprefix,
			"to_site"   => $tositeprefix,
		));

		// --- Delete close notifications
		qa_db_query_raw("DELETE FROM {$fromsiteprefix}eventlog WHERE event LIKE 'in_q_close' AND params LIKE 'postid=$frompostid%'");

		// --- Delete the source post if keep post is not selected
			if ($fromsiteprefix === $tositeprefix)
				qa_post_delete($frompostid);
			else			
				delete_post_and_children($frompostid, 'Q'); // We need to delete all it's child posts then delete the posts.
	}

    return true;
}


//4. When a question 'x' merges with 'y', and 'y' merges with 'z', update the merge table 
function qa_network_update_merges($frompostid, $topostid, $fromsiteprefix, $tositeprefix) {
    $sites = qa_network_sites_list();
    foreach ($sites as $site) {
		 $prefix = $site['prefix'];
        qa_db_query_raw("
            UPDATE {$prefix}postmeta
            SET meta_value='" . (int)$topostid . "', site_prefix='" . qa_db_escape_string($tositeprefix) . "' 
            WHERE meta_key='merged_with'
              AND meta_value='" . (int)$frompostid . "'
              AND site_prefix='" . qa_db_escape_string($fromsiteprefix) . "'
        ");
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





/*
	Omit PHP closing tag to help avoid accidental output
*/
