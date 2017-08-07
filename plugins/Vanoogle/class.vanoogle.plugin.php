<?php if (!defined("APPLICATION")) exit();
/*
 *  Vanoogle vanilla plugin.
 *  Copyright (C) 2011 ddumont@gmail.com
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Vanoogle seearch plugin for Vanilla
 * @author ddumont@gmail.com
 */
class VanooglePlugin extends Gdn_Plugin {

    /**
     * Build the setting page.
     * @param $sender
     */
    public function settingsController_vanoogle_create($sender) {
        $sender->permission('Garden.Settings.Manage');

        $validation = new Gdn_Validation();
        $configurationModel = new Gdn_ConfigurationModel($validation);
        $configurationModel->setField(["Plugins.Vanoogle.CSE"]);
        $sender->Form->setModel($configurationModel);

        if ($sender->Form->authenticatedPostBack() === FALSE) {
            $sender->Form->setData($configurationModel->Data);
        } else {
            $data = $sender->Form->formValues();
            $configurationModel->Validation->applyRule("Plugins.Vanoogle.CSE", "Required");
            if ($sender->Form->save() !== FALSE)
                $sender->StatusMessage = t("Your settings have been saved.");
        }

        $sender->addSideMenu();
        $sender->setData("Title", t("Vanoogle Settings"));

        $categoryModel = new CategoryModel();
        $sender->setData("CategoryData", $categoryModel->getAll(), TRUE);
        array_shift($sender->CategoryData->result());

        $sender->render($this->getView("settings.php"));
    }

    /**
     * Add our script and css to every page.
     *
     * @param $sender
     */
    public function base_render_before($sender) {
        if (!c("Plugins.Vanoogle.CSE"))
            return;

        // Normally one would use ->AddJsFile or ->Head->AddScript, but these insert a version arg in the url that makes the google api barf.
        $sender->Head->addTag('script', [
            'src' => asset('https://www.google.com/jsapi', FALSE, FALSE),
            'type' => 'text/javascript',
            'id' => c("Plugins.Vanoogle.CSE")
        ]);
        $sender->addCssFile('vanoogle.css', 'plugins/Vanoogle');
        $sender->addJsFile('vanoogle.js', 'plugins/Vanoogle');
    }

    /**
     * Place our search element on page to be moved by js later.
     *
     * @param $sender
     */
    public function base_render_after($sender) {
        if (!c("Plugins.Vanoogle.ApiKey"))
            return;
        ?>
            <div id="hidden" style="display:none;">
                <div id="VanoogleSearch"><?php echo t('Loading Search...');?></div>
                <div id="vanoogle_webResult">
                    <li class="Item gs-webResult gs-result"
                      data-vars="{longUrl:function(){var i = unescapedUrl.indexOf(visibleUrl); return i &lt; 1 ? visibleUrl : unescapedUrl.substring(i);},trimmedTitle:function(){return html(title.replace(/[-][^-]+$/, ''));}}">
                        <div class="ItemContent">
                            <div data-if="Vars.richSnippet" data-attr="0" data-body="render('thumbnail',richSnippet,{url:unescapedUrl,target:target})"></div>
                            <div>
                                <a class="Title" data-attr="{href:unescapedUrl,target:target,dir:bidiHtmlDir(title)}" data-body="trimmedTitle()"></a>
                            </div>
                            <div class="Message gs-bidi-start-align gs-snippet" style="margin:0;padding-left:10px" data-body="html(content)" data-attr="{dir:bidiHtmlDir(content)}"></div>
                        </div>
                    </li>
                </div>
                <div id="vanoogle_thumbnail">
                    <div data-attr="0" data-vars="{tn:(Vars.thumbnail &amp;&amp; thumbnail.src) ? thumbnail : ( (Vars.cse_thumbnail &amp;&amp; cse_thumbnail.src) ? cse_thumbnail : {src:Vars.document &amp;&amp; document.thumbnailUrl})}">
                        <div data-if="tn.src">
                            <a class="gs-image" data-attr="{href:url,target:target}">
                                <img style="display: none;" class="gs-image" data-attr="{src:tn.src}" onload="this.style.display = 'inline'; if (this.parentNode &amp;&amp; this.parentNode.parentNode) { this.parentNode.parentNode.className = 'gs-image-box gs-web-image-box'; } ">
                            </a>
                        </div>
                    </div>
                </div>
                <div id="vanoogle_action">
                    <div data-foreach="Vars.action" data-attr="0">
                        <div data-attr="{'class': 'gs-action ' + Cur['class']}" data-if="Cur.url &amp;&amp; Cur.label">
                            <a class="gs-action" data-attr="{href:Cur.url,target:target,dir:bidiTextDir(Cur.label)}" data-body="Cur.label"></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php
    }

    public function setup() {}
}
