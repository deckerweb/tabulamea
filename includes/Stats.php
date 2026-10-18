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
        global $wpdb;if(self::monthly($range)){$rows=$wpdb->get_results($wpdb->prepare("SELECT DATE_FORMAT(date,'%%Y-%%m-01') date,SUM(pageviews) pageviews FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s GROUP BY DATE_FORMAT(date,'%%Y-%%m-01') ORDER BY date",$range),OBJECT_K);$out=[];$d=(new \DateTimeImmutable($range[0],wp_timezone()))->modify('first day of this month');$end=new \DateTimeImmutable($range[1],wp_timezone());while($d<=$end&&count($out)<2400){$key=$d->format('Y-m-d');$out[$key]=(int)($rows[$key]->pageviews??0);$d=$d->modify('+1 month');}return $out;}$rows=$wpdb->get_results($wpdb->prepare("SELECT date,pageviews FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s ORDER BY date",$range),OBJECT_K);$out=[];
        $d=new \DateTimeImmutable($range[0],wp_timezone());$end=new \DateTimeImmutable($range[1],wp_timezone());
        while($d<=$end && count($out)<3660){$key=$d->format('Y-m-d');$out[$key]=(int)($rows[$key]->pageviews ?? 0);$d=$d->modify('+1 day');}return $out;
    }
}
