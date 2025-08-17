<?php

class qa_html_theme_layer extends qa_html_theme_base
{
	//This function handle the action once the merging form submitted
    function doctype()
    {
        if (qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN && qa_post_text('merge_from')) {
			$frompostid = (int)qa_post_text('merge_from');
			$topostid = (int)qa_post_text('merge_to');
            $tositeprefix = qa_post_text('merge_to_site');
            
			// Call updated merge function with all required parameters
            $merged = qa_merge_do_merge(
                $frompostid,
                $topostid,
                "",
                $tositeprefix,
                isset($_POST['force_source_title']),
                isset($_POST['force_source_tags']),
				isset($_POST['keep_original_post'])
            );

            if ($merged === true) {
				qa_opt('merge_question_merged', qa_post_text('merge_question_merged'));

                // Redirect using unified helper
				$baseUrl = qa_network_site_url($tositeprefix);

				// Build full URL and redirect
				qa_redirect_raw($baseUrl . "/".qa_q_request($topostid, null) . '?merged=' . $frompostid);

            } else {
                $this->content['error'] = "Error merging posts.";
            }
        }

        qa_html_theme_base::doctype();
    }

	// override q_view_clear to add merge-button
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
			//error_log("Check1");
			$closedbyid = $this->content['q_view']['raw']['closedbyid'];

			// 1. Check if same-site duplicate
			$duplicate = qa_db_read_one_assoc(
				qa_db_query_sub(
					'SELECT postid, title FROM ^posts WHERE postid = # AND type="Q"',
					$closedbyid
				),
				true
			);

			if ($duplicate) {
				//error_log("Check2");
				$merge_to_postid = $duplicate['postid'];
				$target_prefix = QA_MYSQL_TABLE_PREFIX;
			}
			// 2. If not same-site duplicate, check cross-site note
			else {
				//error_log("Check3");
				$details = $this->content['q_view']['closed']["content"];
				//error_log($details);
				if ($details) {
					foreach (qa_network_sites_list() as $site) {
						$siteUrl = $site['url'];
						if (strpos($details, $siteUrl) === 0) {
							//error_log("Check4");
							if (preg_match('#' . preg_quote($siteUrl, '#') . '/([0-9]+)/?#', $details, $matches)) {
								$merge_to_postid = (int)$matches[1];
								$target_prefix = $site['prefix'];
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

				// Output merge form
				$this->output('
					<FORM METHOD="POST">
					<TABLE>
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
						</TR>
						<TR>
							<TD CLASS="qa-form-tall-label">
								Text to show when redirecting from merged question:
							</TD>
						</TR>
						<TR>
							<TD CLASS="qa-form-tall-label">
								<INPUT NAME="merge_question_merged" id="merge_question_merged" TYPE="text" VALUE="' . qa_opt('merge_question_merged') . '" CLASS="qa-form-tall-text">
							</TD>
						</TR>
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
						</TR>
						<TR>
							<TD CLASS="qa-form-tall-label">
								Keep the post in the site (only in case of cross site merge): 
								<INPUT NAME="keep_original_post" TYPE="checkbox" VALUE="" CLASS="qa-form-tall-text">
							</TD>
						</TR>
						<TR>
							<TD style="text-align:right;">
								<INPUT NAME="merge_question_process" VALUE="Merge" TITLE="" TYPE="submit" CLASS="qa-form-tall-button qa-form-tall-button-0">
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
