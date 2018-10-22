#Homepage Canonical Tag

### Make Home Page Only Accessible in the Forum Document Root.

 * **Summary:** Make the Default Controller (or "Home Page") only be accessible via the Document Root ("/") of the forum for Search Engine Optimization purposes, and make the canonical tag reference the domain name only (no path). 
 * **Description:** Normally if, for example, Categories is set as the "Home Page", Vanilla will make that page accessible by "https://www.myforum.com/" or "https://www.myforum.com/categories". For SEO purposes, regardless of how the visitor arrives at the page, we set the Canonical Tag to:

 ```
 <link rel="canonical" href="https://www.myforum.com/categories">
 ```
 
 telling search engines that the content on `https://www.myforum.com/ categories` and `https://www.myforum.com/` are identical and should be indexed as `https://www.myforum.com/categories`.

 The same is true if Discussions is set as the "Home Page".

 This plugin will respond to any request to the Default Controller in the address bar with a 301 Permanently Moved header and redirect to the document root. There it will add a Canonical Tag on the home page with just the Document Root as the HREF value.
 
* **Use Case:** Satisfying special SEO request.
* **Configs set or added:** None.
* **Events Used:** `base_render_before` to detect the page requested, redirect and set the Canonical Tag.
* **QA steps:**
 1. Turn on plugin.
 2. In Dashboard > Settings > Layout, verify which Controller (Categories or Discussions) is set as the default.
 3. Visit the pubic facing forum.
 4. Verify that the Canonical Tag on the default page is set to "https://myforum.com/".
 5. Type the explicit address to the default forum (e.g. "https://myforum.com/categories").
 6. Verify that you are 301 redirected to https://myforum.com/ and that the Canonical Tag is set to https://myforum.com/.
 7. Type in other explicit addresses (e.g. "https://myforum.com/discussions") and verify that you are **not** redirected.
 8. In Dashboard > Settings > Layout change the default page and redo steps 3. through 7.
