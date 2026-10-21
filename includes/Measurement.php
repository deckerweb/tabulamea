<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Anonymous pageview collection with immediate daily aggregation and no visitor identifiers. */
final class Measurement {
    /** Register frontend collection and a public write-only endpoint. @return void */
    public static function boot(): void {
        add_action('wp_enqueue_scripts',[self::class,'enqueue']);
        add_action('rest_api_init',static function(){register_rest_route('tabulamea/v1','/pageview',['methods'=>'POST','callback'=>[self::class,'receive'],'permission_callback'=>'__return_true']);});
    }
    /** Create only plugin-owned aggregate tables. @return void */
    public static function install(): void {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_site_stats (date date NOT NULL, pageviews bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (date)) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_post_stats (date date NOT NULL, post_id bigint unsigned NOT NULL DEFAULT 0, path_hash char(64) NOT NULL, path varchar(768) NOT NULL, pageviews bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (date,path_hash), KEY post_date (post_id,date)) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_referrer_stats (date date NOT NULL, domain varchar(253) NOT NULL, pageviews bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (date,domain)) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_import_ledger (source varchar(16) NOT NULL, kind varchar(12) NOT NULL, source_key char(64) NOT NULL, pageviews bigint unsigned NOT NULL, PRIMARY KEY  (source,kind,source_key)) ENGINE=InnoDB $charset;");
        CoreStore::install();
    }
    /** Validate collection settings while preserving independently saved sections.
     * @param array $input Submitted settings.
     * @param array $settings Previously sanitized configuration.
     * @param string $scope Saved section identifier.
     * @return array Validated configuration.
     */
    public static function sanitize(array $input,array $settings,string $scope): array {
        if($scope!=='' && $scope!=='measurement'){return $settings;}
        $settings['measurement']=!empty($input['measurement']);
        $settings=MeasurementPolicy::sanitize($input,$settings);
        $settings['exclude_roles']=array_values(array_intersect(array_keys(wp_roles()->roles),array_filter((array)($input['exclude_roles'] ?? []),'is_string')));
        $settings['request_limit']=max(1000,min(600000,absint($input['request_limit'] ?? $settings['request_limit'])));
        $settings['core_retention']=max(0,min(120,absint($input['core_retention'] ?? 0)));
        $raw=is_string($input['referrer_blocklist'] ?? null)?preg_split('/[\r\n,]+/',$input['referrer_blocklist']):(array)($input['referrer_blocklist'] ?? []);
        $settings['referrer_blocklist']=array_values(array_filter(array_unique(array_map(static fn($v)=>is_string($v)?self::domain($v):'',array_slice($raw,0,100)))));
        return $settings;
    }
    /** Normalize a public HTTP hostname without paths, query strings or credentials.
     * @param string $url Referrer URL or hostname.
     * @return string Valid normalized domain, or an empty string.
     */
    public static function domain(string $url): string {
        if(!str_contains($url,'://')){$url='https://'.$url;}
        $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));$host=preg_replace('/^www\./','',$host);
        return strlen($host)<=253 && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D',$host)?$host:'';
    }
    /** Create a site-bound signature for a server-resolved content path.
     * @param int $id Public post identifier, or zero for an archive.
     * @param string $path Query-free local path.
     * @return string Message authentication code, never a visitor identifier.
     */
    public static function signature(int $id,string $path): string { return hash_hmac('sha256',get_current_blog_id().'|'.$id.'|'.$path,wp_salt('auth')); }
    /** Enqueue a small beacon only on eligible public frontend documents. @return void */
    public static function enqueue(): void {
        $s=Plugin::settings();if(!MeasurementPolicy::eligible() && !MeasurementPolicy::testing()){return;}if(!$s['measurement'] || !Plugin::ready() || is_preview() || is_feed() || is_404() || is_search() || is_trackback()){return;}
        if(!MeasurementPolicy::testing() && array_intersect(wp_get_current_user()->roles,$s['exclude_roles'])){return;}
        if(is_singular() && (!is_post_publicly_viewable(get_post()) || post_password_required())){return;}
        $path=wp_parse_url(wp_unslash((string)($_SERVER['REQUEST_URI'] ?? '/')),PHP_URL_PATH);if(!is_string($path) || strlen($path)>768){return;}
        $id=is_singular()?get_queried_object_id():0;
        wp_enqueue_script('tabulamea-measure',plugins_url('assets/measure.js',TBM_FILE),[],TBM_VERSION,['in_footer'=>true,'strategy'=>'defer']);
        wp_add_inline_script('tabulamea-measure','window.tabulaMeaPage='.wp_json_encode(['endpoint'=>rest_url('tabulamea/v1/pageview'),'post'=>$id,'path'=>$path,'signature'=>self::signature($id,$path),'context'=>MeasurementPolicy::context($path),'test'=>MeasurementPolicy::testing()?MeasurementPolicy::test_token():'']).';','before');
    }
    /** Reject recognizable automated and speculative requests without storing request headers.
     * @param string $agent Transient browser header.
     * @return bool Whether the request is excluded.
     */
    public static function automated(string $agent): bool {
        return $agent==='' || MeasurementPolicy::bot($agent)
            || (bool)preg_match('/prefetch|prerender/i',(string)($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? ''));
    }
    /** Validate a signed anonymous pageview; expose no statistics through the public endpoint.
     * @param \WP_REST_Request $request Incoming pageview payload.
     * @return \WP_REST_Response|\WP_Error Empty success or validation error.
     */
    public static function receive(\WP_REST_Request $request) {
        if(strlen($request->get_body())>4096){return new \WP_Error('invalid_payload',__('Invalid pageview.','tabulamea'),['status'=>400]);}
        if(!Plugin::settings()['measurement'] || !Plugin::ready()){return new \WP_REST_Response(null,204);}
        $ua=substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,1024);
        if(self::automated($ua) || ($_SERVER['HTTP_DNT'] ?? '')==='1' || ($_SERVER['HTTP_SEC_GPC'] ?? '')==='1'){return new \WP_REST_Response(null,204);}
        $origin=(string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if($origin!=='' && !self::same_origin($origin)){return new \WP_Error('invalid_origin',__('Invalid pageview.','tabulamea'),['status'=>403]);}
        $a=$request->get_json_params();if(!is_array($a)){return new \WP_Error('invalid_payload',__('Invalid pageview.','tabulamea'),['status'=>400]);}$path=$a['path'] ?? null;$signature=$a['signature'] ?? null;$id=absint(is_scalar($a['post'] ?? null)?$a['post']:0);
        if(!is_string($path)||!is_string($signature)||strlen($path)>768||!str_starts_with($path,'/')||str_contains($path,'?')||str_contains($path,'#')||preg_match('/[\x00-\x20\\\\]/',$path)||!hash_equals(self::signature($id,$path),$signature)){return new \WP_Error('invalid_page',__('Invalid pageview.','tabulamea'),['status'=>400]);}
        if($id){$post=get_post($id);if(!$post||!is_post_publicly_viewable($post)||$post->post_password!==''){return new \WP_REST_Response(null,204);}}
        if(MeasurementPolicy::valid_test($a['test']??'')){update_option('tbm_test_received',time(),false);return new \WP_REST_Response(null,204);}
        if($id===0&&$path==='/__search__'&&is_array($a['context']??null)&&array_key_exists('topic',$a['context'])){return SearchStats::receive($a);}
        $context=MeasurementPolicy::validate_context($a['context']??[],$id,$path);if($context===false){return new \WP_REST_Response(null,204);}
        
        $ref=is_string($a['referrer'] ?? null)?self::domain(substr($a['referrer'],0,1024)):'';
        if($ref===self::domain(home_url())){$ref='';}
        $event=$a['event']??'';if(!is_string($event)||($event!==''&&!preg_match('/^[a-f0-9]{32}$/D',$event))){return new \WP_Error('invalid_event',__('Invalid pageview.','tabulamea'),['status'=>400]);}
        $budget=CoreStore::budget();if($budget===null){CoreStore::failure('storage');return new \WP_Error('storage_failed',__('Statistics could not be saved.','tabulamea'),['status'=>503]);}
        if(!$budget){CoreStore::failure('capacity');$response=new \WP_REST_Response(null,429);$response->header('Retry-After','60');return $response;}
        $settings=Plugin::settings();$dimensions=$settings['technology']?Tracking::classify($ua):[];
        if($settings['country']){$dimensions['country']=Tracking::country((string)($_SERVER['REMOTE_ADDR']??''));}
        add_option('tbm_live_since',wp_date('Y-m-d'),'',false);
        $ok=CoreStore::commit(wp_date('Y-m-d'),$id,$path,$ref,$dimensions,$event);
        if(!$ok){CoreStore::failure('storage');return new \WP_Error('storage_failed',__('Statistics could not be saved.','tabulamea'),['status'=>503]);}
        foreach($dimensions as $dimension=>$value){add_option('tbm_since_'.$dimension,wp_date('Y-m-d'),'',false);}
        if((int)get_option('tbm_last_received',0)<time()-300){update_option('tbm_last_received',time(),false);}
        if(wp_rand(1,100)===1){CoreStore::cleanup();}
        return new \WP_REST_Response(null,204);
    }
    /** Atomically add one daily pageview to independent aggregate counters.
     * @param string $date Site-local calendar date.
     * @param int $id Content identifier.
     * @param string $path Signed local content path.
     * @param string $ref Normalized external referrer hostname.
     * @return bool Whether all required counters were stored.
     */
    public static function record(string $date,int $id,string $path,string $ref): bool {
        return CoreStore::commit($date,$id,$path,$ref);
    }
    /** Compare an Origin header with the configured website, including scheme and effective port.
     * @param string $origin Supplied origin.
     * @return bool Whether it is the exact website origin.
     */
    private static function same_origin(string $origin): bool {
        $a=wp_parse_url($origin);$b=wp_parse_url(home_url());if(!is_array($a)||!is_array($b)){return false;}
        return strtolower($a['scheme']??'')===strtolower($b['scheme']??'') && strtolower($a['host']??'')===strtolower($b['host']??'') && ($a['port']??(($a['scheme']??'')==='https'?443:80))===($b['port']??(($b['scheme']??'')==='https'?443:80));
    }
    /** Render measurement controls inside the site's Settings API form. @return void */
    public static function fields(): void {
        $s=Plugin::settings();echo '<h2>'.esc_html__('Privacy-friendly measurement','tabulamea').'</h2><p>'.esc_html__('Counts visible pageviews without cookies, fingerprinting, visitor identifiers or individual browsing histories. Do Not Track and Global Privacy Control are respected.','tabulamea').'</p>';
        Admin::checkbox('measurement',__('Enable pageview measurement','tabulamea'),$s);
        echo '<fieldset><legend>'.esc_html__('Do not count logged-in users with these roles','tabulamea').'</legend>';
        foreach(wp_roles()->roles as $key=>$role){InterfaceTools::check('tbm_settings[exclude_roles][]',translate_user_role($role['name']),in_array($key,$s['exclude_roles'],true),$key);}echo '</fieldset>';
        InterfaceTools::input('tbm_settings[core_retention]',__('Keep basic statistics for months (0 = keep until explicitly deleted)','tabulamea'),$s['core_retention'],'number');
        InterfaceTools::input('tbm_settings[request_limit]',__('Maximum valid measurement requests per minute (site-wide)','tabulamea'),$s['request_limit'],'number',['min'=>1000,'max'=>600000]);
        echo '<p><label for="tbm-referrer-blocklist">'.esc_html__('Hide these referrer domains from future referrer counts (one per line)','tabulamea').'</label><textarea id="tbm-referrer-blocklist" name="tbm_settings[referrer_blocklist]" rows="5" class="large-text">'.esc_textarea(implode("\n",$s['referrer_blocklist'])).'</textarea></p><p class="description">'.esc_html__('Pageviews still count. Existing referrer totals remain unchanged. No query strings or referrer paths are stored.','tabulamea').'</p>';MeasurementPolicy::fields();
    }
}
