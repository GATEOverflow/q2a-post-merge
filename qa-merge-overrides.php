<?php

function qa_page_q_close_q_submit($question, $closepost, &$in, &$errors)
{
	$in = array(
		'details' => trim((string)qa_post_text('q_close_details')),
	);

	$userid = qa_get_logged_in_userid();
	$handle = qa_get_logged_in_handle();
	$cookieid = qa_cookie_get();

	$sanitizedUrl = filter_var($in['details'], FILTER_SANITIZE_URL);
	$isduplicateurl = filter_var($sanitizedUrl, FILTER_VALIDATE_URL);

	if (!qa_check_form_security_code('close-' . $question['postid'], qa_post_text('code'))) {
		$errors['details'] = qa_lang_html('misc/form_security_again');
	} elseif ($isduplicateurl) {
		$siteUrl = rtrim(qa_opt('site_url'), '/');
		$sanitizedUrl = rtrim($sanitizedUrl, '/');

		// Same site only if exactly equal
		$isSameSite = strpos($sanitizedUrl, $siteUrl . '/') === 0;
		$isblog = strpos($sanitizedUrl, $siteUrl . '/blog/') === 0;
		
		//error_log("site url: ".$siteUrl." --- sanitizedUrl: ".$sanitizedUrl." --- is same site:".$isSameSite."\n is blog : ". $isblog);
		
		if(!$isSameSite || $isblog){ //This function is override for only adding this if block.
			qa_question_close_other($question, $closepost, $in['details'], $userid, $handle, $cookieid);
			return true;
		}
		// be liberal in what we accept, but there are two potential unlikely pitfalls here:
		// a) URLs could have a fixed numerical path, e.g. http://qa.mysite.com/1/478/...
		// b) There could be a question title which is just a number, e.g. http://qa.mysite.com/478/12345/...
		// so we check if more than one question could match, and if so, show an error

		$parts = preg_split('|[=/&]|', $sanitizedUrl, -1, PREG_SPLIT_NO_EMPTY);
		$keypostids = array();

		foreach ($parts as $part) {
			if (preg_match('/^[0-9]+$/', $part))
				$keypostids[$part] = true;
		}

		$questionids = qa_db_posts_filter_q_postids(array_keys($keypostids));

		if (count($questionids) == 1 && $questionids[0] != $question['postid']) {
			qa_question_close_duplicate($question, $closepost, $questionids[0], $userid, $handle, $cookieid);
			return true;

		} else
			$errors['details'] = qa_lang('question/close_duplicate_error');

	} else {
		if (strlen($in['details']) > 0) {
			qa_question_close_other($question, $closepost, $in['details'], $userid, $handle, $cookieid);
			return true;

		} else
			$errors['details'] = qa_lang('main/field_required');
	}


    return false;
}



?>
