<?php

namespace WPDM\admin\menus;


class Settings
{

    function __construct()
    {
        add_action('admin_init', array($this, 'initiateSettings'));
        add_action('wp_ajax_wdm_settings', array($this, 'loadSettingsPage'));
        add_action('admin_menu', array($this, 'Menu'));
    }

    function Menu(){
        add_submenu_page('edit.php?post_type=wpdmpro', __('Settings &lsaquo; Download Manager', "wpdmpro"), __('Settings', "wpdmpro"), WPDM_MENU_ACCESS_CAP, 'settings', array($this, 'UI'));

    }


    function loadSettingsPage()
    {
        global $stabs;
        //$stabs['plugin-update']['callback'] = array($this, 'pluginUpdate');
        if (current_user_can(WPDM_MENU_ACCESS_CAP))
            call_user_func($stabs[$_POST['section']]['callback']);
        die();
    }

    function UI(){
        if (isset($_POST['access']) && $_POST['access'] != '') {
            update_option('access_level', $_POST[access]);
        }

        $access = get_option('access_level');
        include(WPDM_BASE_DIR . 'admin/tpls/settings.php');
    }

    /**
     * @param $tabid
     * @param $tabtitle
     * @param $callback
     * @param string $icon
     * @return array
     */
    public static function createMenu($tabid, $tabtitle, $callback, $icon = 'fa fa-cog')
    {
        return array('id' => $tabid, 'icon'=>$icon, 'link' => 'edit.php?post_type=wpdmpro&page=settings&tab=' . $tabid, 'title' => $tabtitle, 'callback' => $callback);
    }


    /**
     * @usage Initiate Settings Tabs
     */
    function initiateSettings()
    {
        global $stabs;
        $tabs = array();
        $tabs['basic'] = array('id' => 'basic','icon'=>'fa fa-cog', 'link' => 'edit.php?post_type=wpdmpro&page=settings', 'title' => 'Basic', 'callback' => array($this, 'Basic'));

        // Add buddypress settings menu when buddypress plugin is active
        if (function_exists('bp_is_active')) {
            $tabs['buddypress'] = array('id' => 'buddypress','icon'=>'fa fa-users', 'link' => 'edit.php?post_type=wpdmpro&page=settings&tab=buddypress', 'title' => 'BuddyPress', 'callback' => array($this, 'Buddypress'));
        }

        if(defined('WPDM_CLOUD_STORAGE')){
            $tabs['cloud-storage'] = array('id' => 'cloud-storage','icon'=>'fa fa-cloud',  'link' => 'edit.php?post_type=wpdmpro&page=settings&tab=cloud-storage', 'title' => 'Cloud Storage', 'callback' => array($this, 'cloudStorage'));
        }

        if(!$stabs) $stabs = array();


        $stabs = $tabs + $stabs;

        $stabs = apply_filters("add_wpdm_settings_tab", $stabs);

        $stabs['plugin-update'] = array('id' => 'plugin-update','icon'=>'fa fa-refresh',  'link' => 'edit.php?post_type=wpdmpro&page=settings&tab=plugin-update', 'title' => 'Updates', 'callback' => array($this, 'pluginUpdate'));

    }


    /**
     * @usage  Admin Settings Tab Helper
     * @param string $sel
     */
    public static function renderMenu($sel = '')
    {
        global $stabs;

        foreach ($stabs as $tab) {
            if ($sel == $tab['id'])
                echo "<li class='active'><a id='{$tab['id']}' href='{$tab['link']}'><i class='{$tab['icon']}'></i> &nbsp; {$tab['title']}</a></li>";
            else
                echo "<li class=''><a id='{$tab['id']}' href='{$tab['link']}'><i class='{$tab['icon']}'></i> &nbsp; {$tab['title']}</a></li>";
            //if (isset($tab['func']) && function_exists($tab['func'])) {
            //    add_action('wp_ajax_' . $tab['func'], $tab['func']);
            //}
        }
    }


    function Basic(){

        if (isset($_POST['task']) && $_POST['task'] == 'wdm_save_settings') {
            if ($_POST['__wpdm_curl_base'] == '') $_POST['__wpdm_curl_base'] = 'wpdm-category';
            if ($_POST['__wpdm_purl_base'] == '') $_POST['__wpdm_purl_base'] = 'wpdm-package';
            if ($_POST['__wpdm_curl_base'] == $_POST['__wpdm_purl_base']) $_POST['__wpdm_curl_base'] .= 's';
            foreach ($_POST as $optn => $optv) {
                update_option($optn, $optv);
            }
            if (!isset($_POST['__wpdm_skip_locks'])) delete_option('__wpdm_skip_locks');
            if (!isset($_POST['__wpdm_login_form'])) delete_option('__wpdm_login_form');
            if (!isset($_POST['__wpdm_cat_desc'])) delete_option('__wpdm_cat_desc');
            if (!isset($_POST['__wpdm_cat_img'])) delete_option('__wpdm_cat_img');
            if (!isset($_POST['__wpdm_cat_tb'])) delete_option('__wpdm_cat_tb');
            flush_rewrite_rules();
            global $wp_rewrite;
            $wpdm = new \WPDM\WordPressDownloadManager();
            $wpdm->registerPostTypeTaxonomy();
            $wp_rewrite->flush_rules();
            die('Settings Saved Successfully');
        }
        include(WPDM_BASE_DIR.'admin/tpls/settings/basic.php');

    }


