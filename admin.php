<style type='text/css'>
    .text-error {
    color:rgba(255,0,0,0.5) !important;
}
a#content-tmce, a#content-tmce:hover {
    display:none;
}

.hovercard {
    position:relative;
    cursor:help;
}

.hovercard .card {
    z-index:1;
    display:none;
    position:absolute;
    width:450px;
    right:-462px;
    top:-18px;
    padding:0px 10px;
}

.hovercard span {
    font-size:16px;
    color:rgba(0,0,255,0.5);
    position:absolute;
    right:10px;
    top:-18px;
    font-weight:bold;
}

.hovercard:hover .card {
    display:block;
}

.card p {
    font-size:10px;
    font-weight:200;
}
.card p b {
    font-size:13px;
    margin-right:10px;
    text-transform:uppercase;
}

</style>
    <script type='text/javascript'>

    (function ( $ ) {
        "use strict";

        $(function () {

            QTags.addButton( 'tfw_title', 'Title', '{{title}}');
            QTags.addButton( 'tfw_photo', 'Photo', '{{if-photo-start}}{{photo}}{{if-photo-end}}');
            QTags.addButton( 'tfw_photo_link', 'Photo Link', '{{photo-url}}');
            QTags.addButton( 'tfw_video', 'Video', '{{if-video-start}}{{video}}{{if-video-end}}');
            QTags.addButton( 'tfw_content', 'Content', '{{content}}');
            QTags.addButton( 'tfw_post_url', 'Twitter URL', '{{twitter-url}}');
            QTags.addButton( 'tfw_favourites', 'Favourites Count', '{{favourites}}');
            QTags.addButton( 'tfw_retweets', 'Retweet Count', '{{retweets}}');

        });
        $(document).on('click', '#view-debug', function(e) {
            e.preventDefault();
            $('#debug-log').toggle();
        })

    }(jQuery));
</script>

    <div class="wrap">
    <h2 style='margin-bottom:20px;'>
    <?php echo esc_html( get_admin_page_title() ); ?>
    <div style='float:right;'>
    <?php
    if (!get_option('tfw_profiles')) {
    ?>
    <a href='<?php echo SM2WP_Twitter_Admin::SM2WP_AUTH_URL ?>connect/twitter/?next=<?php echo admin_url( "options-general.php?page=$_GET[page]" ) ?>' class='button button-primary'>Connect Profile from Twitter</a>
    <?php
    }
    ?>
    </div>
    </h2>

    <p class='pro-invitation'>
        Social Media 2 WordPress for Twitter Complete supports <u>multiple twitter profiles</u>, <u>retweet filtering</u>, <u>deeper post history</u>, <u>filter imports by hashtags</u>, <u>feature images</u> and more!  <a href='http://sm2wp.com/'>Click here to upgrade</a>.
    </p>

    <div id='welcome-panel' class='welcome-panel'>
    <div class='welcome-panel-content' style='padding-top:10px;'>
    <div style='color:rgba(130,200,90,0.8);height:50px;width:15%;text-align:center;display:inline-block;'>
    <span style='position:absolute;top:0px;left:10px;font-weight:bold;color:#333;'>LAST IMPORT STATS</span>
    <h1 style='font-size:54px;margin:0 0 10px 0;'><?php echo get_option('tfw_imported_new',0)?></h1>
    NEW POSTS
    </div>
    <div style='color:rgba(190,120,60,0.5);height:50px;width:15%;text-align:center;display:inline-block;'>
    <h1 style='font-size:54px;margin:0 0 10px 0;'><?php echo get_option('tfw_imported_updated',0)?></h1>
    UPDATED POSTS
    </div>
    <div style='color:rgba(210,210,210,1);height:50px;width:15%;text-align:center;display:inline-block;'>
    <h1 style='font-size:54px;margin:0 0 10px 0;'><?php echo get_option('tfw_imported_ignored',0)?></h1>
    IGNORED POSTS
    </div>
