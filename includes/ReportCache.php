<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Bounded report caching without caching permissions or visitor information. */
final class ReportCache {
    /** @var array Request-local aggregates, separated by website. */
    private static array $memo=[];
    /** @var array Website IDs with a pending or failed rebuild in this request. */
    private static array $pending=[];
    /** Whether a report rebuild is pending or failed for this website. @return bool Pending state. */
    public static function pending(): bool {return !empty(self::$pending[get_current_blog_id()]);}
    /** @var array Website IDs whose editorial data changed during this request. */
    private static array $changed=[];
    /** Reuse an aggregate only within the current request.
     * @param string $key Internal aggregate identity.
     * @param callable $read Reader invoked on a miss.
     * @return mixed Aggregate result.
     */
    public static function once(string $key,callable $read) {
        $key=get_current_blog_id().'|'.$key;
        if(!array_key_exists($key,self::$memo)){self::$memo[$key]=$read();}
        return self::$memo[$key];
    }
    /** Forget request aggregates after successful writes. @return void */
    public static function reset(): void {self::$memo=[];}
    /** Invalidate report data after imports or retention cleanup. @return void */
    public static function invalidate(): void {self::reset();update_option('tbm_revision',microtime(true),false);}
    /** Invalidate editorial report caches once per modifying request. @return void */
    public static function editorial(): void {
        self::reset();$blog=get_current_blog_id();if(empty(self::$changed[$blog])){update_option('tbm_revision',microtime(true),false);self::$changed[$blog]=true;}
    }
    /** Return whether an authorized administrator requested a fresh report. @return bool Bypass flag. */
    public static function fresh(): bool {
        return is_admin()&&Plugin::allowed('view')&&isset($_GET['tbm_refresh'])&&is_string($_GET['tbm_refresh'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tbm_refresh'])),'tbm_refresh');
    }
    /** Read a bounded ranking result or acquire a short exclusive rebuild lease.
     * @param string $identity Stable query identity, excluding rendered content.
     * @param array $range Inclusive dates.
     * @param callable $read SQL reader returning an array or null on failure.
     * @return array Rows, or a short-lived empty result while another request rebuilds.
     */
    public static function ranking(string $identity,array $range,callable $read): array {
        $key='tbm_rank_'.md5($identity);$envelope=get_transient($key);$cached=is_array($envelope)&&isset($envelope['rows'])&&is_array($envelope['rows'])?$envelope['rows']:null;$fresh=self::fresh();
        if(!$fresh&&is_array($cached)&&(int)($envelope['until']??0)>time()){return $cached;}
        $lock='tbm_report_lock_'.md5($identity);$token=['expires'=>time()+30,'id'=>wp_generate_uuid4()];
        if(!add_option($lock,$token,'',false)){
            $existing=get_option($lock);
            if(!is_array($existing)||(int)($existing['expires']??0)>=time()){self::$pending[get_current_blog_id()]=true;return is_array($cached)?$cached:[];}
            // Compare-and-delete only the exact expired lease; never remove a renewed owner's lock.
            global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock,maybe_serialize($existing)));wp_cache_delete($lock,'options');
            if(!add_option($lock,$token,'',false)){self::$pending[get_current_blog_id()]=true;return is_array($cached)?$cached:[];}
        }
        try {
            $rows=$read();if(!is_array($rows)){self::$pending[get_current_blog_id()]=true;return is_array($cached)?$cached:[];}
            $ttl=$range[1]<wp_date('Y-m-d')?900:60;set_transient($key,['rows'=>$rows,'until'=>time()+$ttl],$ttl+300);return $rows;
        } finally {
            global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock,maybe_serialize($token)));wp_cache_delete($lock,'options');
        }
    }
}
