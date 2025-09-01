<?php

class qa_html_theme_layer extends qa_html_theme_base
{
	//This function handle the action once the merging form submitted
    function doctype()
    {
		if(qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN && (qa_post_text('copy_question_process') || qa_post_text('merge_question_process') || qa_post_text('link_question_process'))){
			$from_postid = (int)qa_post_text('merge_from');
			$to_postid = (int)qa_post_text('merge_to');
			$from_site_prefix = qa_post_text('merge_from_site');
			$to_site_prefix = qa_post_text('merge_to_site');
			$is_from_blog = (int)qa_post_text('is_from_blog');
			$is_to_blog = (int)qa_post_text('is_to_blog');
			$force_source_title = isset($_POST['force_source_title']);
            $force_source_tags = isset($_POST['force_source_tags']);
			$action;
			 	
			if(qa_post_text('copy_question_process')){
				$action="copy";
			}
			else if(qa_post_text('link_question_process')){
				$action="redirect";
			}
			else if(qa_post_text('merge_question_process')){
				$action="merge";
			}		
			/*error_log("from site : ".$from_site_prefix." - ".
				"from is blog : ".$is_from_blog." - ".
				"from post id : ".$from_postid." - ".
				"to site : ".$to_site_prefix." - ".
				"to is blog : ".$is_to_blog." - ".
				"to post id : ".$to_postid." - ".
				"force_source_tags : ".$force_source_tags." - ".
				"force_source_title : ".$force_source_title." - ".
				"action : ".$action
				);*/			
			if($action === "copy" || $action === "merge"){
				// Call updated merge function with all required parameters
				$operation = qa_copy_or_merge($from_postid, $to_postid, $from_site_prefix, $to_site_prefix, $force_source_title, $force_source_tags, $action);
			}
			if($action === "merge" || ($action === "redirect" && $operation = true ) )
			{
				if($operation === true)
					$operation = delete_and_redirect_linking($from_postid, $to_postid, $from_site_prefix, $to_site_prefix, $is_from_blog, $is_to_blog);
			}

            if ($operation === true && $action != "copy") {
				// Redirect using unified helper
				$baseUrl = qa_network_site_url($to_site_prefix);
				if($is_to_blog){
					$baseUrl = $baseUrl."/blog";
				}
				//error_log("base url = ".$baseUrl);
				
				// Build full URL and redirect
				qa_redirect_raw($baseUrl . "/".qa_q_request($to_postid, null) . '?merged=' . $from_postid);

            }
			else if ($operation === true && $action === "copy") {
				$this->content['success'] = 'Copy completed successfully!';
			}
			else {
                $this->content['error'] = "Error merging posts.";
            }
        }

        qa_html_theme_base::doctype();
    }

	//This function check when to display the merging form.