<!--
    <div style='color:rgba(240,60,30,0.5);height:50px;width:15%;text-align:center;display:inline-block;'>
    <h1 style='font-size:54px;margin:0 0 10px 0;'><?php echo get_option('tfw_imported_comments',0)?></h1>
    NEW COMMENTS
    </div>
-->
    <div style='height:50px;width:25%;float:right;text-align:center;display:inline-block;'>
    <h1 style='font-size:54px;margin:0 0 10px 0;'>
    <?php echo '~'.round((wp_next_scheduled('tfw_import')-time())/60,0); ?><span style='font-size:20px;line-height:0px;'>mins</span></h1>
    'TIL SCHEDULED IMPORT
                <a style='position:absolute;top:0px;right:10px;' href='<?php echo admin_url( "options-general.php?page=$_GET[page]" ) ?>&run=1'>RUN NOW</a>
            </div>

        </div>
    </div>

    <div id='dashboard-widgets-wrap'>
        <div id='dashboard-widgets' class='metabox-holder'>

            <div id='postbox-container-1' class='postbox-container'>
                <div id='normal-sortables' class="meta-box-sortables ui-sortable">
                    <form action='options.php' method='POST'>
                        <?php settings_fields( 'tfw_profiles' ); ?>
                    <div class='postbox'>
                        <h3 class='hndle' style='cursor:default;'>Connected Profiles</h3>
                        <div class="inside">
                        	<div class="main">
                            <?php
                                if (!($profiles = get_option('tfw_profiles'))) { echo "<p>You have not yet added any profiles.</p>"; }
                                else {
                            ?>
                                <ul>
                            <?php
                                foreach ($profiles as $id => $profile) {
                            ?>
                                <li>
                                    <input type='hidden' name='tfw_profiles[<?php echo $id?>][id]' value='<?php echo $profile['id']?>' />
                                    <input type='hidden' name='tfw_profiles[<?php echo $id?>][name]' value='<?php echo $profile['name']?>' />
                                    <input type='hidden' name='tfw_profiles[<?php echo $id?>][network_id]' value='<?php echo $profile['network_id']?>' />
                                    <input type='hidden' name='tfw_profiles[<?php echo $id?>][avatar]' value='<?php echo $profile['avatar']?>' />
                                    <input type='hidden' name='tfw_profiles[<?php echo $id?>][access_token]' value='<?php echo $profile['access_token']?>' />
                                    <img style='width:32px;margin-right:20px;vertical-align:middle;' src='<?php echo $profile['avatar']?>' />
                                    <?php echo $profile['name'] ?>
                                    <div style='display:inline-block;float:right;'>
                                        <?php wp_dropdown_users(array('show_option_none' => 'Choose an Author', 'name' => "tfw_profiles[$id][author]", 'who' => 'authors', 'selected' => @$profile['author'], 'include_selected' => true, 'class' => 'wp-authors')); ?>
                                    </div>
                                    <a href='<?php echo admin_url( "options-general.php?page=$_GET[page]&del=".urlencode($id) ) ?>' style='margin-left:5px;' class='text-error'>remove</a>
                                </li>

                            <?php
                                }
                            ?>
                                </ul>
                            <?php
                                }
                                if (get_option('tfw_profiles')) {
                            ?>
                                <input type='submit' class='button button-primary' value='Update Authors'>
                            <?php
                                }
                            ?>
                        	</div>
                    	</div>
                    </div>
                </form>
<div class='postbox'>

<h3 class='hndle' style='cursor:default;'>Imported Post Template
<div class='hovercard'>
<span>?</span>

<div class='postbox card'>
	<p><b>{{title}}</b> The title of the tweet</p>
	<p><b>{{photo}}</b> The URL of the first photo attached to the tweet</p>
	<p><b>{{photo-url}}</b> The URL of the photo on Twitter</p>
	<p><b>{{video}}</b> The URL of the video attached to the tweet</p>
	<p><b>{{content}}</b> This content of the tweet (or reshared tweet)</p>
	<p><b>{{twitter-url}}</b> The link of the tweet on Twitter</p>
	<p><b>{{favourites}}</b> The number of favourites a tweet received</p>
	<p><b>{{retweeted}}</b> The number of times a tweet was retweeted</p>
	<p><hr /></p>
	<p><b>{{if-photo-start}}</b> Keep photo specific information between start and end tags</p>
	<p><b>{{if-video-start}}</b> Keep video specific information between the start and end tags</p>
