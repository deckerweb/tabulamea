<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Reads anonymous daily aggregates without reconstructing visitors or sessions. */
final class Stats {
    /** Sum pageviews across an inclusive interval.
     * @param string $start First site-local date.
     * @param string $end Last site-local date.
     * @return object Pageview total; visitor counts are intentionally unavailable.
     */
    public function get_totals(string $start,string $end): object {
        global $wpdb;return (object)['pageviews'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pageviews),0) FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s",$start,$end))];
    }
    /** Sum a single public content item's retained pageviews.
     * @param int $id Content identifier.
     * @param array $range Inclusive site-local dates.
     * @return int Aggregate pageviews.
     */
    public function content_total(int $id,array $range): int {global $wpdb;return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pageviews),0) FROM {$wpdb->prefix}tbm_post_stats WHERE post_id=%d AND date BETWEEN %s AND %s",$id,...$range));}
    /** Choose monthly aggregation only for custom ranges longer than ten years.
     * @param array $range Inclusive date pair.
     * @return bool Whether monthly buckets are needed.
     */
    public static function monthly(array $range): bool {return (new \DateTimeImmutable($range[0]))->diff(new \DateTimeImmutable($range[1]))->days>=3660;}
    /** Read bounded daily counters, including zero days for an honest chart.
     * @param array $range Inclusive date pair.
     * @return array Date-to-count mapping.
     */
    public static function daily(array $range): array {
        global $wpdb;$monthly=self::monthly($range);$group=$monthly?"DATE_FORMAT(date,'%%Y-%%m-01')":'date';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT $group date,SUM(pageviews) pageviews FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s GROUP BY $group ORDER BY date",$range),OBJECT_K);
        $out=self::calendar($range);foreach($out as $day=>$_){$out[$day]=(int)($rows[$day]->pageviews??0);}return $out;
    }
    /** Build bounded daily or monthly date keys without reading stored counters.
     * @param array $range Inclusive site-local date pair.
     * @return array Date keys initialized to zero.
     */
    public static function calendar(array $range): array {
        $monthly=self::monthly($range);$out=[];$d=new \DateTimeImmutable($range[0],wp_timezone());if($monthly){$d=$d->modify('first day of this month');}$end=new \DateTimeImmutable($range[1],wp_timezone());
        while($d<=$end&&count($out)<($monthly?2400:3660)){$out[$d->format('Y-m-d')]=0;$d=$d->modify($monthly?'+1 month':'+1 day');}return $out;
    }
}
