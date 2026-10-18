<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/**
 * Provides administrative configuration and permission-checked reports.
 */
final class Admin {
    /**
     * Register site settings and filtered report navigation.
     * @return void
     */
    public static function menu(): void {
        add_options_page('TabulaMea','TabulaMea','manage_options','tabulamea',[self::class,'page']);
        if(Plugin::allowed('view')) {
            add_menu_page('TabulaMea','TabulaMea','view_tabulamea','tabulamea-statistics',[StatisticsAdmin::class,'page'],'data:image/svg+xml;base64,'.base64_encode((string)file_get_contents(dirname(TBM_FILE).'/assets/brand/menu.svg')),3);
            add_submenu_page('tabulamea-statistics',__('Statistics overview','tabulamea'),__('Statistics overview','tabulamea'),'view_tabulamea','tabulamea-statistics',[StatisticsAdmin::class,'page']);
            add_submenu_page('tabulamea-statistics',__('Additional statistics','tabulamea'),__('Additional statistics','tabulamea'),'view_tabulamea','tabulamea-dimensions',[self::class,'dimensions']);
            add_submenu_page('tabulamea-statistics',__('Content rankings','tabulamea'),__('Content rankings','tabulamea'),'view_tabulamea','tabulamea-reports',[self::class,'reports']);
        }
    }
    /**
     * Register options through the native Settings API.
     * @return void
     */
    public static function register(): void { register_setting('tbm','tbm_settings',['sanitize_callback'=>[self::class,'sanitize'],'type'=>'array','show_in_rest'=>false]); }
    /**
     * Sanitize site configuration without granting arbitrary WordPress privileges.
     * @param mixed $input Submitted settings.
     * @return array Validated configuration.
     */
    public static function sanitize($input): array {
        if(!is_array($input)){return Plugin::settings();}
        $inherit=!empty($input['_inherit_network'])&&is_multisite()&&current_user_can('manage_options');$baseline=Plugin::settings();$s=$baseline;$scope=InterfaceTools::text($input,'_section');$sections=ConfigurationTools::sections();
        if($scope&&!isset($sections[$scope])){return $s;}
        if($scope){$selected=$input;foreach($sections[$scope] as $key){$selected[$key]=$input[$key] ?? (in_array($key,['users','view_roles','manage_roles','widget_roles','exclude_ids'],true)?[]:false);}$input=array_replace($s,$selected);}
        $roles=array_keys(wp_roles()->roles);
        foreach(['view','manage','widget'] as $area){$s[$area.'_roles']=array_values(array_intersect($roles,array_map('sanitize_key',array_filter((array)($input[$area.'_roles'] ?? $s[$area.'_roles']),'is_scalar'))));}
        foreach(['original_widget','widget','technology','country','geo_download','public_rankings','public_counts','delete_data','exclude_front','widget_pageviews','widget_visitors'] as $key){$s[$key]=array_key_exists($key,$input)?!empty($input[$key]):$s[$key];}
        $s['retention']=max(1,min(120,absint(is_scalar($input['retention'] ?? null)?$input['retention']:$s['retention'])));
        $s['widget_limit']=max(1,min(20,absint(is_scalar($input['widget_limit'] ?? null)?$input['widget_limit']:$s['widget_limit'])));
        $period=is_scalar($input['widget_period'] ?? null)?(string)$input['widget_period']:$s['widget_period'];$s['widget_period']=isset(DashboardTools::periods()[$period])?$period:'30_days';
        $type=is_scalar($input['widget_type'] ?? null)?sanitize_key($input['widget_type']):$s['widget_type'];$s['widget_type']=$type==='all'||isset(get_post_types(['public'=>true])[$type])?$type:'all';
        $s['widget_ranking']=is_scalar($input['widget_ranking'] ?? null)?sanitize_key((string)$input['widget_ranking']):$s['widget_ranking'];
        $ids=$input['exclude_ids'] ?? $s['exclude_ids'];$s['exclude_ids']=array_slice(wp_parse_id_list(is_array($ids)?$ids:(is_scalar($ids)?(string)$ids:[])),0,200);
        $s['users']=[];foreach((array)($input['users'] ?? $baseline['users']) as $id=>$rules){$id=absint($id);if(!$id||!is_user_member_of_blog($id)||!is_array($rules)){continue;}foreach(['view','manage','widget'] as $area){$value=$rules[$area] ?? 'inherit';$s['users'][$id][$area]=in_array($value,['allow','deny'],true)?$value:'inherit';}}
        foreach(array_keys((array)($input['remove_users'] ?? [])) as $id){unset($s['users'][absint($id)]);}
        $name=InterfaceTools::text($input,'add_user');if($name){$user=get_user_by('login',$name)?:get_user_by('email',$name);if($user&&is_user_member_of_blog($user->ID)){$s['users'][$user->ID] ??= ['view'=>'inherit','manage'=>'inherit','widget'=>'inherit'];}else{add_settings_error('tbm','user',__('User not found on this website.','tabulamea'));}}
        if($scope){foreach($baseline as $key=>$value){if(!in_array($key,$sections[$scope],true)){$s[$key]=$value;}}}
        $s=Measurement::sanitize($input,$s,$scope);
        if($scope===''||$scope==='content'){$s['column_types']=array_values(array_intersect(array_diff(get_post_types(['public'=>true]),['attachment']),array_filter((array)($input['column_types']??[]),'is_string')));$period=is_string($input['column_period']??null)?$input['column_period']:'30_days';$s['column_period']=isset(DashboardTools::periods()[$period])?$period:'30_days';}
        if($scope===''||$scope==='dashboard'){foreach(['overview_modules','overview_allowed'] as $key){$s[$key]=array_values(array_intersect(array_keys(DashboardTools::modules()),array_filter((array)($input[$key]??[]),'is_string')));}$s['chart_style']=($input['chart_style']??'line')==='bars'?'bars':'line';$s['widget_personal']=!empty($input['widget_personal']);$s['widget_max']=max(1,min(20,absint($input['widget_max']??10)));$s['overview_items']=max(1,min(10,absint($input['overview_items']??5)));$period=is_string($input['overview_period']??null)?$input['overview_period']:'7_days';$s['overview_period']=isset(DashboardTools::periods()[$period])?$period:'7_days';}
        if($scope){$keys=array_unique(array_merge(array_keys((array)get_option('tbm_settings',[])),$sections[$scope]));$s=array_intersect_key($s,array_flip($keys));}
        if($inherit){foreach(array_keys(NetworkSettings::defaults()) as $key){unset($s[$key]);}}return $s;
    }

