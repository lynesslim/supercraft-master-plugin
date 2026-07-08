# Multi-Taxonomy Checklists for Publishing Components

Please modify the Supercraft Master Plugin to implement checklists and "Add New" inputs for all three taxonomies: Categories, Tags, and Requirements when pushing components to SuperVault.

## Technical Requirements

### 1. Update Local PHP Proxy (`supercraft-master-plugin.php`)
- Inside `scmp_ajax_supervault_proxy`, add support for proxying `/tags` and `/requirements` endpoints:
  - **`tags` action**: forwards GET request to `https://library.supercraft.my/wp-json/supervault/v1/tags`.
  - **`requirements` action**: forwards GET request to `https://library.supercraft.my/wp-json/supervault/v1/requirements`.

### 2. Multi-Taxonomy UI in Publish Modal (JS + CSS)
In `assets/editor-connector.js` and `assets/editor-connector.css`:
- Inside `publishModalHtml`, replace the text input field for Tags and the text input field for Requirements with:
  - **Tags**: A scrollable checkbox list container (`#scmp-publish-tags-list`) displaying existing tags, followed by a text input: "Or create new tags (comma-separated)" (`#scmp-publish-new-tags`).
  - **Requirements**: A scrollable checkbox list container (`#scmp-publish-reqs-list`) displaying existing requirements, followed by a text input: "Or create new requirements (comma-separated)" (`#scmp-publish-new-reqs`).
- Restyle the CSS so all three lists (Categories, Tags, Requirements) have scrollable containers (`max-height: 100px; overflow-y: auto; border: 1px solid #3a3c42; padding: 8px; border-radius: 4px; background: #1a1b1e; margin-bottom: 6px;`).

### 3. Load & Render Existing Taxonomies (JS)
In `assets/editor-connector.js`:
- In `openPublishForm(view)`:
  - Trigger loading of Categories, Tags, and Requirements by making concurrent proxy requests:
    - `proxyRequest('categories', ...)` ➔ renders to `#scmp-publish-categories-list`.
    - `proxyRequest('tags', ...)` ➔ renders to `#scmp-publish-tags-list`.
    - `proxyRequest('requirements', ...)` ➔ renders to `#scmp-publish-reqs-list`.
  - Ensure loaders are handled cleanly.

### 4. Combine Checked and Newly Created Items (JS)
In `submitPublishForm()`:
- **Categories**: Collect all checked categories + split/trim custom category text input.
- **Tags**: Collect all checked tags + split/trim custom tags text input.
- **Requirements**: Collect all checked requirements + split/trim custom requirements text input.
- Send all three combined arrays in the payload to the local proxy.
