<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Local bot classification, explicit collection scope and anonymous configuration history. */
final class MeasurementPolicy {
    /** Register bounded history and frontend test controls. @return void */
    public static function boot(): void {add_action('template_redirect',static function(){if(self::testing()){nocache_headers();}});add_action('update_option_tbm_settings',[self::class,'changed'],10,2);}
    /** Validate collection policy without accepting unknown public types.
     * @param array $input Submitted fields.
     * @param array $settings Existing sanitized settings.
     * @return array Valid settings.
     */
    public static function sanitize(array $input,array $settings): array {
        foreach(['exclude_search_bots','exclude_ai_bots','search_enabled'] as $key){$settings[$key]=!empty($input[$key]);}
        $settings['measure_scope']=($input['measure_scope']??'all')==='selected'?'selected':'all';
        $settings['measure_types']=array_values(array_intersect(get_post_types(['public'=>true]),array_filter((array)($input['measure_types']??[]),'is_string')));
        $settings['measure_archives']=array_values(array_intersect(['home','posts','taxonomy','post_type','author','date','other'],array_filter((array)($input['measure_archives']??[]),'is_string')));
        $raw=$input['search_topics']??[];if(is_string($raw)){$raw=preg_split('/[\r\n]+/', $raw);}$topics=[];
        foreach(array_slice((array)$raw,0,50) as $text){if(!is_string($text)){continue;}$text=trim(sanitize_text_field($text));if($text!==''&&mb_strlen($text)<=80){$topics[substr(hash('sha256',SearchStats::normalize($text)),0,16)]=$text;}}
        $settings['search_topics']=$topics;return $settings;
    }
    /** Recognize locally declared automated clients; no address lookup or header storage.
     * @param string $agent Transient request header.
     * @return bool Whether this client is excluded.
     */
    public static function bot(string $agent): bool {
        $s=Plugin::settings();
        if(preg_match('/GPTBot|OAI-SearchBot|ChatGPT-User|ClaudeBot|Claude-SearchBot|Claude-User|anthropic-ai|PerplexityBot|Perplexity-User|CCBot|Bytespider|Amazonbot|Applebot-Extended/i',$agent)){return (bool)$s['exclude_ai_bots'];}
        if(preg_match('/Googlebot|GoogleOther|bingbot|msnbot|DuckDuckBot|Baiduspider|YandexBot|Applebot|Slurp|PetalBot/i',$agent)){return (bool)$s['exclude_search_bots'];}
        return (bool)preg_match('/bot|crawler|spider|headless|lighthouse|pagespeed|prerender|preview|wget|curl|python|httpclient|go-http-client/i',$agent);
    }
    /** Resolve the current public document's collection category. @return string Category key. */
    public static function category(): string {
        if(is_front_page()){return 'home';}if(is_singular()){return 'content';}if(is_home()){return 'posts';}if(is_category()||is_tag()||is_tax()){return 'taxonomy';}if(is_post_type_archive()){return 'post_type';}if(is_author()){return 'author';}if(is_date()){return 'date';}return 'other';
    }
    /** Determine collection eligibility before enqueuing a beacon. @return bool Allowed scope. */
    public static function eligible(): bool {
        $s=Plugin::settings();if($s['measure_scope']==='all'){return true;}$kind=self::category();return $kind==='content'?in_array(get_post_type(),$s['measure_types'],true):in_array($kind,$s['measure_archives'],true);
    }
    /** Sign coarse archive context bound to its already query-free page path.
     * @param string $path Signed page path.
     * @return array Signed context without taxonomy names, author names or query text.
     */
    public static function context(string $path=''): array {$kind=self::category();return ['kind'=>$kind,'proof'=>hash_hmac('sha256',get_current_blog_id().'|'.$kind.'|'.$path,wp_salt('auth'))];}
    /** Recheck current scope for old cached pages and signed archive contexts.
     * @param mixed $context Request context.
     * @param int $id Public content ID, or zero.
     * @param string $path Signed query-free page path.
     * @return array|false Verified category, or denied.
     */
    public static function validate_context($context,int $id,string $path='') {
        $s=Plugin::settings();if($id&&$s['measure_scope']==='selected'&&$id===(int)get_option('page_on_front')){return in_array('home',$s['measure_archives'],true)?['kind'=>'home']:false;}if($id){return $s['measure_scope']==='all'||in_array(get_post_type($id),$s['measure_types'],true)?['kind'=>'content']:false;}
        if(!is_array($context)||!isset($context['kind'],$context['proof'])){return $s['measure_scope']==='all'?['kind'=>'other']:false;}
        $kind=$context['kind'];if(!is_string($kind)||!in_array($kind,['home','posts','taxonomy','post_type','author','date','other','content'],true)||!is_string($context['proof'])||!hash_equals(hash_hmac('sha256',get_current_blog_id().'|'.$kind.'|'.$path,wp_salt('auth')),$context['proof'])){return false;}
        return $s['measure_scope']==='all'||in_array($kind,$s['measure_archives'],true)?['kind'=>$kind]:false;
    }
    /** Whether an authorized administrator opened a fresh dry-run link. @return bool Test mode. */
    public static function testing(): bool {return current_user_can('manage_options')&&isset($_GET['tbm_test'])&&is_string($_GET['tbm_test'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tbm_test'])),'tbm_measure_test');}
    /** Create a short-lived signature for a dry run without storing a visitor identity. @return string Test proof. */
    public static function test_token(): string {$time=time();return $time.'.'.hash_hmac('sha256',get_current_blog_id().'|test|'.$time,wp_salt('auth'));}
    /** Check dry-run authenticity; it can never write normal statistics.
     * @param mixed $token Signed timestamp.
     * @return bool Valid test.
     */
    public static function valid_test($token): bool {if(!is_string($token)||!preg_match('/^(\d+)\.([a-f0-9]{64})$/D',$token,$m)||abs(time()-(int)$m[1])>300){return false;}return hash_equals(hash_hmac('sha256',get_current_blog_id().'|test|'.$m[1],wp_salt('auth')),$m[2]);}
    /** Record only technical keys and pause state, never users or arbitrary setting values.
     * @param mixed $old Previous options.
     * @param mixed $new New options.
     * @return void
     */
    public static function changed($old,$new): void {
        $old=array_replace(Plugin::defaults(),(array)$old);$new=array_replace(Plugin::defaults(),(array)$new);$keys=['measurement','exclude_roles','measure_scope','measure_types','measure_archives','exclude_search_bots','exclude_ai_bots','technology','country','search_enabled','core_retention'];$changed=[];
        foreach($keys as $key){if(($old[$key]??null)!==($new[$key]??null)){$changed[]=$key;}}if(!$changed){return;}
        $history=(array)get_option('tbm_changes',[]);$history[]=['time'=>time(),'keys'=>$changed,'active'=>!empty($new['measurement'])];update_option('tbm_changes',array_slice($history,-200),false);
    }
    /** Render progressively disclosed settings with safe defaults. @return void */
    public static function fields(): void {
        $s=Plugin::settings();echo '<h3>'.esc_html__('Automated requests','tabulamea').'</h3>';
        InterfaceTools::check('tbm_settings[exclude_search_bots]',__('Exclude recognizable search-engine bots from statistics','tabulamea'),$s['exclude_search_bots']);echo '<p class="description">'.esc_html__('For example Google, Bing and DuckDuckGo. People arriving through these search engines are still counted.','tabulamea').'</p>';
        InterfaceTools::check('tbm_settings[exclude_ai_bots]',__('Exclude recognizable AI bots and assistants from statistics','tabulamea'),$s['exclude_ai_bots']);echo '<p class="description">'.esc_html__('For example OpenAI, Anthropic and Perplexity. People following links from these services are still counted. These options do not block access or change robots.txt. Bots without a measurement request remain invisible, even when an exclusion is disabled.','tabulamea').'</p>';
        echo '<h3>'.esc_html__('Collection scope','tabulamea').'</h3>';InterfaceTools::select('tbm_settings[measure_scope]',__('Which pages should be measured?','tabulamea'),['all'=>__('All public pages','tabulamea'),'selected'=>__('Selected content and archive types','tabulamea')],$s['measure_scope']);echo '<details><summary>'.esc_html__('Choose content and archive types','tabulamea').'</summary><p>'.esc_html__('These choices apply only to selected collection. Existing totals are retained. The website total then covers only the selected part of your website.','tabulamea').'</p>';
        echo '<h4>'.esc_html__('Content types','tabulamea').'</h4>';foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){InterfaceTools::check('tbm_settings[measure_types][]',$type->labels->name,in_array($type->name,$s['measure_types'],true),$type->name);}}
        echo '<h4>'.esc_html__('Archive types','tabulamea').'</h4>';foreach(['home'=>__('Front page','tabulamea'),'posts'=>__('Posts overview','tabulamea'),'taxonomy'=>__('Category, tag and public taxonomy archives','tabulamea'),'post_type'=>__('Content-type archives','tabulamea'),'author'=>__('Author archives','tabulamea'),'date'=>__('Date archives','tabulamea'),'other'=>__('Other public overview pages','tabulamea')] as $key=>$label){InterfaceTools::check('tbm_settings[measure_archives][]',$label,in_array($key,$s['measure_archives'],true),$key);}echo '</details>';
        echo '<h3>'.esc_html__('Internal search','tabulamea').'</h3><p>'.esc_html__('See which offers people search for, without retaining arbitrary search inputs. Select non-personal terms in advance; matching results-page views are counted under these terms. Names, email addresses and other unselected inputs are not saved as search text.','tabulamea').'</p>';InterfaceTools::check('tbm_settings[search_enabled]',__('Count search-result views separately','tabulamea'),$s['search_enabled']);echo '<p>'.esc_html__('Counts the first results page and searches without results. Reloads count again. Free search strings are never retained. Search counts do not increase regular pageview totals.','tabulamea').'</p><details><summary>'.esc_html__('Selected search terms','tabulamea').'</summary><label>'.esc_html__('Selected search terms: one exact phrase per line','tabulamea').'<textarea name="tbm_settings[search_topics]" rows="5">'.esc_textarea(implode("\n",$s['search_topics'])).'</textarea></label><p class="description">'.esc_html__('Use only non-personal topics. Matching ignores letter case and repeated spaces; unknown terms are not saved.','tabulamea').'</p><p>'.esc_html__('Examples for a club: Donations, Volunteering, Become a member, Events, Contact. These suggestions are not enabled automatically.','tabulamea').'</p><p>'.esc_html__('Example: if Donations is selected, a search for donations increases that counter. A search for Donations wanted does not match unless that whole phrase is selected separately. Other searches increase only the overall search total; their text is not retained. This does not change WordPress search results.','tabulamea').'</p><p>'.esc_html__('Example report: Donations · 18 search-result views · Last 30 days · Open search on website. Term totals appear from five views; missing terms do not prove zero interest. Counts describe first results-page loads, not people, and reloads count again. This feature monitors selected terms rather than discovering unknown queries.','tabulamea').'</p></details>';
    }
}
