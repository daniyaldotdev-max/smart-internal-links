=== Smart Internal Links ===
Contributors: daniyaldotdev
Tags: internal links, seo, link building, automatic linking, internal linking
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Smart Internal Links automatically suggests and inserts relevant internal links in your posts to boost SEO and improve site navigation.

== Description ==

Smart Internal Links helps you automatically find and insert relevant internal links in your posts. It analyzes your content and suggests opportunities to link to other posts on your site based on keyword matches, giving your SEO a significant boost.

**Key Features:**

*   **Bulk Analysis Dashboard:** Analyze your entire website or a subset of posts (Latest 25, 50, 100) at once to find internal linking opportunities.
*   **Smart Link Detection:** Automatically finds phrases in your content that match other post titles.
*   **One-Click Linking:** Insert internal links directly from the dashboard with a single click.
*   **Post Editor Integration:** Use the meta box in the post editor to analyze and link individually while writing.
*   **Two-Tab Management:** Separate tabs for "Available Links" (suggestions) and "Added Links" (history).
*   **Intelligent Matching:** Prioritizes strong 3-word matches over 2-word matches for better relevance.
*   **Clean Database:** Stores only the best suggestion per post to keep your database optimized.

**How It Works:**

1.  **Analyze:** The plugin scans your posts to find phrases that match the titles of other published posts.
2.  **Suggest:** It presents the best linking opportunity for each post, showing the keyword, source post, and target post.
3.  **Link:** You review the suggestion and click "Add Link" to automatically insert the link into your content.

== Installation ==

1.  Upload the `smart-internal-links` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to **Posts > Internal Links** in your admin dashboard.
4.  Click **Analyze Website** to start finding internal links!

== Frequently Asked Questions ==

= Does it link automatically? =
No, the plugin suggests links, but you have full control. You must click "Add Link" to insert them. This ensures you only add relevant links.

= Can I choose which posts to analyze? =
Yes! You can choose to analyze the latest 25, 50, 100 posts, or your entire library at once from the dashboard.

= Where can I find the settings? =
The plugin adds a submenu under **Posts > Internal Links**. All operations happen there or inside the post editor meta box.

= Does it slow down my site? =
No. The textual analysis is performed on-demand via AJAX in the admin dashboard, so it does not affect your front-end site performance.

== Screenshots ==

1.  **Dashboard Overview** - The main dashboard showing statistics and available link suggestions.
2.  **Bulk Analysis** - The progress bar showing real-time analysis of your website's posts.
3.  **Post Editor Meta Box** - The integration within the Gutenberg/Classic editor for individual post analysis.

== Changelog ==

= 1.2 =
*   Fixed: WordPress Plugin Directory compliance issues
*   Changed: Prefix from 'sil' to 'smartinlinks' for better uniqueness (4+ character requirement)
*   Changed: Database table from `wp_sil_suggestions` to `wp_smartinlinks_suggestions`
*   Improved: Input validation and sanitization with wp_unslash()
*   Improved: SQL query security with proper prepared statements
*   Fixed: Contributors field to match WordPress.org username

= 1.1 =
*   Added "Limit Posts" feature to analyze latest 25, 50, or 100 posts.
*   Moved admin menu to "Posts > Internal Links".
*   Enhanced dashboard UI with modern design and responsive layout.
*   Added database table `wp_sil_suggestions` for better performance.
*   Implemented bulk analysis with progress bar.

= 1.0 =
*   Initial release.