    function Frontend(){
        if(isset($_POST['section']) && $_POST['section']=='frontend' && isset($_POST['task']) && $_POST['task']=='wdm_save_settings' && current_user_can(WPDM_ADMIN_CAP)){
            foreach($_POST as $k => $v){
                if(strpos("__".$k, '_wpdm_')){
                    update_option($k, $v);
                }
            }

            global $wp_roles;

            $roleids = array_keys($wp_roles->roles);
            $roles = maybe_unserialize(get_option('__wpdm_front_end_access',array()));
            $naroles = array_diff($roleids, $roles);

            foreach($roles as $role) {
                $role = get_role($role);
                if(is_object($role))
                    $role->add_cap('upload_files');
            }

            foreach($naroles as $role) {
                $role = get_role($role);
                if(!isset($role->capabilities['edit_posts']) || $role->capabilities['edit_posts']!=1)
                    $role->remove_cap('upload_files');
            }

            $refresh = 0;

            $page_id = $_POST['__wpdm_user_dashboard'];
            if($page_id != '') {
                $page_name = get_post_field("post_name", $page_id);
                add_rewrite_rule('^' . $page_name . '/(.+)/?', 'index.php?page_id=' . $page_id . '&udb_page=$matches[1]', 'top');
                $refresh = 1;
            }

            $page_id = $_POST['__wpdm_author_dashboard'];
            if($page_id != '') {
                $page_name = get_post_field("post_name", $page_id);
                add_rewrite_rule('^' . $page_name . '/(.+)/?', 'index.php?page_id=' . $page_id . '&adb_page=$matches[1]', 'top');
                $refresh = 1;
            }

            if($refresh == 1){
                global $wp_rewrite;
                $wp_rewrite->flush_rules(true);
            }

            die('Settings Saved Successfully!');
        }
        include(WPDM_BASE_DIR."admin/tpls/settings/frontend.php");
    }

    function Buddypress(){
        if(isset($_POST['section']) && $_POST['section']=='buddypress' && isset($_POST['task']) && $_POST['task']=='wdm_save_settings' && current_user_can(WPDM_ADMIN_CAP)){
            foreach($_POST as $k => $v){
                if(strpos("__".$k, '_wpdm_')){
                    update_option($k, $v);
                }
            }
            die('Settings Saved Successfully!');
        }
        include(WPDM_BASE_DIR . "admin/tpls/settings/buddypress.php");
    }

    function cloudStorage(){
        if(isset($_POST['section']) && $_POST['section']=='cloud-storage' && isset($_POST['task']) && $_POST['task']=='wdm_save_settings' && current_user_can(WPDM_ADMIN_CAP)){
            foreach($_POST as $k => $v){
                if(strpos("__".$k, '_wpdm_')){
                    update_option($k, $v);
                }
            }
            die('Settings Saved Successfully!');
        }
        include(WPDM_BASE_DIR . "admin/tpls/settings/cloud-storage.php");
    }

    function pluginUpdate(){
        if(isset($_REQUEST['logout']) && $_REQUEST['logout'] == 1){
            delete_option('__wpdm_suname');
            delete_option('__wpdm_supass');
            die('<script>location.href="edit.php?post_type=wpdmpro&page=settings&tab=plugin-update";</script>Refreshing...');
        }

        if(isset($_POST['__wpdm_suname']) && $_POST['__wpdm_suname'] != ''){
            update_option('__wpdm_suname',$_POST['__wpdm_suname']);
            update_option('__wpdm_supass',$_POST['__wpdm_supass']);
            die('<script>location.href=location.href;</script>Refreshing...');
        }

        if(get_option('__wpdm_suname') != '') {
            $purchased_items = get_option('__wpdm_purchased_items', false);
            if(!$purchased_items || wpdm_query_var('newpurchase') != '' ) {
                $purchased_items = remote_get('http://www.wpdownloadmanager.com/?wpdmppaction=getpurchaseditems&user=' . get_option('__wpdm_suname') . '&pass=' . get_option('__wpdm_supass'));
                update_option('__wpdm_purchased_items', $purchased_items);
            }
            $purchased_items = json_decode($purchased_items);
            if (isset($purchased_items->error)){ delete_option('__wpdm_suname'); }
            if (isset($purchased_items->error)) $purchased_items->error = str_replace("[redirect]", admin_url("edit.php?post_type=wpdmpro&page=settings&tab=plugin-update"), $purchased_items->error);
        }
        if(get_option('__wpdm_freeaddons') == '' || wpdm_query_var('newpurchase') != '' || 1) {
            $freeaddons = remote_get('http://www.wpdownloadmanager.com/?wpdm_api_req=getPackageList&cat_id=1148');
            update_option('__wpdm_freeaddons', $freeaddons);
        }
        $freeaddons = json_decode(get_option('__wpdm_freeaddons'));
        include(WPDM_BASE_DIR . 'admin/tpls/settings/addon-update.php');
    }



}