<?php

class qa_html_theme_layer extends qa_html_theme_base {

// theme replacement functions
//This is the file which is actually redirecting
		
	function doctype() {
		// Check if page not found for numeric post request
		if(@$this->content['error'] == qa_lang_html('main/page_not_found') && (preg_match('/^([0-9]+)(?:\/|$)/', $this->request) > 0 || preg_match('/^blog\/([0-9]+)(?:\/|$)/', $this->request) > 0)) {
			
			$pid = null;
			$is_from_blog = 0;

			// --- Detect request type (Question vs Blog)
			if (preg_match('/^([0-9]+)(?:\/|$)/', $this->request, $matches)) {
				// Example: "14" or "14/asasas" or "14/"
				$pid = (int)$matches[1];
				$is_from_blog = 0;

			} elseif (preg_match('/^blog\/([0-9]+)(?:\/|$)/', $this->request, $matches)) {
				// Example: "blog/14" or "blog/14/sometitle" or "blog/14/"
				$pid = (int)$matches[1];
				$is_from_blog = 1;
			}

			// error_log("request={$this->request}, pid={$pid}, is_from_blog={$is_from_blog}");

			// Get merged target info from postmeta
			$row = qa_db_read_one_assoc(
				qa_db_query_sub(
					"SELECT meta_value, site_prefix, is_to_blog FROM ^postmeta WHERE meta_key='merged_with' AND post_id = # AND is_from_blog= #",
					$pid,$is_from_blog
				),
				true
			);

			if ($row) {
				$targetPostid = (int)$row['meta_value'];
				$targetPrefix = $row['site_prefix'];
				$target_is_Blog = (int)$row['is_to_blog'];
				$targetTable = "{$targetPrefix}posts";
				if($target_is_Blog){
					$targetTable = "{$targetPrefix}blogs";
				}

				// Always get the title from the target site
				$merged = qa_db_read_one_assoc(
					qa_db_query_sub(
						"SELECT title FROM {$targetTable} WHERE postid = #",
						$targetPostid
					),
					true
				);

				$title = $merged ? $merged['title'] : null;
				// Get base URL for target site
				$baseUrl = qa_network_site_url($targetPrefix);
				
				if($target_is_Blog){
					$baseUrl = $baseUrl."/blog";
				}

				// Build full URL and redirect
				qa_redirect_raw($baseUrl . "/".qa_q_request($targetPostid, $title) . '?merged=' . $pid);
				return;
			}
		}

		// Show merged message if merged GET parameter exists
		else if (qa_get('merged')) {
			$this->content['error'] = str_replace('^post', qa_get('merged'), qa_opt('merge_question_merged'));
		}
		if (qa_get('status') === 'copy_done') {
			$this->content['success'] = 'Copy completed successfully!';
		}

		// Return early for AJAX merge requests
		if (qa_post_text('ajax_merge_get_from')) {
			return;
		}

		qa_html_theme_base::doctype();
	}
}