</div>
</h3>
<div class="inside">
<div class="main" style='padding-bottom:40px;'>
<form action='options.php' method='POST'>
<?php settings_fields( 'tfw_template' ); ?>
<?php
wp_editor( get_option('tfw_template', SM2WP_Twitter_Library::get_template()), 'tfw_template',
array('media_buttons' => false,
'textarea_rows' => 20,
'tinymce' => false,
'teeny' => true,
'quicktags' => array("buttons" => "none")
)); ?>
<input type='submit' style='float:left;margin-top:10px;z-index:1;' class='button button-primary' value='Update Template'>
</form>
<form action='options.php' method='POST' style='position:absolute;text-align:right;width:50%;right:0px;'>
<?php settings_fields( 'tfw_template' ); ?>
<input type='hidden' name='tfw_template' value="<?php echo SM2WP_Twitter_Library::get_template()?>" />
<input type='submit' id='reset-template' style='margin-top:10px;margin-right:10px;float:right;' class='button' value='Reset Template'>
</form>


</div>
</div>
</div>

</div>
</div>

<div id='postbox-container-2' class='postbox-container'>
    <div id='side-sortables' class="meta-box-sortables ui-sortable">



<div class='postbox'>

    <h3 class='hndle' style='cursor:default;'>Import Settings</h3>
    <div class="inside">
        <div class="main">
            <form action='options.php' method='POST'>
                <?php settings_fields( 'tfw_import_settings' ); ?>

