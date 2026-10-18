<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Atomic aggregation, short-lived delivery receipts and anonymous site-wide resource limits. */
final class CoreStore {
    /** Create bounded-index operational tables; neither table identifies a visitor. @return void */
    public static function install(): void {
        global $wpdb;$charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_receipts (token_hash char(64) NOT NULL, expires bigint unsigned NOT NULL, PRIMARY KEY  (token_hash), KEY expires (expires)) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_rate (bucket bigint unsigned NOT NULL, requests int unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (bucket)) ENGINE=InnoDB $charset;");
    }
    /** Apply an anonymous per-site request budget, independent of IP or browser identity.
     * @return bool|null Null on a storage failure; otherwise whether the valid request fits the configured per-minute budget.
     */
    public static function budget(): ?bool {
        global $wpdb;$bucket=(int)floor(time()/60);$limit=(int)Plugin::settings()['request_limit'];
        $prior=$wpdb->suppress_errors(true);try{
            $result=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_rate (bucket,requests) VALUES (%d,1) ON DUPLICATE KEY UPDATE requests=requests+1",$bucket));
            if($result===false){return null;}$count=$wpdb->get_var($wpdb->prepare("SELECT requests FROM {$wpdb->prefix}tbm_rate WHERE bucket=%d",$bucket));return $count===null?null:(int)$count<=$limit;
        }finally{$wpdb->suppress_errors($prior);}

    }
    /** Commit one pageview, all optional dimensions and a delivery receipt in one transaction.
     * @param string $date Site-local date.
     * @param int $id Public content ID, or zero for an archive.
     * @param string $path Signed query-free local path.
     * @param string $ref Normalized external referrer domain.
     * @param array $dimensions Independently classified finite categories.
     * @param string $event One-delivery random token, empty for legacy cached scripts.
     * @return bool Whether the delivery committed or had already committed.
     */
    public static function commit(string $date,int $id,string $path,string $ref,array $dimensions=[],string $event=''): bool {
        global $wpdb;$prior=$wpdb->suppress_errors(true);
        try {
            if($wpdb->query('START TRANSACTION')===false){return false;}
            if($event!==''){
                $receipt=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}tbm_receipts (token_hash,expires) VALUES (%s,%d)",hash('sha256',$event),time()+600));
                if($receipt===false){$wpdb->query('ROLLBACK');return false;}
                if($receipt===0){return $wpdb->query('COMMIT')!==false;}
            }
            $results=[$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_site_stats (date,pageviews) VALUES (%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1",$date)),
                $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_post_stats (date,post_id,path_hash,path,pageviews) VALUES (%s,%d,%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1",$date,$id,hash('sha256',$id.'|'.$path),$path))];
            if($ref && !in_array($ref,Plugin::settings()['referrer_blocklist'],true)){$results[]=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_referrer_stats (date,domain,pageviews) VALUES (%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1",$date,$ref));}
            foreach($dimensions as $dimension=>$value){if(!in_array($dimension,['device','browser','os','country'],true)||!is_string($value)||strlen($value)>32){$wpdb->query('ROLLBACK');return false;}$results[]=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_dimensions (date,dimension,value,pageviews) VALUES (%s,%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1",$date,$dimension,$value));}
            if(in_array(false,$results,true)){$wpdb->query('ROLLBACK');return false;}
            return $wpdb->query('COMMIT')!==false;
        }catch(\Throwable $error){$wpdb->query('ROLLBACK');return false;}
        finally{$wpdb->suppress_errors($prior);}
    }
    /** Remove expired operational records in small indexed batches, even when WP-Cron is delayed. @return bool Whether both cleanup statements succeeded. */
    public static function cleanup(): bool {
        global $wpdb;$receipts=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_receipts WHERE expires<%d LIMIT 1000",time()));$rate=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_rate WHERE bucket<%d LIMIT 1000",(int)floor(time()/60)-10));return $receipts!==false&&$rate!==false;
    }
    /** Record a coarse operational failure without raw request or database details.
     * @param string $code Fixed failure category.
     * @return void
     */
    public static function failure(string $code): void {
        $last=(array)get_option('tbm_core_error',[]);if(($last['code']??'')!==$code||(int)($last['time']??0)<time()-60){update_option('tbm_core_error',['time'=>time(),'code'=>in_array($code,['storage','capacity'],true)?$code:'storage','occurrences'=>(($last['code']??'')===$code&&(int)($last['time']??0)>time()-HOUR_IN_SECONDS)?(int)($last['occurrences']??1)+1:1],false);}
    }
}