	function q_view_clear()
	{
		qa_html_theme_base::q_view_clear();

		if (qa_get_logged_in_level() < QA_USER_LEVEL_ADMIN) {
			return;
		}

		$closed = (@$this->content['q_view']['raw']['closedbyid'] !== null);
		$merge_to_postid = '';
		$target_prefix = QA_MYSQL_TABLE_PREFIX;

		if ($closed) {
			$closedbyid = $this->content['q_view']['raw']['closedbyid'];
			$targetTable = "{$target_prefix}posts";
			
			$is_from_blog = ($this->content['q_view']['raw']['type'] === 'B');
			if($is_from_blog)
				$targetTable = "{$target_prefix}blogs";


			// 1. Check if same-site duplicate
			$duplicate = qa_db_read_one_assoc(
				qa_db_query_sub(
					"SELECT postid, title, type FROM {$targetTable} WHERE postid = #",
					$closedbyid
				),
				true
			);

			if ($duplicate && $duplicate['type'] === 'Q') {
				$merge_to_postid = $duplicate['postid'];
				$target_prefix = QA_MYSQL_TABLE_PREFIX;
				$is_to_blog = 0;
			}
			// 2. If not same-site duplicate, check cross-site note
			else {
				$details = $this->content['q_view']['closed']["content"] ?? '';
				if ($details) {
					foreach (qa_network_sites_list() as $site) {
						$siteUrl = $site['url'];
						if (strpos($details, $siteUrl) === 0) {
							// Normal Q
							if (preg_match('#' . preg_quote($siteUrl, '#') . '/([0-9]+)/?#', $details, $matches)) {
								$merge_to_postid = (int)$matches[1];
								$target_prefix = $site['prefix'];
								$is_to_blog = 0;
								break;
							}
							// Blog Q
							elseif (preg_match('#' . preg_quote($siteUrl, '#') . '/blog/([0-9]+)/?#', $details, $matches)) {
								$merge_to_postid = (int)$matches[1];
								$target_prefix = $site['prefix'];
								$is_to_blog = 1;
								break;
							}
						}
					}
				}
			}

			if ($merge_to_postid) {
				$this->output('<div id="mergeDup" style="margin:10px 0 0 120px;padding:5px 10px;background:#FCC;border:1px solid #AAA;"><h3>Merge Duplicate:</h3>');

				// Build site dropdown dynamically
				$site_options = '';
				foreach (qa_network_sites_list() as $site) {
					$selected = ($site['prefix'] === $target_prefix) ? ' selected' : '';
					$site_options .= '<option value="' . qa_html($site['prefix']) . '"' . $selected . '>' . qa_html($site['title']) . '</option>';
				}

				// From-side details
				$from_site = QA_MYSQL_TABLE_PREFIX;
				$is_from_blog = ($this->content['q_view']['raw']['type'] === 'B');
				
				// To-side details (already computed)
				$to_site = $target_prefix;

/*  				error_log("from site : ".$from_site);
				error_log("from is blog : ".$is_from_blog);
				error_log("from post id : ".$this->content['q_view']['raw']['postid']);
				error_log("to site : ".$to_site);
				error_log("to is blog : ".$is_to_blog);
				error_log("to post id : ".$merge_to_postid); */ 


				// Decide buttons			
				$buttons = [];

				if (!$is_from_blog && !$is_to_blog) {
					// Q → Q
					$buttons[] = '<input name="copy_question_process" value="Copy Children" type="submit" class="qa-form-tall-button">';
					$buttons[] = '<input name="merge_question_process" value="Merge and Redirect" type="submit" class="qa-form-tall-button">';
					$buttons[] = '<input name="link_question_process" value="Only Redirect" type="submit" class="qa-form-tall-button">';
				} else {
					// At least one is a blog → only link
					$buttons[] = '<input name="link_question_process" value="Only Redirect" type="submit" class="qa-form-tall-button" >';
				}


				// Output merge form
				$this->output('
					<FORM METHOD="POST">
					<TABLE>
						<INPUT TYPE="hidden" NAME="is_to_blog" id="is_to_blog" VALUE="' . $is_to_blog . '">
						<INPUT TYPE="hidden" NAME="is_from_blog" id="is_from_blog" VALUE="' . $is_from_blog . '">
						<INPUT TYPE="hidden" NAME="merge_from_site" id="merge_from_site" VALUE="' . $from_site . '">
						<TR>
							<TD CLASS="qa-form-tall-label">
								From: &nbsp;
								<INPUT NAME="merge_from" id="merge_from" TYPE="text" VALUE="' . $this->content['q_view']['raw']['postid'] . '" CLASS="qa-form-tall-number">
								&nbsp; To: &nbsp;
								<INPUT NAME="merge_to" id="merge_to" TYPE="text" VALUE="' . $merge_to_postid . '" CLASS="qa-form-tall-number">
							</TD>
						</TR>
						<TR>
							<TD CLASS="qa-form-tall-label">
								Merge into site: 
								<SELECT NAME="merge_to_site" id="merge_to_site">' . $site_options . '</SELECT>
							</TD>
						</TR>'. (
            (!$is_from_blog && !$is_to_blog) ? '
						<TR>
							<TD CLASS="qa-form-tall-label">
								Force title of source post: 
								<INPUT NAME="force_source_title" TYPE="checkbox" VALUE="" CLASS="qa-form-tall-text">
							</TD>
						</TR>
						<TR>
							<TD CLASS="qa-form-tall-label">
								Force tags of source post: 
								<INPUT NAME="force_source_tags" TYPE="checkbox" VALUE="" CLASS="qa-form-tall-text">
							</TD>
						</TR> ' : ''
        ) . '
						<TR>
							<TD style="text-align:right;">
								' . implode(' ', $buttons) . '
							</TD>
						</TR>
					</TABLE>
					</FORM>
				</div>');
			}
		}
	}
}

/* Omit PHP closing tag to avoid accidental output */