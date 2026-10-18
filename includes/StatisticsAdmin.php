<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Responsive statistics with accessible interactive charts and useful editorial tables. */
final class StatisticsAdmin {
    /** Render the full private statistics screen. @return void */
    public static function page(): void {
        if(!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        echo '<div class="wrap tbm tbm-statistics">';Settings::header();Navigation::links();$a=ReportTools::requested();ReportTools::controls('tabulamea-statistics',$a);$range=Rankings::dates($a);
        if(!$range){echo '<p role="alert">'.esc_html__('Choose a valid date range.','tabulamea').'</p>';Admin::footer();echo '</div>';return;}
        Insights::filters($a);self::report($range,false,max(10,min(50,absint(InterfaceTools::text($_GET,'items','10')))));DataExports::controls($range);Admin::footer();echo '</div>';
    }
    /** Render a simple overview for compatibility with saved dashboard registrations. @return void */
    public static function widget(): void {DashboardTools::overview();}
    /** Render totals, period change, chart and parallel content/referrer tables.
     * @param array $range Inclusive site-local dates.
     * @param bool $compact Whether inside a WordPress dashboard widget.
     * @param int $items Maximum rows per list.
     * @param array $modules Enabled optional widget modules; ignored on full reports.
     * @return void
     */
    public static function report(array $range,bool $compact=false,int $items=10,array $modules=[]): void {
        $show=static fn($key)=>!$compact||in_array($key,$modules,true);$id=$compact?0:Insights::content();$stats=new Stats();$total=$id?$stats->content_total($id,$range):$stats->get_totals(...$range)->pageviews;$previous=$id?$stats->content_total($id,Rankings::previous($range)):$stats->get_totals(...Rankings::previous($range))->pageviews;$delta=$total-$previous;
        if($id){echo '<section class="tbm-card"><h2>'.esc_html(get_the_title($id)).'</h2><p><a href="'.esc_url(get_permalink($id)).'">'.esc_html__('View content','tabulamea').'</a>';if(current_user_can('edit_post',$id)){echo ' · <a href="'.esc_url(get_edit_post_link($id,'raw')).'">'.esc_html__('Edit content','tabulamea').'</a>';}echo '</p></section>';}
        echo '<section class="tbm-stat-cards"><div class="tbm-stat-card"><span>'.esc_html__('Pageviews','tabulamea').'</span><strong>'.esc_html(number_format_i18n($total)).'</strong><small>'.esc_html(self::date($range[0]).' – '.self::date($range[1])).'</small></div>'.($show('comparison')?'<div class="tbm-stat-card"><span>'.esc_html__('Compared with previous period','tabulamea').'</span><strong>'.esc_html(($delta>0?'+':'').number_format_i18n($delta)).'</strong><small>'.esc_html($previous>=10?sprintf(__('%s%% change','tabulamea'),number_format_i18n(100*$delta/$previous,1)):__('The previous baseline is too small for a useful percentage.','tabulamea')).'</small></div>':'').'</section>';
        if($range[1]>=wp_date('Y-m-d')){echo '<p class="description">'.esc_html__('Today is still incomplete.','tabulamea').'</p>';}
        if($show('chart')){$series=Insights::series($range,$id);$comparison=[];if(!$compact&&!empty($_GET['compare'])){$oldSeries=Insights::series(Rankings::previous($range),$id);foreach($oldSeries['values'] as $date=>$value){$comparison[$date]=in_array($date,$oldSeries['known'],true)?$value:null;}}self::chart($series['values'],Stats::monthly($range),$series['known'],$comparison,$compact);}
        if(!$id){$focus=InterfaceTools::text($_GET,'focus_date');$listRange=$range;if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$focus)&&$focus>=$range[0]&&$focus<=$range[1]&&Rankings::dates(['period'=>'custom','start'=>$focus,'end'=>$focus])){$listRange=[$focus,$focus];echo '<p class="tbm-attention">'.esc_html(sprintf(__('Lists filtered to %s','tabulamea'),self::date($focus))).' <a href="'.esc_url(remove_query_arg('focus_date')).'">'.esc_html__('Clear day filter','tabulamea').'</a></p>';}
            echo '<div class="tbm-report-grid">';if($show('contents')){echo '<section class="tbm-card"><h2>'.esc_html__('Most viewed content','tabulamea').'</h2>';self::contents($listRange,$items);echo '</section>';}if($show('referrers')){echo '<section class="tbm-card"><h2>'.esc_html__('Referring websites','tabulamea').'</h2>';self::referrers($listRange,$items);echo '</section>';}echo '</div>';
            if(!$compact){Insights::composition($listRange);}
        }
        if(!$compact){echo '<details class="tbm-card"><summary>'.esc_html__('How these numbers are measured','tabulamea').'</summary><p>'.esc_html__('Pageviews count visible page loads. Reloads count again. Recognizable bots, previews, search pages and excluded roles are omitted. No unique people, sessions or visitor profiles are calculated. Browser blocking and missing JavaScript can reduce counts.','tabulamea').'</p></details>';Insights::coverage($range);}
    }
    /** Format an ISO date in the website's timezone and preferred date format.
     * @param string $date Site-local date.
     * @return string Localized date.
     */
    public static function date(string $date): string {return wp_date(get_option('date_format'),(new \DateTimeImmutable($date,wp_timezone()))->getTimestamp());}
    /** Render content actions without hiding relevant recorded counts behind a plain list.
     * @param array $range Inclusive dates.
     * @param int $limit Row limit.
     * @return void
     */
    public static function contents(array $range,int $limit): void {
        $rows=Rankings::visible(['global_exclusions'=>false],Rankings::query(['period'=>'custom','start'=>$range[0],'end'=>$range[1],'type'=>InterfaceTools::text($_GET,'content_type','all'),'search'=>mb_substr(InterfaceTools::text($_GET,'content_search'),0,80),'limit'=>max(1,min(50,$limit)),'global_exclusions'=>false]));
        if(!$rows){echo '<p class="tbm-empty">'.esc_html__('No matching public content with recorded pageviews was found for these filters.','tabulamea').'</p>';return;}
        echo '<table class="tbm-data-table"><thead><tr><th scope="col">'.esc_html__('Content','tabulamea').'</th><th scope="col" class="tbm-number">'.esc_html__('Pageviews','tabulamea').'</th></tr></thead><tbody>';
        $site=(new Stats())->get_totals(...$range)->pageviews;foreach($rows as $row){$id=(int)$row['ID'];echo '<tr><td><a class="tbm-content-title" href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a>';if(current_user_can('edit_post',$id)){echo '<span class="tbm-row-actions"><a href="'.esc_url(get_edit_post_link($id,'raw')).'">'.esc_html__('Edit content','tabulamea').'</a> · <a href="'.esc_url(WordPressIntegration::content_url($id)).'">'.esc_html__('Statistics','tabulamea').'</a></span>';}echo '</td><td class="tbm-number"><strong>'.esc_html(number_format_i18n((int)$row['pageviews'])).'</strong><small class="tbm-row-actions">'.esc_html($site>0?number_format_i18n(100*(int)$row['pageviews']/$site,1).' %':'').'</small></td></tr>';}
        echo '</tbody></table>';
    }
    /** Render external referring domains with explicit safe link behavior and coverage limits.
     * @param array $range Inclusive dates.
     * @param int $limit Maximum displayed domains.
     * @return void
     */
    public static function referrers(array $range,int $limit): void {
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT domain,SUM(pageviews) views FROM {$wpdb->prefix}tbm_referrer_stats WHERE date BETWEEN %s AND %s GROUP BY domain ORDER BY views DESC,domain ASC LIMIT %d",...array_merge($range,[max(1,min(50,$limit))])),ARRAY_A);
        if(!$rows){echo '<p class="tbm-empty">'.esc_html__('No external referrers recorded for this period.','tabulamea').'</p>';}else{echo '<table class="tbm-data-table"><thead><tr><th scope="col">'.esc_html__('Referrer domain','tabulamea').'</th><th scope="col" class="tbm-number">'.esc_html__('Pageviews','tabulamea').'</th></tr></thead><tbody>';foreach($rows as $row){echo '<tr><td><a href="'.esc_url('https://'.$row['domain'].'/').'" target="_blank" rel="noopener noreferrer nofollow">'.esc_html(Insights::referrer_name($row['domain'])).'<span class="screen-reader-text"> · '.esc_html__('opens in a new tab','tabulamea').'</span></a></td><td class="tbm-number"><strong>'.esc_html(number_format_i18n((int)$row['views'])).'</strong></td></tr>';}echo '</tbody></table>';}
        echo '<p class="description">'.esc_html__('Only domains are retained. Missing referrers cannot be reconstructed; these counts do not represent all traffic sources.','tabulamea').'</p>';
    }
    /** Render exact values with gaps, optional comparison and a complete native table fallback.
     * @param array $daily Date-to-count mapping.
     * @param bool $monthly Whether values are calendar-month buckets.
     * @param array|null $known Dates with retained records; null means all supplied dates.
     * @param array $comparison Previous-period values aligned by position.
     * @param bool $compact Whether short widget axis dates are needed.
     * @return void
     */
    public static function chart(array $daily,bool $monthly=false,?array $known=null,array $comparison=[],bool $compact=false): void {
        $count=count($daily);if(!$count){return;}$known=$known??array_keys($daily);$all=array_merge(array_values($daily),array_values($comparison),[1]);$max=max($all);$top=max(1,(int)ceil($max/4)*4);$labels=[];$i=0;$segments=[];$segment=[];$bridges=[];$lastKnown=null;$lastIndex=-1;$priorPoints=[];$old=array_values($comparison);$pauses=Insights::pauses();
        foreach($daily as $date=>$views){$paused=false;$bucketEnd=$monthly?(new \DateTimeImmutable($date))->modify('last day of this month')->format('Y-m-d'):$date;foreach($pauses as $pause){if($date<=$pause[1]&&$bucketEnd>=$pause[0]){$paused=true;break;}}$x=50+($count===1?330:660*$i/($count-1));$y=195-160*$views/$top;$recorded=in_array($date,$known,true);if($recorded){$point=round($x,2).','.round($y,2);if($lastKnown!==null&&$i>$lastIndex+1){$bridges[]=[$lastKnown,$point];}$lastKnown=$point;$lastIndex=$i;$segment[]=$point;}elseif($segment){$segments[]=$segment;$segment=[];}$labels[]=['x'=>$x,'y'=>$y,'iso'=>$date,'date'=>$monthly?wp_date('F Y',(new \DateTimeImmutable($date,wp_timezone()))->getTimestamp()):self::date($date),'count'=>$recorded?number_format_i18n($views):__('No retained record','tabulamea'),'recorded'=>$recorded,'paused'=>$paused,'previous'=>isset($old[$i])?number_format_i18n($old[$i]):''];if(isset($old[$i])){$priorPoints[]=round($x,2).','.round(195-160*$old[$i]/$top,2);}$i++;}if($segment){$segments[]=$segment;}
        $prefs=(array)get_user_option('tbm_preferences');$settings=Plugin::settings();$dashboard=is_admin()&&get_current_screen()&&get_current_screen()->id==='dashboard';$style=$dashboard?$settings['chart_style']:InterfaceTools::text($_GET,'chart',$prefs['chart']??$settings['chart_style']);
        echo '<section class="tbm-card tbm-chart'.($style==='bars'?' tbm-chart-bars':'').'" data-tbm-chart><div class="tbm-section-heading"><h2>'.esc_html__('Pageviews over time','tabulamea').'</h2><span class="description">'.esc_html($monthly?__('Monthly totals','tabulamea'):__('Daily totals','tabulamea')).'</span></div>';Insights::chart_controls();echo '<details class="tbm-chart-help"><summary>'.esc_html__('Chart help','tabulamea').'</summary><p>'.esc_html__('Hover or tap for exact values. Use Tab and arrow keys; activate a daily point to filter the lists. Gaps mean no retained record, not proven zero traffic.','tabulamea').'</p>';
        echo '<p class="tbm-chart-legend"><span class="tbm-legend-gap"></span>'.esc_html__('Dotted connection: no retained values between these points. It does not estimate daily counts.','tabulamea').'</p>';
        echo '<p class="tbm-bars-note description">'.esc_html__('No bar: zero views or no retained record. Hover, tap or use the values table to check each date.','tabulamea').'</p></details>';
        if($comparison){echo '<p class="tbm-chart-legend">'.esc_html__('Solid: selected period · Long dashes: preceding period','tabulamea').'</p>';}
        echo '<div class="tbm-chart-canvas"><svg viewBox="0 0 736 228" role="group" aria-label="'.esc_attr__('Interactive pageview chart','tabulamea').'">';
        foreach($labels as $label){if($label['paused']){$width=max(3,660/max(1,$count));echo '<rect class="tbm-pause-band" x="'.esc_attr((string)max(50,$label['x']-$width/2)).'" y="30" width="'.esc_attr((string)$width).'" height="165"><title>'.esc_html__('Known collection pause within this interval','tabulamea').'</title></rect>';}}
        for($tick=0;$tick<=4;$tick++){$y=195-$tick*40;echo '<path d="M50 '.$y.'H710" class="tbm-grid-line"/><text class="tbm-axis-y" x="40" y="'.($y+4).'" text-anchor="end">'.esc_html(number_format_i18n((int)($tick*$top/4))).'</text>';}
        foreach($bridges as $points){echo '<polyline points="'.esc_attr(implode(' ',$points)).'" class="tbm-gap-line"/>';}
        foreach($segments as $points){echo '<polyline points="'.esc_attr(implode(' ',$points)).'" class="tbm-line"/>';}
        if($priorPoints){echo '<polyline points="'.esc_attr(implode(' ',$priorPoints)).'" class="tbm-previous"/>';}
        echo '<line class="tbm-crosshair" x1="50" x2="50" y1="24" y2="195" hidden/>';
        foreach($labels as $index=>$label){$caption=$label['date'].' · '.$label['count'].($label['paused']?' · '.__('Known collection pause within this interval','tabulamea'):'');if($label['recorded']){$width=max(1,min(32,550/$count));echo '<rect x="'.esc_attr((string)($label['x']-$width/2)).'" y="'.esc_attr((string)$label['y']).'" width="'.esc_attr((string)$width).'" height="'.esc_attr((string)(195-$label['y'])).'" class="tbm-bar"/>';}
            echo '<circle class="tbm-chart-point'.(!$label['recorded']?' tbm-unknown':'').'" cx="'.esc_attr((string)round($label['x'],2)).'" cy="'.esc_attr((string)round($label['y'],2)).'" r="5" tabindex="'.($index===0?'0':'-1').'" role="button" aria-label="'.esc_attr($caption).'" data-date="'.esc_attr($label['date']).'" data-iso="'.esc_attr($monthly?'':$label['iso']).'" data-count="'.esc_attr($label['count']).'" data-note="'.esc_attr($label['paused']?__('Known collection pause within this interval','tabulamea'):'').'" data-previous="'.esc_attr($label['previous']).'"><title>'.esc_html($caption).'</title></circle>';
        }
        foreach([0.25,0.5,0.75] as $fraction){if($count<8){continue;}$at=(int)round(($count-1)*$fraction);echo '<text class="tbm-axis-middle" x="'.esc_attr((string)(50+660*$fraction)).'" y="220" text-anchor="middle">'.esc_html($monthly?wp_date('M Y',(new \DateTimeImmutable($labels[$at]['iso'],wp_timezone()))->getTimestamp()):wp_date('j M',(new \DateTimeImmutable($labels[$at]['iso'],wp_timezone()))->getTimestamp())).'</text>';}
        echo '<text class="tbm-axis-start" x="50" y="220">'.esc_html(($compact?wp_date('d.m.Y',(new \DateTimeImmutable((string)array_key_first($daily),wp_timezone()))->getTimestamp()):self::date((string)array_key_first($daily)))).'</text><text class="tbm-axis-end" x="710" y="220" text-anchor="end">'.esc_html(($compact?wp_date('d.m.Y',(new \DateTimeImmutable((string)array_key_last($daily),wp_timezone()))->getTimestamp()):self::date((string)array_key_last($daily)))).'</text></svg><output class="tbm-chart-tooltip" hidden></output></div><details><summary>'.esc_html($monthly?__('Show monthly values','tabulamea'):__('Show daily values','tabulamea')).'</summary><div class="tbm-daily-scroll"><table class="tbm-data-table"><thead><tr><th scope="col">'.esc_html__('Date','tabulamea').'</th><th scope="col">'.esc_html__('Pageviews','tabulamea').'</th></tr></thead><tbody>';
        foreach($labels as $label){echo '<tr><th scope="row">'.esc_html($label['date']).'</th><td>'.esc_html($label['count']).'</td></tr>';}echo '</tbody></table></div></details></section>';
    }
}
