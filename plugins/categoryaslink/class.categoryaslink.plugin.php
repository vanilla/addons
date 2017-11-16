<?php
/**
 * @copyright 2009-2017 Vanilla Forums Inc.
 * @license proprietary
 */


/**
 * Class CategoryAsLinkPlugin
 *
 * A plugin to allow admins to create a "category" that will not have any posts but will serve as a link.
 */
class CategoryAsLinkPlugin extends Gdn_Plugin {

    /**
     * Executes every time the plugin is turned on, makes changes to config.
     */
    public function setup() {
        $this->structure();
    }


    /**
     * Add columns to the Category table to store LinkedDiscussion URLs.
     */
    public function structure() {
        Gdn::structure()->table('Category')
            ->column('LinkedDiscussion', 'varchar(255)', true)
            ->set();
    }


    /**
     * On the category add/edit page the dashboard, add a textbox to link a category to some other URI (e.g. a discussion).
     *
     * @param SettingsController $sender
     * @param SettingsController $args
     */
    public function settingsController_addEditCategory_handler($sender, $args) {
        $sender->Data['_ExtendedFields']['LinkedDiscussion'] = [
            'Control' => 'TextBox',
            'Description' => 'Act as a link to a discussion, another category or any URL. Paste the full URL of the address.',
            'Options' => ['IncludeNull' => 'None']
        ];

        $sender->Form->validateRule('LinkedDiscussion', 'validateWebAddress', t('Linked Discussion must be a valid Web Address'));
        if ($sender->Form->getFormValue('LinkedDiscussion')) {
            // Has to be displayed as Discussions for the BeforeCategoriesRender
            // to redirect any requests to the category.
            $sender->Form->setFormValue('DisplayAs', 'Discussions');
        }
    }


    /**
     * Loop through all the categories and prepare data to display any LinkedDiscussions.
     *
     * @param CategoriesController $sender
     * @param CategoriesController $args
     */
    public function categoriesController_render_before($sender, $args) {
        if (!isset($sender->Data['CategoryTree'])
            || !is_array($sender->Data['CategoryTree'])
            || !is_array(reset($sender->Data['CategoryTree']))) {
            return;
        }

        $categories =& $sender->Data['CategoryTree'];

        foreach ($categories as &$category) {
            if (!val('LinkedDiscussion', $category)) {
                continue;
            }

            if (val('LinkedDiscussion', $category)) {
                // Linked discussion.
                // Add CSS classes in case someone wants to display Aliased Categories differently.
                $category['_CssClass'] = 'Aliased AliasedDiscussion';
                $category['LastTitle'] = null; // Set to null so that no LastDiscussion info will be displayed.
                $category['CountAllDiscussions'] = false; // Set to false so that now Count info will be displayed.
                $category['CountAllComments'] = false;
                $category['Linked'] = true;
            }
        }
    }


    /**
     * Redirect any request to a category that is aliased to a discussion or other URL.
     *
     * @param CategoriesController $sender
     * @param CategoriesController $args
     */
    public function categoriesController_beforeCategoriesRender_handler($sender, $args) {
        if ($sender->data('Category.LinkedDiscussion')) {
            redirectTo(categoryUrl($sender->Data('Category')), 301);
        }
    }
}


if (!function_exists("categoryUrl")) {

   /**
    * Override links to categories with either the URL of the LinkedCategory or LinkedDiscussion.
    *
    * @param string | array $category
    * @param string | int $page The page number.
    * @param bool $withDomain Whether to add the domain to the URL
    * @return string The url to a category.
    */
    function categoryUrl($category, $page = '', $withDomain = true) {
        static $px;
        if (is_string($category)) {
            $category = CategoryModel::categories($category);
        }
        $category = (array)$category;

        // If there is a URL that links to a discussion it overrides the category or even a link to an alias category.
        if (val('LinkedDiscussion', $category)) {
            // SafeURL because you may be linking to another web property, another forum or knowledgebase.
            return safeURL(val('LinkedDiscussion', $category));
        }

        // Build URL.
        if (class_exists('SEOLinksPlugin')) {
            // SEOLinks version
            if (!isset($px)) {
                $px = SEOLinksPlugin::Prefix();
            }
            $result = '/' . $px . rawurlencode($category['UrlCode']) . '/';
            if ($page && $page > 1) {
                $result .= 'p' . $page . '/';
            }
        } else {
            // Normal version
            $result = '/categories/'.rawurlencode($category['UrlCode']);
            if ($page && $page > 1) {
                $result .= '/p'.$page;
            }
        }

        return url($result, $withDomain);
    }
}
