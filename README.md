# Question2Answer Merge Plugin

This plugin adds advanced **question and blog post management** features to **Question2Answer (Q2A)**.  
Admins can **Copy, Merge, or Only Redirect** questions and blog posts — including across networked Q2A sites.  

**Important:** This plugin requires a minor modification in Q2A core to allow overriding of the `qa_page_q_close_q_submit()` function. (Instructions below)

---

## Installation

1. Copy this plugin folder into your Q2A `/qa-plugin/` directory.  
2. Modify the core file `qa-include/pages/question-post.php`:  
   - Locate the function:  
     ```php
     function qa_page_q_close_q_submit($question, $closepost, &$in, &$errors)
     ```
   - Add this line as the **first line inside the function**:  
     ```php
     if (qa_to_override(__FUNCTION__)) { 
         $args = func_get_args(); 
         return qa_call_override(__FUNCTION__, $args); 
     }
     ```
3. In the Q2A Admin Panel → Plugins, enable **Q2A Merge**.  

---
## Note
- Copy/Merge/Only Redirect dialog appears automatically when:  
  - A question is marked as duplicate, OR  
  - The "closed note" contains a network site URL.   
---
## Features

### 1. **Copy (Q → Q only)**  
- Copies all **child posts** (answers, comments) from one question into another question.  
- The original question remains intact.   

### 2. **Merge (Q → Q only)**  
- Moves all **child posts** into the target post.  
- Deletes the original question post after merging.  
- Creates a redirect from the old post to the new one.
- Update Favorites Table.  

### 3. **Only Redirect (Q → Q, Q → B, B → Q, B → B)**  
- Deletes the original post (including all child posts).  
- Creates a redirect link from the old post to the new one.
- Update Favorites Table.  

---
## Network Site Support
- Works across multiple Q2A sites in a **network setup**.  
- Moves/merges/redirects posts even if the target post is on another site.  
- Redirect information is stored in `postmeta` with the target `site_prefix` for accurate cross-site linking.  

---


## Credits
This plugin original version is developed by NoahY and available at https://github.com/NoahY/q2a-post-merge
