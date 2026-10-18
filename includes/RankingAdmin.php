<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Ranking starters, draft previews, saved definitions and copyable editorial output. */
final class RankingAdmin {
    /** Return safe, unpublished starter definitions. @return array Starter definitions. */
    public static function starters(): array {
        return ['popular-posts'=>['title'=>__('Most popular posts','tabulamea'),'type'=>'post','period'=>'30_days','counts'=>1],'popular-pages'=>['title'=>__('Most popular pages','tabulamea'),'type'=>'page','period'=>'30_days','counts'=>1],'previous-year'=>['title'=>__('Top 10 of the previous year','tabulamea'),'type'=>'post','period'=>'previous_year','counts'=>1],'category-posts'=>['title'=>__('Popular posts in a category','tabulamea'),'type'=>'post','period'=>'30_days','counts'=>1]];
    }
    /** Return translated analytical modes. @return array Mode labels. */
    public static function modes(): array { return ['popular'=>__('Most viewed','tabulamea'),'rising'=>__('Gaining attention','tabulamea'),'falling'=>__('Losing attention','tabulamea'),'evergreen'=>__('Popular every month','tabulamea'),'rediscovered'=>__('Older content gaining attention','tabulamea')]; }
    /**
     * Offer a bounded selection of public content and retain already selected IDs.
     * @param string $name Field name.
     * @param array $selected Currently excluded IDs.
     * @return void
     */
    public static function content_picker(string $name,array $selected): void {
        $types=array_values(array_diff(get_post_types(['public'=>true]),['attachment']));$search=InterfaceTools::text($_GET,'content_search');$posts=get_posts(['post_type'=>$types,'post_status'=>'publish','posts_per_page'=>20,'s'=>$search,'orderby'=>'title','order'=>'ASC']);$items=[];
        foreach($posts as $post){$items[$post->ID]=get_the_title($post);}
        foreach($selected as $id){$post=get_post($id);if($post){$items[$id]=get_the_title($post);}}
        echo '<fieldset class="tbm-content-picker"><legend>'.esc_html__('Contents to exclude','tabulamea').'</legend>';
        foreach($items as $id=>$title){InterfaceTools::check($name.'[]',$title.' (#'.$id.')',in_array((int)$id,$selected,true),(string)$id);}echo '</fieldset>';
    }
    /** Render the private ranking report and management controls. @return void */
    public static function page(): void {
        if(!Plugin::ready()||!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        echo '<div class="wrap tbm">';Settings::header(__('Content rankings','tabulamea'));Settings::status();Navigation::links();
        $view=InterfaceTools::text($_GET,'view','rankings');if(isset($_GET['edit_ranking'])||isset($_GET['preview'])||isset($_GET['content_search'])){$view='edit';}
        echo '<p class="tbm-intro">'.esc_html__('Turn recorded pageviews into useful reading lists: popular content, growing interest, monthly favorites or a year in review. Start privately; publish a ranking only when you choose.','tabulamea').'</p><nav class="tbm-subnav" aria-label="'.esc_attr__('Ranking workspace','tabulamea').'">';
        foreach(['rankings'=>__('My rankings','tabulamea'),'create'=>__('Create a ranking','tabulamea'),'year-review'=>__('Year in review','tabulamea')] as $key=>$label){if($key==='create'&&!current_user_can('manage_options')){continue;}echo '<a href="'.esc_url(add_query_arg('view',$key,admin_url('admin.php?page=tabulamea-reports'))).'"'.($view===$key?' aria-current="page"':'').'>'.esc_html($label).'</a>';}echo '</nav>';
        if($view==='year-review'){self::recap();Admin::footer();echo '</div>';return;}
        $ranks=(array)get_option('tbm_rankings',[]);$preferences=(array)get_user_option('tbm_preferences');$favorites=(array)($preferences['favorites']??[]);uksort($ranks,static fn($a,$b)=>(int)in_array($b,$favorites,true)<=>(int)in_array($a,$favorites,true));$selected=sanitize_key(InterfaceTools::text($_GET,'ranking'));
        if(in_array($view,['create','edit'],true)&&current_user_can('manage_options')){self::manage($ranks);}
        else{
            if($selected&&isset($ranks[$selected])){self::report($ranks[$selected],$selected);}
            echo '<div class="tbm-ranking-catalog">';foreach($ranks as $id=>$rank){echo '<section class="tbm-card"><h2>'.esc_html($rank['title']).'</h2><p><span class="tbm-badge">'.esc_html(isset($rank['snapshot'])?__('Saved snapshot','tabulamea'):__('Live ranking','tabulamea')).'</span> <span class="tbm-badge">'.esc_html(!empty($rank['public'])?__('Published','tabulamea'):__('Private','tabulamea')).'</span></p><p>'.esc_html(self::modes()[$rank['mode']??'popular']).'</p><div class="tbm-inline-actions"><a class="button" href="'.esc_url(add_query_arg('ranking',$id,admin_url('admin.php?page=tabulamea-reports'))).'">'.esc_html__('View results','tabulamea').'</a>';if(current_user_can('manage_options')){echo '<a class="button" href="'.esc_url(add_query_arg('edit_ranking',$id,admin_url('admin.php?page=tabulamea-reports'))).'">'.esc_html__('Edit ranking','tabulamea').'</a>';}echo '</div>';RankingEnhancements::favorite($id,$favorites);echo '</section>';}echo '</div>';
            if(!$ranks){echo '<section class="tbm-card"><h2>'.esc_html__('Your first useful overview','tabulamea').'</h2><p>'.esc_html__('Create a ranking from a template, choose a period and inspect the results. No content is published automatically.','tabulamea').'</p>';if(current_user_can('manage_options')){echo '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=tabulamea-reports&view=create')).'">'.esc_html__('Create a ranking','tabulamea').'</a>';}echo '</section>';}
        }
        Admin::footer();echo '</div>';

    }
    /**
     * Render one ranking and explain exact time and snapshot semantics.
     * @param array $rank Definition.
     * @param string $id Saved ID or empty for a draft.
     * @return void
     */
    public static function report(array $rank,string $id=''): void {
        $range=$rank['snapshot']['range'] ?? Rankings::dates($rank);echo '<section class="tbm-card"><h2>'.esc_html($rank['title'] ?: __('Ranking preview','tabulamea')).'</h2>';
        if(!$range){echo '<p role="alert">'.esc_html__('Choose a valid date range; the end must not precede the start.','tabulamea').'</p></section>';return;}
        echo '<p>'.esc_html(implode(' – ',$range)).' · '.esc_html(isset($rank['snapshot'])?__('Saved snapshot','tabulamea'):__('Live ranking','tabulamea')).'</p>';
        if(isset($rank['snapshot'])){echo '<p>'.esc_html__('Snapshot saved:','tabulamea').' '.esc_html($rank['snapshot']['date']).' · '.esc_html__('Counts are frozen. Contents that are no longer public remain hidden.','tabulamea').'</p>';}
        RankingEnhancements::explain($rank);$rows=$rank['snapshot']['rows'] ?? Rankings::query($rank);echo Rankings::render($rank,$rows);
        if(!$rows){echo '<p class="tbm-attention">'.esc_html(self::empty_reason($rank,$range)).'</p>';}
        if(($rank['mode'] ?? '')==='evergreen'){echo '<p>'.esc_html__('This ranking uses complete calendar months. Every listed content must reach the minimum pageviews in every selected month.','tabulamea').'</p>';}
        if(!empty($rank['compare'])||in_array($rank['mode'] ?? '',['rising','falling','rediscovered'],true)){$previous=Rankings::previous($range);echo '<p>'.esc_html__('Comparison with the preceding equally long period:','tabulamea').' '.esc_html(implode(' – ',$previous)).'</p>';ReportTools::explain($previous);}
        ReportTools::explain($range);
        if($id){InterfaceTools::form('csv',['report'=>'ranking','id'=>$id]);submit_button(__('Download CSV','tabulamea'),'secondary','submit',false);echo '</form>';}
        echo '</section>';
    }
    /**
     * Distinguish absent history, absent public content and filters that do not match.
     * @param array $rank Definition.
     * @param array $range Selected interval.
     * @return string Actionable empty-state explanation.
     */
    private static function empty_reason(array $rank,array $range): string {
        global $wpdb;
        if(isset($rank['snapshot'])){return __('The snapshot has no currently eligible public contents. Content visibility or exclusions may have changed.','tabulamea');}
        $types=array_values(array_diff(get_post_types(['public'=>true]),['attachment']));
        $any=get_posts(['post_type'=>$types,'post_status'=>'publish','has_password'=>false,'posts_per_page'=>1,'fields'=>'ids']);
        if(!$any){return __('No public, unprotected contents are available. Publish a suitable post or page first.','tabulamea');}
        $records=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tbm_post_stats WHERE date BETWEEN %s AND %s",$range));
        if(!$records){return __('No content pageviews are retained for this period. Choose a period with existing statistics history.','tabulamea');}
        return ($rank['mode'] ?? 'popular')==='popular'?__('Pageviews exist, but the selected type, category, publication year or exclusions leave no matching contents. Relax the filters or change the period.','tabulamea'):__('No content meets this method’s minimum traffic and comparison rules. Try a longer period, a lower minimum or Most viewed.','tabulamea');
    }
    /**
     * Render starters, compact saved-definition controls, editor and global exclusions.
     * @param array $ranks Saved definitions.
     * @return void
     */
    private static function manage(array $ranks): void {
        echo '<details class="tbm-card"'.(isset($_GET['edit_ranking'])?'':' open').'><summary>'.esc_html__('Create a ranking from a template','tabulamea').'</summary>';InterfaceTools::form('starter');InterfaceTools::select('preset',__('Starting point','tabulamea'),array_map(static fn($rank)=>$rank['title'],self::starters()));echo '<p><label>'.esc_html__('Category for the category template','tabulamea').' ';wp_dropdown_categories(['name'=>'category','hide_empty'=>false,'show_option_all'=>__('All categories','tabulamea')]);echo '</label></p>';submit_button(__('Create unpublished ranking','tabulamea'),'secondary','submit',false);echo '</form></details>';
        $editing=sanitize_key(InterfaceTools::text($_GET,'edit_ranking'));$shown=$editing&&isset($ranks[$editing])?[$editing=>$ranks[$editing]]:[];
        foreach($shown as $id=>$rank){echo '<details class="tbm-card"><summary>'.esc_html($rank['title']).' · '.esc_html(isset($rank['snapshot'])?__('Saved snapshot','tabulamea'):__('Live ranking','tabulamea')).'</summary><p><a class="button" href="'.esc_url(add_query_arg('edit_ranking',$id,admin_url('admin.php?page=tabulamea-reports'))).'#tbm-ranking-editor">'.esc_html__('Edit ranking','tabulamea').'</a> <a class="button" href="'.esc_url(add_query_arg('ranking',$id,InterfaceTools::settings_url('shortcodes'))).'">'.esc_html__('Open shortcode generator','tabulamea').'</a></p>';
            InterfaceTools::copy('[tabulamea_ranking id="'.$id.'"]',__('Shortcode for this ranking','tabulamea'));
            echo '<p>'.esc_html(!empty($rank['public'])?(Plugin::settings()['public_rankings']?__('Public output enabled','tabulamea'):__('The ranking is published, but global public output is disabled.','tabulamea')):__('Private ranking: public shortcodes and blocks remain empty.','tabulamea')).'</p>';
            InterfaceTools::form('duplicate',['id'=>$id]);submit_button(__('Duplicate as a private live ranking','tabulamea'),'secondary','submit',false);echo '</form>';
            Admin::button(isset($rank['snapshot'])?'unfreeze':'freeze',isset($rank['snapshot'])?__('Return to live ranking','tabulamea'):__('Save snapshot','tabulamea'),$id);Admin::button('delete',__('Delete ranking','tabulamea'),$id);echo '</details>';
        }
        $edit=sanitize_key(InterfaceTools::text($_GET,'edit_ranking'));$current=(array)($ranks[$edit] ?? []);
        if(InterfaceTools::text($_GET,'preview')==='1'){$draft=get_transient('tbm_draft_'.get_current_user_id());if(is_array($draft)){$current=$draft['ranking'];$edit=$draft['id'];echo '<div id="tbm-draft-preview"><h2>'.esc_html__('Unsaved preview','tabulamea').'</h2>';self::report($current);echo '</div>';}}
        echo '<details class="tbm-card" '.'open'.'><summary>'.esc_html($edit?__('Edit ranking','tabulamea'):__('Create a custom ranking','tabulamea')).'</summary>';
        echo '<form class="tbm-ranking-editor" id="tbm-ranking-editor" action="'.esc_url(admin_url('admin-post.php')).'" method="post">';Admin::action_fields('ranking');echo '<input type="hidden" name="original_id" value="'.esc_attr($edit).'">';
        echo '<fieldset><legend><span class="tbm-step">1</span>'.esc_html__('Choose content and ranking method','tabulamea').'</legend><p class="description">'.esc_html__('Give the overview a recognizable title. Most viewed is the simplest starting point.','tabulamea').'</p>';
        InterfaceTools::input('ranking[title]',__('Ranking title','tabulamea'),$current['title'] ?? '','text',['required'=>true,'maxlength'=>160]);InterfaceTools::input('ranking[id]',__('Shortcode identifier (optional for a new ranking)','tabulamea'),$edit);
        echo '<p class="description">'.esc_html__('Use a short identifier without spaces. New rankings get an identifier from the title when this field is empty. Reusing an existing identifier updates that definition.','tabulamea').'</p>';
        $types=['all'=>__('All public content types','tabulamea')];foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){$types[$type->name]=$type->labels->name;}}
        RankingEnhancements::method_help();InterfaceTools::select('ranking[type]',__('Content type','tabulamea'),$types,$current['type'] ?? 'post');InterfaceTools::select('ranking[mode]',__('Ranking method','tabulamea'),self::modes(),$current['mode'] ?? 'popular');
        echo '<p class="description">'.esc_html__('Movers compare absolute pageview changes with the preceding equally long period. Older content must also be gaining views. Popular every month uses complete months instead of the period below.','tabulamea').'</p>';
        echo '</fieldset><fieldset><legend><span class="tbm-step">2</span>'.esc_html__('Set the period and filters','tabulamea').'</legend>';
        InterfaceTools::select('ranking[period]',__('Period','tabulamea'),ReportTools::periods(),$current['period'] ?? '30_days');
        foreach(['year'=>[__('Calendar year','tabulamea'),'number',wp_date('Y')],'start'=>[__('Start date for custom period','tabulamea'),'date',''],'end'=>[__('End date for custom period','tabulamea'),'date',''],'published_year'=>[__('Publication year (0 = any)','tabulamea'),'number',0],'limit'=>[__('Number of items (1–50)','tabulamea'),'number',10],'min_views'=>[__('Minimum pageviews (at least 5; per month for monthly rankings)','tabulamea'),'number',10],'months'=>[__('Complete months for monthly rankings (2–12)','tabulamea'),'number',3],'older_days'=>[__('Minimum content age in days for rediscovered content','tabulamea'),'number',365]] as $key=>[$label,$type,$default]){InterfaceTools::input('ranking['.$key.']',$label,$current[$key] ?? $default,$type);}
        echo '<p class="description">'.esc_html__('The period refers to pageview dates. Publication year separately filters when the content was published.','tabulamea').'</p>';
        echo '<p><label>'.esc_html__('Category','tabulamea').' ';wp_dropdown_categories(['name'=>'ranking[category]','selected'=>$current['category'] ?? 0,'hide_empty'=>false,'show_option_all'=>__('All categories','tabulamea')]);echo '</label></p>';
        echo '<input type="hidden" name="ranking[global_exclusions]" value="0">';InterfaceTools::check('ranking[global_exclusions]',__('Use the website-wide ranking exclusions','tabulamea'),!isset($current['global_exclusions'])||$current['global_exclusions']);
        echo '<details><summary>'.esc_html__('Exclude individual content','tabulamea').'</summary>';
        self::content_picker('ranking[exclude_ids]',wp_parse_id_list($current['exclude_ids'] ?? []));
        echo '</details></fieldset><fieldset><legend><span class="tbm-step">3</span>'.esc_html__('Choose appearance and publication','tabulamea').'</legend><p class="description">'.esc_html__('A private ranking is available in the admin. Frontend output needs this ranking’s publication switch and the website-wide public-output permission.','tabulamea').'</p>';
        InterfaceTools::check('ranking[caption]',__('Show period below this ranking','tabulamea'),!empty($current['caption']));InterfaceTools::select('ranking[style]',__('List appearance','tabulamea'),['numbered'=>__('Numbered list','tabulamea'),'links'=>__('Simple link list','tabulamea'),'images'=>__('List with featured images','tabulamea')],$current['style'] ?? 'numbered');
        foreach(['public'=>__('Publish this ranking','tabulamea'),'counts'=>__('Show pageview counts','tabulamea'),'images'=>__('Show featured images','tabulamea'),'compare'=>__('Show the previous period and absolute change','tabulamea'),'share'=>__('Show share of all site pageviews','tabulamea')] as $key=>$label){InterfaceTools::check('ranking['.$key.']',$label,!empty($current[$key]));}
        echo '</fieldset><p>'.esc_html__('Editing a frozen ranking preserves its snapshot until you return it to live mode.','tabulamea').'</p><button type="submit" name="tbm_preview" value="1" class="button">'.esc_html__('Preview without saving','tabulamea').'</button> ';submit_button(__('Save ranking','tabulamea'),'primary','submit',false,['id'=>'tbm-save-ranking']);echo '</form></details>';
        echo '<details class="tbm-card"><summary>'.esc_html__('Search content for exclusion lists','tabulamea').'</summary><form method="get"><input type="hidden" name="page" value="tabulamea-reports"><input type="hidden" name="edit_ranking" value="'.esc_attr($edit).'">';InterfaceTools::input('content_search',__('Content title search','tabulamea'),InterfaceTools::text($_GET,'content_search'));submit_button(__('Find content','tabulamea'),'secondary','submit',false);echo '</form><p>'.esc_html__('Search before changing selections. Results appear in both exclusion pickers; saved selections stay available. Save an edited ranking before starting another search.','tabulamea').'</p></details>';
        echo '<details class="tbm-card"><summary>'.esc_html__('Website-wide ranking exclusions','tabulamea').'</summary><form action="options.php" method="post">';settings_fields('tbm');echo '<input type="hidden" name="tbm_settings[_section]" value="rankings">';$s=Plugin::settings();InterfaceTools::check('tbm_settings[exclude_front]',__('Exclude the static front page','tabulamea'),$s['exclude_front']);self::content_picker('tbm_settings[exclude_ids]',$s['exclude_ids']);submit_button(__('Save ranking defaults','tabulamea'));echo '</form></details>';
    }
    /**
     * Create deterministic editorial copy from current aggregates or a year snapshot.
     * @param array $rank Year ranking.
     * @return string Plain text suitable for newsletters or a Shortcode-independent article.
     */
    public static function recap_text(array $rank): string {
        $range=$rank['snapshot']['range'] ?? Rankings::dates($rank);if(!$range){return '';}$rows=Rankings::visible($rank,$rank['snapshot']['rows'] ?? Rankings::query($rank));
        $lines=[sprintf(__('Most read contents in %s','tabulamea'),substr($range[0],0,4)),sprintf(__('Recorded pageviews from %1$s to %2$s.','tabulamea'),$range[0],$range[1]),isset($rank['snapshot'])?__('Source: saved snapshot.','tabulamea'):__('Source: current retained statistics.','tabulamea'),''];
        foreach($rows as $index=>$row){$lines[]=($index+1).'. '.wp_strip_all_tags(get_the_title($row['ID'])).' — '.number_format_i18n((int)$row['pageviews']).' '.__('pageviews','tabulamea');$lines[]=get_permalink($row['ID']);$lines[]='';}
        if(!$rows){$lines[]=__('No matching public content with recorded pageviews was found for these filters.','tabulamea');}$lines[]=__('This list describes recorded pageviews, not an editorial quality assessment. Missing history is not estimated.','tabulamea');return implode("\n",$lines);
    }
    /** Render year selection and copyable editorial output without publishing any content. @return void */
    private static function recap(): void {
        echo '<button class="button" type="button" data-tbm-print>'.esc_html__('Print this review','tabulamea').'</button>';
        $year=max(2000,min((int)wp_date('Y'),(int)InterfaceTools::text($_GET,'review_year',(string)((int)wp_date('Y')-1))));$choices=[''=>__('Create a top-10 post list for the selected year','tabulamea')];$ranks=(array)get_option('tbm_rankings',[]);foreach($ranks as $id=>$rank){if(in_array($rank['period'],['year','previous_year'],true)){$choices[$id]=$rank['title'];}}
        $id=sanitize_key(InterfaceTools::text($_GET,'recap_rank'));echo '<section class="tbm-card"><h2>'.esc_html__('Year in review','tabulamea').'</h2><p>'.esc_html__('Generate a factual draft with titles, pageview counts and links. It is never published automatically. A selected saved ranking keeps its filters, year and snapshot.','tabulamea').'</p><form method="get"><input type="hidden" name="page" value="tabulamea-reports"><input type="hidden" name="view" value="year-review">';InterfaceTools::input('review_year',__('Calendar year','tabulamea'),$year,'number');InterfaceTools::select('recap_rank',__('Optional saved year ranking','tabulamea'),$choices,isset($choices[$id])?$id:'');submit_button(__('Generate review text','tabulamea'),'secondary','submit',false);echo '</form>';
        $rank=$id&&isset($choices[$id])?$ranks[$id]:Rankings::clean(['title'=>'Year review','type'=>'post','period'=>'year','year'=>$year,'limit'=>10,'counts'=>1]);$text=self::recap_text($rank);InterfaceTools::copy($text,__('Text draft to copy and edit','tabulamea'),16);echo '<div class="tbm-print-review">'.nl2br(esc_html($text)).'</div>';ReportTools::explain($rank['snapshot']['range'] ?? Rankings::dates($rank));echo '</section>';
    }
}
