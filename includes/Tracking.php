<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/**
 * Collects optional aggregate dimensions without persistent visitor identifiers.
 */
final class Tracking {
    /**
     * Load isolated MMDB reader classes.
     * @param string $class Requested class.
     * @return void
     */
    public static function autoload(string $class): void {
        $prefix='Deckerweb\\TabulaMea\\Vendor\\MaxMind\\Db\\';
        if (str_starts_with($class,$prefix)) { $path=dirname(TBM_FILE).'/vendor/maxmind/src/MaxMind/Db/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; if (is_file($path)) { require_once $path; } }
    }
    /**
     * Classify coarse browser, OS and device categories; ambiguous cases remain unknown.
     * @param string $ua Browser header.
     * @return array<string,string> Finite category labels.
     */
    public static function classify(string $ua): array {
        $browser='Unknown'; $os='Unknown'; $device='Unknown';
        foreach (['Edge'=>'~Edg(?:e|A|iOS)?/~','Opera'=>'~OPR/|Opera~','Samsung Internet'=>'~SamsungBrowser/~','Firefox'=>'~Firefox/|FxiOS/~','Chrome'=>'~Chrome/|CriOS/~','Safari'=>'~Version/.*Safari/~'] as $name=>$re) { if (preg_match($re,$ua)) { $browser=$name; break; } }
        foreach (['Android'=>'~Android~','iOS'=>'~iPhone|iPad|iPod~','Windows'=>'~Windows NT~','Chrome OS'=>'~CrOS~','macOS'=>'~Macintosh|Mac OS X~','Linux'=>'~Linux|X11~'] as $name=>$re) { if (preg_match($re,$ua)) { $os=$name; break; } }
        if (preg_match('~iPad|Tablet|Kindle|Silk/~i',$ua) || ($os==='Android' && !str_contains($ua,'Mobile'))) { $device='Tablet'; }
        elseif (preg_match('~Mobile|iPhone|iPod~i',$ua)) { $device='Smartphone'; }
        elseif (in_array($os,['Windows','macOS','Linux','Chrome OS'],true)) { $device='Desktop'; }
        return ['device'=>$device,'browser'=>$browser,'os'=>$os];
    }
    /**
     * Translate coarse report values without changing stored categories.
     * @param string $value Stored category or country code.
     * @return string Localized display label.
     */
    public static function label(string $value): string {
        $labels=['Unknown'=>__('Unknown','tabulamea'),'Desktop'=>__('Desktop','tabulamea'),'Tablet'=>__('Tablet','tabulamea'),'Smartphone'=>__('Smartphone','tabulamea')];
        if(isset($labels[$value])){return $labels[$value];}
        if(preg_match('/^[A-Z]{2}$/D',$value) && class_exists('Locale')){return \Locale::getDisplayRegion('-'.$value,determine_locale());}
        return $value;
    }
    /** Check availability of the local aggregate store. @return bool Whether collection is ready. */
    public static function supported(): bool { return Plugin::ready(); }
    /** Collect independent coarse totals for one accepted anonymous pageview.
     * @param array $data Accepted marker, returned unchanged.
     * @return array Original marker.
     */
    public static function collect(array $data): array {
        $s=Plugin::settings();if(!self::supported() || (!$s['technology'] && !$s['country'])){return $data;}
        $values=$s['technology']?self::classify(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,1024)):[];
        if($s['country']){$values['country']=self::country((string)($_SERVER['REMOTE_ADDR'] ?? ''));}
        global $wpdb;$date=wp_date('Y-m-d');foreach($values as $dimension=>$value){$result=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_dimensions (date,dimension,value,pageviews) VALUES (%s,%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1",$date,$dimension,$value));if($result!==false){add_option('tbm_since_'.$dimension,$date,'',false);}}return $data;
    }
    /**
     * Return the fixed per-site database location.
     * @return string Absolute database path.
     */
    public static function geo_path(): string { return wp_upload_dir(null,false)['basedir'].'/tabulamea/country.mmdb'; }
    /**
     * Resolve country locally using only the direct remote IP, without trusting proxy headers.
     * @param string $ip Connection address.
     * @return string ISO country or Unknown.
     */
    public static function country(string $ip): string {
        if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) || !is_readable(self::geo_path())) { return 'Unknown'; }
        try { spl_autoload_register([self::class,'autoload']); $reader=new Vendor\MaxMind\Db\Reader(self::geo_path()); $result=$reader->get($ip); $reader->close(); $country=$result['country']['iso_code'] ?? ''; return preg_match('/^[A-Z]{2}$/D',$country) ? $country : 'Unknown'; } catch (\Throwable $e) { return 'Unknown'; }
    }
    /**
     * Fetch one monthly DB-IP Lite database after explicit opt-in, with size and format limits.
     * @return bool Whether database was replaced.
     */
    public static function download_geo(): bool {
        $s=Plugin::settings(); if (!$s['country'] || !$s['geo_download']) { return false; }
        $month=gmdate('Y-m');
        $url='https://download.db-ip.com/free/dbip-country-lite-'.$month.'.mmdb.gz';
        $dir=dirname(self::geo_path()); if (!wp_mkdir_p($dir)) { return false; }
        $lock=$dir.'/update.lock'; $handle=@fopen($lock,'c'); if (!$handle || !flock($handle,LOCK_EX|LOCK_NB)) { if ($handle) { fclose($handle); } return false; }
        $gz=$dir.'/country.download.gz'; $tmp=$dir.'/country.download.mmdb'; $success=false;
        try {
            $response=wp_safe_remote_get($url,['timeout'=>45,'redirection'=>0,'stream'=>true,'filename'=>$gz,'limit_response_size'=>20*1024*1024]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200 || !is_file($gz) || filesize($gz)>=20*1024*1024) { return false; }
            $in=@gzopen($gz,'rb'); $out=@fopen($tmp,'wb'); if (!$in || !$out) { if($in){gzclose($in);} if($out){fclose($out);} return false; }
            $size=0;
            while(!gzeof($in)) { $chunk=gzread($in,65536); if ($chunk===false || ($size+=strlen($chunk))>40*1024*1024 || fwrite($out,$chunk)!==strlen($chunk)) { $size=0; break; } }
            gzclose($in); fclose($out); if (!$size) { return false; }
            spl_autoload_register([self::class,'autoload']); $reader=new Vendor\MaxMind\Db\Reader($tmp); $meta=$reader->metadata(); $reader->close();
            if (!str_contains(strtolower($meta->databaseType),'country')) { return false; }
            $success=rename($tmp,self::geo_path()); if($success){ update_option('tbm_geo_month',$month,false); update_option('tbm_geo_updated',time(),false); }
            return $success;
        } catch (\Throwable $e) { return false; }
        finally { foreach([$gz,$tmp] as $file){ if(is_file($file)){wp_delete_file($file);} } flock($handle,LOCK_UN); fclose($handle); }
    }
    /**
     * Prune only this plugin's aggregate history and refresh opted-in database monthly.
     * @return void
     */
    public static function maintenance(): void {
        $ok=CoreStore::cleanup();
        global $wpdb; $s=Plugin::settings(); $cutoff=(new \DateTimeImmutable('today',wp_timezone()))->modify('-'.max(1,min(120,(int)$s['retention'])).' months')->format('Y-m-d');
        if($wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_dimensions WHERE date < %s LIMIT 1000",$cutoff))===false){$ok=false;}
        if($s['core_retention']>0){$core=(new \DateTimeImmutable('today',wp_timezone()))->modify('-'.(int)$s['core_retention'].' months')->format('Y-m-d');foreach(['site_stats','post_stats','referrer_stats','search_stats'] as $name){if($wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_{$name} WHERE date < %s LIMIT 1000",$core))===false){$ok=false;}}}
        if ($s['country'] && $s['geo_download'] && get_option('tbm_geo_month')!==gmdate('Y-m')) { if(!self::download_geo()){$ok=false;} }
        if($ok){update_option('tbm_last_maintenance',time(),false);}else{CoreStore::failure('storage');}
    }
}
