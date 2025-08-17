<?php

class qa_html_theme_layer extends qa_html_theme_base {

// theme replacement functions
//This is the file which is actually redirecting
		
	function doctype() {
		// Check if page not found for numeric post request
		if(@$this->content['error'] == qa_lang_html('main/page_not_found') && preg_match('/^[0-9]+\//',$this->request) !== false) {
			$pid = preg_replace('/\/.*/', '', $this->request);

			// Get merged target info from postmeta
			$row = qa_db_read_one_assoc(
				qa_db_query_sub(
					"SELECT meta_value, site_prefix FROM ^postmeta WHERE meta_key='merged_with' AND post_id = #",
					$pid
				),
				true
			);

			if ($row) {
				$targetPostid = (int)$row['meta_value'];
				$targetPrefix = $row['site_prefix'];

				// Always get the title from the target site
				$merged = qa_db_read_one_assoc(
					qa_db_query_sub(
						"SELECT title FROM {$targetPrefix}posts WHERE postid = #",
						$targetPostid
					),
					true
				);

				$title = $merged ? $merged['title'] : null;
				// Get base URL for target site
				$baseUrl = qa_network_site_url($targetPrefix);

				// Build full URL and redirect
				qa_redirect_raw($baseUrl . "/".qa_q_request($targetPostid, $title) . '?merged=' . $pid);
				return;
			}
		}

		// Show merged message if merged GET parameter exists
		else if (qa_get('merged')) {
			$this->content['error'] = str_replace('^post', qa_get('merged'), qa_opt('merge_question_merged'));
		}

		// Return early for AJAX merge requests
		if (qa_post_text('ajax_merge_get_from')) {
			return;
		}

		qa_html_theme_base::doctype();
	}
}

