<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Explicit day, week and month display buckets built only from retained aggregates. */
final class ChartBuckets {
    /** Group a daily series without inventing counts for missing days.
     * @param array $series Values and known daily dates.
     * @param string $resolution day, week or month.
     * @return array Bucket values, dates having any retained records, and partial-bucket dates.
     */
    public static function group(array $series,string $resolution): array {
        if(!in_array($resolution,['week','month'],true)){return $series;}$values=[];$known=[];$partial=[];$members=[];$recorded=array_fill_keys($series['known'],true);
        foreach($series['values'] as $day=>$views){$date=new \DateTimeImmutable($day,wp_timezone());$key=$date->modify($resolution==='week'?'monday this week':'first day of this month')->format('Y-m-d');$values[$key]=($values[$key]??0)+(int)$views;$members[$key]=($members[$key]??0)+1;if(isset($recorded[$day])){$known[$key]=($known[$key]??0)+1;}}
        foreach($members as $key=>$count){$expected=$resolution==='week'?7:(int)(new \DateTimeImmutable($key,wp_timezone()))->format('t');if(($known[$key]??0)<$count||$count<$expected){$partial[]=$key;}}return ['values'=>$values,'known'=>array_keys($known),'partial'=>$partial];
    }
    /** Choose a bounded explicit chart resolution, retaining old long-range monthly behavior.
     * @param array $range Inclusive date pair.
     * @return string Effective display resolution.
     */
    public static function resolution(array $range): string {
        if(Stats::monthly($range)){return 'month';}$value=InterfaceTools::text($_GET,'resolution','day');return in_array($value,['day','week','month'],true)?$value:'day';
    }
}