<ul>

    <li>
        <b>Post History Depth</b>
        <select name='tfw_history' style='float:right;margin-top:6px;'>
            <option <?php echo get_option('tfw_history', 10) == '10' ? 'selected' : ''?>>10</option>
        </select>
        <span style='display:block'>The amount of tweets to be monitored by the plugin</span>
    </li>

    <li>
        <b>Post Overwrite</b>
        <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_overwrite' value='1' <?php echo get_option('tfw_overwrite', true) ? 'checked' : '' ?>/>
        <span style='display:block'>Continue to update posts after initial import</span>
    </li>

    <li>
        <b>Import Trashed Posts</b>
        <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_import_trashed' value='1' <?php echo get_option('tfw_import_trashed', true) ? 'checked' : '' ?>/>
        <span style='display:block'>Reimport post even if it has previously been trashed</span>
    </li>
    <li>
        <b>Don't Add Canonical Reference</b>
            <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_ignore_canonical' value='1' <?php echo get_option('tfw_ignore_canonical', false) ? 'checked' : '' ?>/>
            <span style='display:block'>Do not properly attribute to Twitter post</span>
            </li>

            <li>
            <b>Remove Hashtags from Post</b>
            <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_remove_hashtags' value='1' <?php echo get_option('tfw_remove_hashtags', false) ? 'checked' : '' ?>/>
            <span style='display:block'>Remove hashtags from post content</span>
            </li>

            <li>
            <b>Remove Hashes from Post</b>
            <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_remove_hashes' value='1' <?php echo get_option('tfw_remove_hashes', false) ? 'checked' : '' ?>/>
            <span style='display:block'>Remove hashes (#) from post content</span>
            </li>

            <li>
            <b>Include Replies</b>
            <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_include_replies' value='1' <?php echo get_option('tfw_include_replies', false) ? 'checked' : '' ?>/>
            <span style='display:block'>Import tweets that are replies to other tweets</span>
            </li>

            <li>
            <b>Include ReTweets</b>
            <input style='float:right;margin-top:6px;' type='checkbox' name='tfw_include_retweets' value='1' <?php echo get_option('tfw_include_retweets', true) ? 'checked' : '' ?>/>
            <span style='display:block'>Import retweets of other tweets</span>
            </li>

    <li style='clear:both;'>
        <b>Import Schedule</b>
        <select name='tfw_schedule' style='float:right;margin-top:6px;'>
        <?php foreach (wp_get_schedules() as $key => $desc) {
          if (!in_array($key, array('hourly', 'daily', 'twicedaily'))) continue;
        ?>
        <option value='<?php echo $key?>' <?php echo get_option('tfw_schedule') == $key ? 'selected' : ''?>><?php echo $desc['display'] ?></option>
        <?php } ?>
        </select>
        <span style='display:block'>How often to run the import process</span>
    </li>

</ul>
    <input type='submit' class='button button-primary' value='Update Settings'>
</form>
</div>
</div>
</div>

<div class='postbox'>

    <h3 class='hndle' style='cursor:default;'>Post Settings</h3>
    <div class="inside">
        <div class="main">
            <form action='options.php' method='POST'>
            <?php settings_fields( 'tfw_defaults' ); ?>

<ul>

    <li>
        <b>Default Post Status</b>
        <select name='tfw_post_status' style='float:right;margin-top:6px;'>
            <option <?php echo get_option('tfw_post_status') == 'Publish' ? 'selected' : ''?>>Publish</option>
            <option <?php echo get_option('tfw_post_status') == 'Pending' ? 'selected' : ''?>>Pending</option>
            <option <?php echo get_option('tfw_post_status') == 'Future' ? 'selected' : ''?>>Future</option>
            <option <?php echo get_option('tfw_post_status') == 'Private' ? 'selected' : ''?>>Private</option>
            <option <?php echo get_option('tfw_post_status') == 'Draft' ? 'selected' : ''?>>Draft</option>
        </select>
        <span style='display:block'>Imported posts are created with this status</span>
    </li>

    <li>
        <b>Default Post Categories</b>
        <select name='tfw_post_categories[]' row='3' multiple style='float:right;margin-top:6px;'>
        <?php foreach (get_categories(array('hide_empty' => 0)) as $category) { ?>
            <option value='<?php echo $category->cat_ID?>' <?php echo in_array($category->cat_ID, get_option('tfw_post_categories',array())) ? 'selected' : ''?>><?php echo $category->name?></option>
        <?php } ?>
        </select>
        <span style='display:block'>Imported posts are created with these categories</span>
    </li>

    <li style='clear:both;'>
        <b>Default Post Tags</b>
        <input style='float:right;margin-top:6px;' size='15' type='text' name='tfw_post_tags' value='<?php echo get_option('tfw_post_tags') ?>' />
        <span style='display:block'>Tags to apply to post during import (eg. tag1,tag2)</span>
    </li>

    </ul>
    <input type='submit' class='button button-primary' value='Update Settings'>
</form>
</div>
</div>
</div>


                </div>
                </div>

</div>
</div>

<div style='width:100%;' class='postbox-container'>
    <div id='side-sortables' class="meta-box-sortables ui-sortable">

<div class='postbox'>

<h3 class='hndle' style='cursor:default;padding:8px;margin:0px;font-size:14px;'>Import Log
<a style='float:right;' href='#' id='view-debug'>&#9660;</a>
            </h3>
            <div class="inside">
            <div class="main">
            <?php foreach (get_option('tfw_running', array()) as $message) {
                ?>
                    <p><?php echo $message?></p>
                    <?php
            } ?>

            </div>
            </div>
            </div>
            </div>
            </div>

            <div id='debug-log' style='width:100%;display:none;' class='postbox-container'>
            <div id='side-sortables' class="meta-box-sortables ui-sortable">

            <div class='postbox'>

            <h3 class='hndle' style='cursor:default;padding:8px;margin:0px;font-size:14px;'>Debug Log</h3>
            <div class="inside">
            <div class="main">
            <?php foreach (get_option('tfw_debug', array()) as $message) {
                ?>
                    <p><?php echo $message?></p>
                    <?php
            } ?>

            </div>
            </div>
            </div>
            </div>
            </div>


            </div>
