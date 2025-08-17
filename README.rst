Question2Answer Merge:

This is a plugin for **Question2Answer** that allows admin to merge questions.

This plugin requires modification in core file qa-include/pages/question-post.php. In that file, we need to make the function qa_page_q_close_q_submit($question, $closepost, &$in, &$errors) overridable.


Installation:

1. Copy the plugin folder into your Q2A `qa-plugin` directory.
2. **Modify the core file** `qa-include/pages/question-post.php`:
   - Locate the function:qa_page_q_close_q_submit($question, $closepost, &$in, &$errors)
   - Add the line **as the first line of the function**: "if (qa_to_override(__FUNCTION__)) { $args=func_get_args(); return qa_call_override(__FUNCTION__, $args); }"
3. Go to Plugin section and enable it.

Features:
- moves comments and answers from one question to another (even for network sites), then deletes the first question based on the user choice.
- shows merge dialog either on duplicate questions or the closed note contain network site url 
- store redirect info for deleted questions in postmeta with the network site_url.
- ajax call to show which posts are affected

Disclaimer: 
This is **alpha** code.  It may not work as expected.  It may corrupt your data.  Refunds will not be given.  If it breaks, you get to keep both parts.

Release : All code herein is Copylefted_(http://en.wikipedia.org/wiki/Copyleft).
