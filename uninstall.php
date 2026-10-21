<?php
/** Remove only TabulaMea data; preserve all other plugins’ tables. */
defined('WP_UNINSTALL_PLUGIN') || exit;
require_once __DIR__.'/includes/deckerweb-plugin-library/lifecycle.php';
deckerweb_library_uninstall_v3(__DIR__.'/tabulamea.php');
// Clear only TabulaMea's current and legacy updater metadata.
$tabulamea_updater_class=require __DIR__.'/includes/deckerweb-github-release-updater-v2.php';
if(is_string($tabulamea_updater_class)&&method_exists($tabulamea_updater_class,'clear_repository_cache')){
    $tabulamea_updater_class::clear_repository_cache('https://github.com/deckerweb/tabulamea');
}
unset($tabulamea_updater_class);
/** Clean one site's tasks, caches and optionally user-owned TabulaMea data. @return void */
function tbm_uninstall_site(): void {
    global $wpdb;
    wp_clear_scheduled_hook('tbm_maintenance');
    wp_clear_scheduled_hook('tbm_maintenance_batch');
    $names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",$wpdb->esc_like('_transient_tbm_').'%',$wpdb->esc_like('_transient_timeout_tbm_').'%'));
    foreach($names as $name){delete_option($name);}
    foreach((array)get_option('tbm_workflow_keys',[]) as $key=>$expiry){if(preg_match('/^tbm_(stage|draft|apply_result|cleanup)_\\d+$/D',$key)){delete_transient($key);}}
    delete_option('tbm_workflow_keys');delete_transient('tbm_storage_summary');
    delete_option('tbm_import_state');delete_option('tbm_statify_import');
    foreach(['receipts','rate'] as $table){$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tbm_{$table}");}
    delete_option('tbm_core_error');delete_option('tbm_test_received');delete_option('tbm_schema');
    $s=get_option('tbm_settings',[]);
    $dir=wp_upload_dir(null,false)['basedir'].'/tabulamea';
    foreach(['country.download.gz','country.download.mmdb','update.lock'] as $file){if(is_file($dir.'/'.$file)){wp_delete_file($dir.'/'.$file);}}
    if(empty($s['delete_data'])){return;}
    foreach(['dimensions','site_stats','post_stats','referrer_stats','import_ledger','search_stats'] as $table){$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tbm_{$table}");}
    foreach(['tbm_settings','tbm_rankings','tbm_schema','tbm_revision','tbm_geo_month','tbm_geo_updated','tbm_since_device','tbm_since_browser','tbm_since_os','tbm_since_country','tbm_templates','tbm_last_received','tbm_live_since','tbm_import_state','tbm_changes','tbm_last_maintenance','tbm_test_received','tbm_search_since'] as $name){delete_option($name);}
    delete_metadata('user',0,$wpdb->prefix.'tbm_preferences','',true);
    if(is_file($dir.'/country.mmdb')){wp_delete_file($dir.'/country.mmdb');}
}
if(is_multisite()){
    $offset=0;
    do{$ids=get_sites(['fields'=>'ids','number'=>100,'offset'=>$offset]);foreach($ids as $id){switch_to_blog($id);tbm_uninstall_site();restore_current_blog();}$offset+=100;}while(count($ids)===100);
}else{tbm_uninstall_site();}