    /**
     * Add settings action link before Deactivate.
     * @param array $links Existing action links.
     * @return array Updated links.
     */
    public static function links(array $links): array { if(current_user_can('manage_options')){ array_unshift($links,'<a href="'.esc_url(admin_url('options-general.php?page=tabulamea')).'">'.esc_html__('Settings','tabulamea').'</a>'); } return $links; }
    /**
     * Load scoped admin assets and optional cosmetic cleanup.
     * @param string $hook Current screen hook.
     * @return void
     */
    public static function assets(string $hook): void {
        if($hook==='edit.php'&&Plugin::allowed('view')&&in_array(get_current_screen()->post_type,ContentColumns::types(),true)){wp_enqueue_style('tbm-content-columns',plugins_url('assets/content-columns.css',TBM_FILE),[],TBM_VERSION);}
        if($hook==='index.php'&&!self::dashboard_assets()){return;}if(str_contains($hook,'tabulamea-reports')){wp_enqueue_style('tbm-ranking',plugins_url('assets/rankings.css',TBM_FILE),[],TBM_VERSION);}
        if($hook==='index.php' || str_contains($hook,'tabulamea')) {
            foreach(['navigation','admin','footer','statistics'] as $name){wp_enqueue_style('tbm-'.$name,plugins_url('assets/'.$name.'.css',TBM_FILE),[],TBM_VERSION.'.'.filemtime(dirname(TBM_FILE).'/assets/'.$name.'.css'));}
            foreach(['extras','admin','footer','statistics'] as $name){wp_enqueue_script('tbm-'.$name,plugins_url('assets/'.$name.'.js',TBM_FILE),[],TBM_VERSION,true);}
        }
    }
    /** Load dashboard assets only when an accessible plugin widget is enabled. @return bool Whether assets are needed. */
    private static function dashboard_assets(): bool {$s=Plugin::settings();return ($s['original_widget']&&Plugin::allowed('view'))||($s['widget']&&Plugin::allowed('widget'));}
    /**
     * Print a labeled checkbox with escaped configuration.
     * @param string $key Setting key.
     * @param string $label Translated label.
     * @param array $s Configuration.
     * @return void
     */
    public static function checkbox(string $key,string $label,array $s): void { echo '<p><label><input type="checkbox" name="tbm_settings['.esc_attr($key).']" value="1" '.checked(!empty($s[$key]),true,false).'> '.esc_html($label).'</label></p>'; }
    /**
     * Render the settings, ranking editor and portable configuration controls.
     * @return void
     */
    public static function page(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'));}
        Settings::page();
    }

