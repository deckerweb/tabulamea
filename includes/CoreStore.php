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
        global $wpdb;
        if ($event !== '' && !preg_match('/^[a-f0-9]{32}$/D', $event)) { return false; }
        foreach ($dimensions as $dimension => $value) {
            if (!in_array($dimension, ['device','browser','os','country'], true) || !is_string($value) || strlen($value) > 32) { return false; }
        }
        try {
            $queries = [
                $wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_site_stats (date,pageviews) VALUES (%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1", $date),
                $wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_post_stats (date,post_id,path_hash,path,pageviews) VALUES (%s,%d,%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1", $date, $id, hash('sha256',$id.'|'.$path), $path),
            ];
            if ($ref && !in_array($ref, Plugin::settings()['referrer_blocklist'], true)) {
                $queries[] = $wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_referrer_stats (date,domain,pageviews) VALUES (%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1", $date, $ref);
            }
            foreach ($dimensions as $dimension => $value) {
                $queries[] = $wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_dimensions (date,dimension,value,pageviews) VALUES (%s,%s,%s,1) ON DUPLICATE KEY UPDATE pageviews=pageviews+1", $date, $dimension, $value);
            }
            return self::transaction($queries, $event);
        } catch (\Throwable $error) { return false; }
    }

    /**
     * Commit a search total and its optional approved topic with one delivery receipt.
     *
     * @param string $date Site-local aggregation date.
     * @param string $topic Approved hexadecimal topic key, or empty for the total only.
     * @param int $empty Whether the results page contained no results, zero or one.
     * @param string $event Random per-document delivery token, or empty for legacy delivery.
     * @return bool Whether all counters committed or this delivery was already committed.
     */
    public static function search(string $date, string $topic, int $empty, string $event = ''): bool {
        global $wpdb;
        if (!in_array($empty, [0,1], true) || ($topic !== '' && !preg_match('/^[a-f0-9]{16}$/D', $topic)) || ($event !== '' && !preg_match('/^[a-f0-9]{32}$/D', $event))) { return false; }
        try {
            $queries = [];
            foreach ($topic === '' ? [''] : ['', $topic] as $key) {
                $queries[] = $wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_search_stats (date,topic,no_results,views) VALUES (%s,%s,%d,1) ON DUPLICATE KEY UPDATE views=views+1", $date, $key, $empty);
            }
            return self::transaction($queries, $event);
        } catch (\Throwable $error) { return false; }
    }

    /**
     * Apply a historical source increment and its permanent receipt atomically.
     *
     * @param string $source Fixed source identifier: koko or statify.
     * @param string $kind Fixed source group.
     * @param string $key SHA-256 source-row identity, without source text.
     * @param int $views Cumulative source count.
     * @param callable $write Internal callback accepting the positive increment and returning write success.
     * @return bool Whether the increment and receipt committed or were already imported.
     */
    public static function import(string $source,string $kind,string $key,int $views,callable $write): bool {
        if (!in_array($source,['koko','statify'],true) || !in_array($kind,['site','posts','referrers','row'],true) || !preg_match('/^[a-f0-9]{64}$/D',$key) || $views<0) { return false; }
        return self::transaction([], '', static function() use($source,$kind,$key,$views,$write): bool {
            global $wpdb;
            $previous=$wpdb->get_var($wpdb->prepare("SELECT pageviews FROM {$wpdb->prefix}tbm_import_ledger WHERE source=%s AND kind=%s AND source_key=%s FOR UPDATE",$source,$kind,$key));
            if ($wpdb->last_error) { return false; }
            $delta=max(0,$views-(int)$previous);
            if ($delta>0 && !$write($delta)) { return false; }
            return $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_import_ledger (source,kind,source_key,pageviews) VALUES (%s,%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=GREATEST(pageviews,VALUES(pageviews))",$source,$kind,$key,$views))!==false;
        });
    }

    /**
     * Execute a bounded counter transaction; stop at the first SQL or connection error.
     *
     * This owns the transaction and must not run inside another transaction. Strict
     * mysqli reporting prevents wpdb from transparently reconnecting and replaying
     * a counter statement outside its lost transaction. The request-local driver
     * mode and wpdb error-display setting are restored on every exit. Only known
     * rollback errors are retried, never ambiguous connection or commit failures.
     *
     * @param string[] $queries Fully prepared internal counter statements, in lock order.
     * @param string $event Per-document token shared by every delivery retry.
     * @param callable|null $operation Optional internal import operation returning success inside the transaction.
     * @return bool Whether the transaction or an existing delivery receipt committed.
     */
    private static function transaction(array $queries, string $event, ?callable $operation=null): bool {
        global $wpdb;
        if (!($wpdb->dbh instanceof \mysqli)) { return false; }
        $driver = new \mysqli_driver();
        $reporting = $driver->report_mode;
        $suppressed = $wpdb->suppress_errors(true);
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $started = false;
                try {
                    if ($wpdb->query('START TRANSACTION') === false) { return false; }
                    $started = true;
                    $duplicate = false;
                    if ($event !== '') {
                        $receipt = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->prefix}tbm_receipts (token_hash,expires) VALUES (%s,%d)", hash('sha256',$event), time()+600));
                        if ($receipt === false) { throw new \RuntimeException('receipt_failed'); }
                        $duplicate = $receipt === 0;
                    }
                    if (!$duplicate) {
                        if ($operation!==null && !$operation()) { throw new \RuntimeException('operation_failed'); }
                        foreach ($queries as $query) {
                            if ($wpdb->query($query) === false) { throw new \RuntimeException('counter_failed'); }
                        }
                    }
                    if ($wpdb->query('COMMIT') === false) { throw new \RuntimeException('commit_failed'); }
                    return true;
                } catch (\Throwable $error) {
                    $rolledBack = false;
                    if ($started) {
                        try { $rolledBack = $wpdb->query('ROLLBACK') !== false; } catch (\Throwable $rollbackError) { /* A disconnected session cannot be rolled back locally. */ }
                    }
                    $retryable = $error instanceof \mysqli_sql_exception && in_array((int)$error->getCode(), [1213,1205], true);
                    if (!$retryable || !$rolledBack || $attempt === 2) { return false; }
                    usleep(wp_rand(10000,30000) * ($attempt+1));
                }
            }
            return false;
        } finally {
            mysqli_report($reporting);
            $wpdb->suppress_errors($suppressed);
        }
    }
    /** Remove expired operational records in small indexed batches, even when WP-Cron is delayed. @return bool Whether both cleanup statements succeeded. */
    public static function cleanup(): bool {
        global $wpdb;$receipts=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_receipts WHERE expires<%d LIMIT 1000",time()));$rate=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_rate WHERE bucket<%d LIMIT 1000",(int)floor(time()/60)-10));return $receipts!==false&&$rate!==false;
    }
    /** Check indexed expiry ranges for bounded background cleanup, without counting all rows.
     * @return bool Whether expired operational records remain.
     */
    public static function cleanup_pending(): bool {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}tbm_receipts WHERE expires<%d LIMIT 1",time()))
            || (bool)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}tbm_rate WHERE bucket<%d LIMIT 1",(int)floor(time()/60)-10));
    }
    /** Record a coarse operational failure without raw request or database details.
     * @param string $code Fixed failure category.
     * @return void
     */
    public static function failure(string $code): void {
        $last=(array)get_option('tbm_core_error',[]);if(($last['code']??'')!==$code||(int)($last['time']??0)<time()-60){update_option('tbm_core_error',['time'=>time(),'code'=>in_array($code,['storage','capacity'],true)?$code:'storage','occurrences'=>(($last['code']??'')===$code&&(int)($last['time']??0)>time()-HOUR_IN_SECONDS)?(int)($last['occurrences']??1)+1:1],false);}
    }
}