    /**
     * Render shared local documentation dialogs with genuine link fallbacks.
     * @return void
     */
    public static function footer(): void {
        require_once dirname(TBM_FILE).'/includes/deckerweb-changelog-v1.php';
        require_once dirname(TBM_FILE).'/includes/deckerweb-admin-footer-v1.php';
        // Match the effective UI language too, including preferred-language fallbacks.
        $de=(bool)preg_match('/^de(?:[_-]|$)/i',get_user_locale())||__('Documentation','tabulamea')==='Dokumentation'; $docs=[];
        foreach(['readme'=>['DOCUMENTATION',__('Documentation','tabulamea')],'faq'=>['FAQ',__('FAQ','tabulamea')],'changelog'=>['CHANGELOG',__('Changelog','tabulamea')]] as $key=>[$file,$label]){
            $name=$file.($de?'-de':'').'.txt';$docs[$key]=['label'=>$label,'url'=>plugins_url($name,TBM_FILE),'text'=>(string)file_get_contents(dirname(TBM_FILE).'/'.$name)];
        }
        \Deckerweb\AdminFooter\V1\StructuredFooter::render(['id'=>'tbm','about'=>__('About this plugin','tabulamea'),'name'=>'TabulaMea','version'=>TBM_VERSION,'version_label'=>__('Version','tabulamea'),'slogan'=>__('Your content. Its impact. All in view.','tabulamea'),'copyright'=>'© 2026','repository'=>'https://github.com/deckerweb','website'=>__('More plugins','tabulamea'),'close'=>__('Close','tabulamea'),'documents'=>$docs]);
    }
    /**
     * Print nonce-protected action fields.
     * @param string $task Allowed operation.
     * @return void
     */
    public static function action_fields(string $task): void { echo '<input type="hidden" name="action" value="tbm_action"><input type="hidden" name="task" value="'.esc_attr($task).'">';wp_nonce_field('tbm_'.$task); }
    /**
     * Print one standalone action button.
     * @param string $task Action name.
     * @param string $label Translated label.
     * @param string $id Ranking identifier.
     * @return void
     */
    public static function button(string $task,string $label,string $id=''): void { echo '<form class="tbm-inline" action="'.esc_url(admin_url('admin-post.php')).'" method="post">';self::action_fields($task);echo '<input type="hidden" name="id" value="'.esc_attr($id).'">';submit_button($label,'secondary','submit',false,['id'=>'tbm-'.$task.'-'.$id]);echo '</form>'; }
    /**
     * Dispatch administrative mutations after explicit capability and nonce checks.
     * @return void
     */
    public static function action(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $task=is_string($_POST['task'] ?? null) ? sanitize_key($_POST['task']) : ''; 
        if(!in_array($task,['ranking','freeze','unfreeze','delete','geo','export','import'],true)){wp_die('Invalid action','',400);}
        check_admin_referer('tbm_'.$task);$ok=false;$ranks=(array)get_option('tbm_rankings',[]);$id=is_string($_POST['id'] ?? null) ? sanitize_key($_POST['id']) : ''; 
        if($task==='ranking' && !empty($_POST['tbm_preview'])){
            $raw=wp_unslash($_POST['ranking'] ?? []);if(!is_array($raw)){wp_die(esc_html__('Invalid ranking.','tabulamea'),'',400);}
            InterfaceTools::temporary('tbm_draft_'.get_current_user_id(),['id'=>sanitize_key(is_scalar($raw['id'] ?? null)?(string)$raw['id']:''),'ranking'=>Rankings::clean($raw)],15*MINUTE_IN_SECONDS);
            wp_safe_redirect(admin_url('admin.php?page=tabulamea-reports&preview=1#tbm-draft-preview'));exit;
        }
        if($task==='ranking'){$raw=wp_unslash($_POST['ranking'] ?? []);if(is_array($raw)){$id=is_string($raw['id'] ?? null) ? sanitize_key($raw['id']) : ''; $rank=Rankings::clean($raw);if(!$id && $rank['title']){$base=substr(sanitize_key(sanitize_title($rank['title'])),0,64);if(!$base){$base='ranking-'.substr(wp_generate_uuid4(),0,8);}$id=$base;$suffix=2;while(isset($ranks[$id])){$id=$base.'-'.$suffix++;}}if($id && $rank['title'] && Rankings::dates($rank) && (count($ranks)<100 || isset($ranks[$id]))){if(isset($ranks[$id]['snapshot'])){$rank['snapshot']=$ranks[$id]['snapshot'];}$ranks[$id]=$rank;$ok=true;}}}
        if(in_array($task,['freeze','unfreeze','delete'],true) && isset($ranks[$id])){
            if($task==='freeze' && Plugin::ready() && !isset($ranks[$id]['snapshot'])){$rows=Rankings::query($ranks[$id]);if($rows){$range=Rankings::dates($ranks[$id]);$totals=(new Stats())->get_totals(...$range);$ranks[$id]['snapshot']=['date'=>wp_date('Y-m-d H:i'),'range'=>Rankings::dates($ranks[$id]),'rows'=>$rows,'definition'=>Rankings::clean($ranks[$id]),'site_total'=>Rankings::site_total($ranks[$id]),'site_visitors'=>(int)($totals->visitors ?? 0)];$ok=true;}}
            if($task==='unfreeze'){unset($ranks[$id]['snapshot']);$ok=true;}
            if($task==='delete'){unset($ranks[$id]);$ok=true;}
        }
        if($task==='geo'){$ok=Tracking::download_geo();}
        if($task==='export'){
            $s=Plugin::settings();unset($s['users'],$s['geo_download'],$s['delete_data']);foreach($ranks as &$rank){unset($rank['snapshot']);}unset($rank);
            nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="tabulamea-configuration.json"');echo wp_json_encode(['format'=>1,'settings'=>$s,'rankings'=>$ranks],JSON_PRETTY_PRINT);exit;
        }
        if($task==='import'){
            try{ToolsActions::import_preview(['access','dashboard','collection','rankings'],true);$ok=true;}catch(\Throwable $error){$ok=false;}
        }
        if($ok){update_option('tbm_rankings',$ranks,false);update_option('tbm_revision',time(),false);}
        $destination=in_array($task,['ranking','freeze','unfreeze','delete'],true) ? add_query_arg('ranking',$id,admin_url('admin.php?page=tabulamea-reports')) : admin_url('options-general.php?page=tabulamea&tab='.($task==='geo'?'collection':'data'));
        wp_safe_redirect(add_query_arg('tbm_status',$ok?'ok':'error',$destination));exit;
    }
    /** Render the configurable private widget. @return void */
    public static function summary(): void { DashboardTools::render(); }
    /** Render saved rankings and editorial tools. @return void */
    public static function reports(): void { RankingAdmin::page(); }
    /** Render independent dimension reports. @return void */
    public static function dimensions(): void { DimensionReports::page(); }
}
