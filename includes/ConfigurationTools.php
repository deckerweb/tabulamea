<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Selective configuration transfer with user-bound previews and per-site conflict checks. */
final class ConfigurationTools {
    /** Return independently saved settings sections. @return array Section keys and configuration keys. */
    public static function sections(): array {
        return ['measurement'=>['exclude_search_bots','exclude_ai_bots','measure_scope','measure_types','measure_archives','search_enabled','search_topics','measurement','exclude_roles','core_retention','referrer_blocklist','request_limit'],'access'=>['view_roles','manage_roles','widget_roles','users','public_rankings','public_counts'],'dashboard'=>['original_widget','widget','widget_period','widget_limit','widget_type','widget_ranking','widget_pageviews','widget_visitors','widget_personal','widget_max','overview_period','overview_items','overview_modules','overview_allowed','chart_style'],'collection'=>['technology','country','geo_download','retention'],'content'=>['column_types','column_period'],'rankings'=>['exclude_ids','exclude_front'],'data'=>['delete_data']];
    }
    /** Return translated section labels. @return array Labels. */
    public static function labels(): array { return ['measurement'=>__('Measurement','tabulamea'),'access'=>__('Access','tabulamea'),'dashboard'=>__('Dashboard','tabulamea'),'collection'=>__('Additional collection','tabulamea'),'content'=>__('Content lists','tabulamea'),'rankings'=>__('Ranking defaults','tabulamea'),'data'=>__('Uninstall preference','tabulamea')]; }
    /**
     * Return portable keys, excluding personal permissions and external consent.
     * @param array $sections Selected sections.
     * @return array Allowed configuration keys.
     */
    public static function portable_keys(array $sections): array {
        $keys=[];foreach(array_intersect($sections,array_keys(self::sections())) as $section){$keys=array_merge($keys,self::sections()[$section]);}
        return array_values(array_diff($keys,['users','geo_download','delete_data','exclude_ids','search_topics']));
    }
    /**
     * Create a bounded portable package without snapshots or site-specific content IDs.
     * @param array $sections Selected sections.
     * @param array $ids Selected ranking IDs.
     * @return array Versioned portable configuration.
     */
    public static function package(array $sections,array $ids): array {
        $settings=array_intersect_key(Plugin::settings(),array_flip(self::portable_keys($sections)));$ranks=[];
        foreach((array)get_option('tbm_rankings',[]) as $id=>$rank){if(!in_array((string)$id,$ids,true)){continue;}$rank=Rankings::clean($rank);$rank['exclude_ids']=[];$rank['category']=0;$rank['public']=false;$ranks[$id]=$rank;}
        foreach(['technology','country','public_rankings','public_counts','search_enabled'] as $key){if(isset($settings[$key])){$settings[$key]=false;}}
        // A local ranking reference is portable only when its definition is in the same package.
        if(!empty($settings['widget_ranking'])&&!isset($ranks[$settings['widget_ranking']])){$settings['widget_ranking']='';}
        return ['format'=>2,'settings'=>$settings,'rankings'=>$ranks];
    }
    /**
     * Validate a supplied package and strip fields that cannot be imported safely.
     * @param array $data Untrusted decoded configuration.
     * @param array $sections Selected settings sections.
     * @param bool $rankings Whether to import definitions.
     * @return array Validated settings patch and definitions.
     */
    public static function validate(array $data,array $sections,bool $rankings): array {
        if(!in_array($data['format'] ?? 0,[1,2],true)||!is_array($data['settings'] ?? null)||!is_array($data['rankings'] ?? null)||count($data['rankings'])>100){throw new \InvalidArgumentException('configuration');}
        $input=array_intersect_key($data['settings'],array_flip(self::portable_keys($sections)));$candidate=Admin::sanitize(array_replace(Plugin::settings(),$input));$patch=array_intersect_key($candidate,$input);
        foreach(['technology','country','public_rankings','public_counts','search_enabled'] as $key){if(array_key_exists($key,$patch)){$patch[$key]=false;}}
        $ranks=[];if($rankings){foreach($data['rankings'] as $id=>$rank){if(!is_string($id)||sanitize_key($id)!==$id||$id===''||!is_array($rank)){continue;}$clean=Rankings::clean($rank);$clean['public']=false;$clean['exclude_ids']=[];$clean['category']=0;if($clean['title'] && Rankings::dates($clean)){$ranks[$id]=$clean;}}}
        return ['settings'=>$patch,'rankings'=>$ranks];
    }
    /** Check plugin availability on the currently selected website. @return bool Whether the host is active. */
    public static function active(): bool {
        if(!function_exists('is_plugin_active')){require_once ABSPATH.'wp-admin/includes/plugin.php';}
        return is_plugin_active(plugin_basename(TBM_FILE))||is_plugin_active_for_network(plugin_basename(TBM_FILE));
    }
    /** Return a content-sensitive revision for conflict detection. @return string SHA-256 revision. */
    public static function revision(): string { return hash('sha256',serialize([get_option('tbm_settings',[]),get_option('tbm_rankings',[])])); }
    /**
     * Stage exact per-site changes for the current administrator; no changes are applied here.
     * @param array $patch Validated settings patch.
     * @param array $definitions Validated ranking definitions.
     * @param array $sites Explicit target blog IDs.
     * @param bool $preserve Preserve target values differing from defaults.
     * @return void
     */
    public static function stage(array $patch,array $definitions,array $sites,bool $preserve=false): void {
        $origin=get_current_blog_id();$items=[];$defaults=Plugin::defaults();
        foreach(array_slice(array_unique(array_map('absint',$sites)),0,50) as $site){
            if(!$site || (is_multisite()&&!get_site($site))){continue;}
            if($site!==$origin && (!is_multisite()||!current_user_can('manage_network_options'))){continue;}
            if(is_multisite()){switch_to_blog($site);}
            try {
            if(!current_user_can('manage_options')){continue;}
            $available=self::active();
            $current=Plugin::settings();$candidate=Admin::sanitize(array_replace($current,$patch));$targetPatch=array_intersect_key($candidate,$patch);$apply=[];$skipped=[];
            foreach($targetPatch as $key=>$value){if(!$available){$skipped[]=$key;continue;}if($site!==$origin && $key==='widget_type' && !in_array($value,['all','post','page'],true)){$skipped[]=$key;continue;}if(!array_key_exists($key,$defaults)){continue;}if($preserve && ($current[$key] ?? null)!==$defaults[$key] && ($current[$key] ?? null)!==$value){$skipped[]=$key;continue;}if(($current[$key] ?? null)!==$value){$apply[$key]=['before'=>$current[$key] ?? null,'after'=>$value];}}
            $existing=(array)get_option('tbm_rankings',[]);$add=[];$conflicts=[];
            foreach($definitions as $id=>$rank){if(!$available){$conflicts[]=$id;continue;}if($site!==$origin&&!in_array($rank['type'],['all','post','page'],true)){$conflicts[]=$id;continue;}$rank=Rankings::clean($rank);if(isset($existing[$id])){$conflicts[]=$id;}elseif(count($existing)+count($add)<100){$add[$id]=$rank;}else{$conflicts[]=$id;}}
            if(isset($apply['widget_ranking']) && $apply['widget_ranking']['after']!=='' && in_array($apply['widget_ranking']['after'],$conflicts,true)){$skipped[]='widget_ranking';unset($apply['widget_ranking']);}
            $items[]=['site'=>$site,'name'=>get_bloginfo('name'),'base'=>self::revision(),'available'=>$available,'changes'=>$apply,'rankings'=>$add,'conflicts'=>$conflicts,'skipped'=>$skipped];
            } finally {if(is_multisite()){restore_current_blog();}}
        }
        InterfaceTools::temporary('tbm_stage_'.get_current_user_id(),['origin'=>$origin,'items'=>$items],15*MINUTE_IN_SECONDS);
    }
    /** Render a staged diff outside all other forms. @return void */
    public static function preview(): void {
        $tab=InterfaceTools::text($_GET,'tab','overview');
        if(!in_array($tab,['access','data'],true)){if(get_transient('tbm_stage_'.get_current_user_id())){echo '<p><a href="'.esc_url(InterfaceTools::settings_url('data')).'">'.esc_html__('A configuration preview is waiting for review.','tabulamea').'</a></p>';}return;}
        $results=$tab==='data'?get_transient('tbm_apply_result_'.get_current_user_id()):false;if(is_array($results)){echo '<details class="tbm-card"><summary>'.esc_html__('Last application result','tabulamea').'</summary><ul>';foreach($results as $result){echo '<li>#'.(int)$result['site'].': '.esc_html(!empty($result['skipped'])?__('Skipped: plugin inactive','tabulamea'):($result['success']?__('Applied or already unchanged','tabulamea'):__('Could not save; review this website and create a new preview','tabulamea'))).'</li>';}echo '</ul></details>';}
        $stage=get_transient('tbm_stage_'.get_current_user_id());if(!is_array($stage)||($stage['origin'] ?? 0)!==get_current_blog_id()){return;}
        echo '<section class="tbm-card tbm-preview"><h2>'.esc_html__('Review configuration changes','tabulamea').'</h2><p>'.esc_html__('Nothing has been applied yet. This preview expires after 15 minutes. If a target configuration changes, create a new preview.','tabulamea').'</p>';
        if(empty($stage['items'])){echo '<p>'.esc_html__('No permitted target websites were selected.','tabulamea').'</p>';}
        foreach($stage['items'] as $item){if(empty($item['available'])){echo '<p class="tbm-attention">'.esc_html__('The plugin is inactive on this target website. Its configuration will not be changed.','tabulamea').'</p>';}echo '<h3>'.esc_html($item['name']).' (#'.(int)$item['site'].')</h3><table class="widefat"><thead><tr><th>'.esc_html__('Setting','tabulamea').'</th><th>'.esc_html__('Current value','tabulamea').'</th><th>'.esc_html__('New value','tabulamea').'</th></tr></thead><tbody>';
            foreach($item['changes'] as $key=>$change){echo '<tr><td>'.esc_html(self::setting_label($key)).'</td><td>'.esc_html(self::display($change['before'])).'</td><td>'.esc_html(self::display($change['after'])).'</td></tr>';}
            echo '</tbody></table><p>'.esc_html__('New ranking definitions:','tabulamea').' '.esc_html(implode(', ',array_keys($item['rankings']))?:'—').'</p><p>'.esc_html__('Skipped ranking IDs (existing, unsupported on target or capacity reached):','tabulamea').' '.esc_html(implode(', ',$item['conflicts'])?:'—').'</p><p>'.esc_html__('Individual settings preserved:','tabulamea').' '.esc_html(implode(', ',array_map([self::class,'setting_label'],$item['skipped']))?:'—').'</p>';
        }
        InterfaceTools::form('apply_preview');submit_button(__('Apply the reviewed changes','tabulamea'),'primary','submit',false);echo '</form>';InterfaceTools::form('discard_preview');submit_button(__('Discard preview','tabulamea'),'secondary','submit',false);echo '</form></section>';
    }
    /**
     * Render readable values without HTML or hidden side effects.
     * @param mixed $value Configuration value.
     * @return string Human-readable value.
     */
    public static function display($value): string {
        if(is_bool($value)){return $value?__('Enabled','tabulamea'):__('Disabled','tabulamea');}
        if(is_array($value)){if($value && is_array(reset($value))){return sprintf(__('Personal rules: %d','tabulamea'),count($value));}$labels=[];foreach($value as $item){$labels[]=is_scalar($item)&&isset(wp_roles()->roles[(string)$item])?translate_user_role(wp_roles()->roles[(string)$item]['name']):(string)$item;}return implode(', ',$labels)?:'—';}
        return (string)$value;
    }
    /**
     * Translate configuration keys shown in previews.
     * @param string $key Known setting key.
     * @return string Readable label.
     */
    public static function setting_label(string $key): string {
        $labels=['view_roles'=>__('Roles allowed to view reports','tabulamea'),'manage_roles'=>__('Management roles','tabulamea'),'widget_roles'=>__('Roles allowed to view the compact widget','tabulamea'),'users'=>__('Personal access rules','tabulamea'),'public_rankings'=>__('Public ranking output','tabulamea'),'widget'=>__('Compact widget','tabulamea'),'original_widget'=>__('Statistics overview widget','tabulamea'),'hide_upsell'=>__('Hide upgrade advertising','tabulamea'),'technology'=>__('Technology collection','tabulamea'),'country'=>__('Country collection','tabulamea'),'geo_download'=>__('Country database download consent','tabulamea'),'retention'=>__('Dimension retention in months','tabulamea'),'exclude_ids'=>__('Excluded content','tabulamea'),'exclude_front'=>__('Exclude the static front page','tabulamea'),'delete_data'=>__('Delete plugin data on uninstall','tabulamea'),'widget_period'=>__('Default widget period','tabulamea'),'widget_limit'=>__('Widget item limit','tabulamea'),'widget_type'=>__('Widget content type','tabulamea'),'widget_ranking'=>__('Saved ranking in the compact widget','tabulamea'),'widget_pageviews'=>__('Show pageview total','tabulamea'),'widget_visitors'=>__('Visitor totals are unavailable','tabulamea')];return $labels[$key] ?? $key;
    }
    /** Apply only reviewed changes; every target is rechecked and its blog context restored. @return void */
    public static function apply(): void {
        $stage=get_transient('tbm_stage_'.get_current_user_id());
        if(!is_array($stage)||($stage['origin'] ?? 0)!==get_current_blog_id()){throw new \RuntimeException('expired');}
        $origin=get_current_blog_id();
        foreach($stage['items'] as $item){
            if($item['site']!==$origin&&(!is_multisite()||!current_user_can('manage_network_options'))){throw new \RuntimeException('permission');}
            if(is_multisite()){switch_to_blog($item['site']);}
            try{$valid=current_user_can('manage_options')&&hash_equals($item['base'],self::revision())&&self::active()===$item['available'];}
            finally{if(is_multisite()){restore_current_blog();}}
            if(!$valid){throw new \RuntimeException('stale');}
        }
        $results=[];$failed=false;
        foreach($stage['items'] as $item){
            if(is_multisite()){switch_to_blog($item['site']);}
            $success=true;
            try{
                if(!current_user_can('manage_options')||!hash_equals($item['base'],self::revision())||self::active()!==$item['available']){throw new \RuntimeException('stale');}
                $settings=Plugin::settings();foreach($item['changes'] as $key=>$change){$settings[$key]=$change['after'];}
                if($item['changes']){$success=update_option('tbm_settings',$settings,false);}
                $ranks=(array)get_option('tbm_rankings',[]);foreach($item['rankings'] as $id=>$rank){$ranks[$id]=$rank;}
                if($item['rankings']){$success=update_option('tbm_rankings',$ranks,false)&&$success;}
                if($success&&($item['changes']||$item['rankings'])){update_option('tbm_revision',microtime(true),false);}
            }catch(\Throwable $error){$success=false;}
            finally{if(is_multisite()){restore_current_blog();}}
            $results[]=['site'=>$item['site'],'success'=>$success,'skipped'=>!$item['available']];$failed=$failed||!$success;
        }
        InterfaceTools::temporary('tbm_apply_result_'.get_current_user_id(),$results,15*MINUTE_IN_SECONDS);
        if($failed){throw new \RuntimeException('write');}
        delete_transient('tbm_stage_'.get_current_user_id());
    }
    /** Render selected settings section checkboxes. @return void */
    public static function section_fields(): void {
        echo '<fieldset><legend>'.esc_html__('Settings sections to include','tabulamea').'</legend>';foreach(self::labels() as $key=>$label){if($key==='data'){continue;}InterfaceTools::check('sections[]',$label,$key!=='collection',$key);echo '<input type="hidden" name="section_marker" value="1">';}echo '</fieldset>';
    }
    /** Render explicit ranking selection checkboxes. @return void */
    public static function ranking_fields(): void {
        echo '<fieldset><legend>'.esc_html__('Ranking definitions to include','tabulamea').'</legend>';foreach((array)get_option('tbm_rankings',[]) as $id=>$rank){InterfaceTools::check('rank_ids[]',$rank['title'].' ('.$id.')',false,(string)$id);}
        echo '</fieldset><p class="description">'.esc_html__('Personal rules, snapshots, content exclusions by ID, category IDs, collected statistics and download consent are excluded. Imported collection and public output stay disabled for review.','tabulamea').'</p>';
    }
    /** Render transfer, named templates, selected-site deployment and scoped reset. @return void */
    public static function render(): void {
        echo '<section class="tbm-card"><h2>'.esc_html__('Selective configuration export','tabulamea').'</h2>';InterfaceTools::form('export_selected');self::section_fields();self::ranking_fields();submit_button(__('Download configuration','tabulamea'),'secondary','submit',false);echo '</form></section>';
        echo '<section class="tbm-card"><h2>'.esc_html__('Import with preview','tabulamea').'</h2><form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="tbm_tools"><input type="hidden" name="command" value="import_preview">';wp_nonce_field('tbm_tools_import_preview');self::section_fields();InterfaceTools::check('include_rankings',__('Import ranking definitions from the file','tabulamea'),true);echo '<p><label>'.esc_html__('Configuration JSON file','tabulamea').' <input type="file" name="configuration" accept=".json,application/json" required></label></p>';submit_button(__('Preview import','tabulamea'),'secondary','submit',false);echo '</form></section>';
        $templates=(array)get_option('tbm_templates',[]);echo '<section class="tbm-card"><h2>'.esc_html__('Named configuration templates','tabulamea').'</h2><p>'.esc_html__('Save a portable selection for reuse. For example: Customer website, Editorial project or Own website. Templates do not grant collection or download consent.','tabulamea').'</p>';
        InterfaceTools::form('save_template');InterfaceTools::input('name',__('Template name','tabulamea'),'','text',['required'=>true,'maxlength'=>100]);self::section_fields();self::ranking_fields();submit_button(__('Save new template','tabulamea'),'secondary','submit',false);echo '</form>';
        foreach($templates as $id=>$template){echo '<details><summary>'.esc_html($template['name']).'</summary>';InterfaceTools::form('template_preview',['template'=>$id]);self::section_fields();InterfaceTools::check('include_rankings',__('Include saved ranking definitions','tabulamea'),true);submit_button(__('Preview template','tabulamea'),'secondary','submit',false);echo '</form>';InterfaceTools::form('delete_template',['template'=>$id]);submit_button(__('Delete template','tabulamea'),'secondary','submit',false);echo '</form></details>';}
        echo '</section>';
        if(is_multisite()&&current_user_can('manage_network_options')){
            echo '<section class="tbm-card"><h2>'.esc_html__('Apply a template to selected websites','tabulamea').'</h2><p>'.esc_html__('Only checked websites are considered. Individual settings are preserved by default. The preview lists changes and skipped conflicts for every website.','tabulamea').'</p><form method="get"><input type="hidden" name="page" value="tabulamea"><input type="hidden" name="tab" value="data">';InterfaceTools::input('network_search',__('Search website domain or path','tabulamea'),InterfaceTools::text($_GET,'network_search'));submit_button(__('Find websites','tabulamea'),'secondary','submit',false);echo '</form>';InterfaceTools::form('network_preview');$choices=[];foreach($templates as $id=>$template){$choices[$id]=$template['name'];}InterfaceTools::select('template',__('Template','tabulamea'),$choices);self::section_fields();InterfaceTools::check('include_rankings',__('Include saved ranking definitions','tabulamea'),true);
            foreach(get_sites(['number'=>100,'search'=>InterfaceTools::text($_GET,'network_search'),'public'=>null,'deleted'=>0,'spam'=>0,'archived'=>0]) as $site){$details=get_blog_details($site->blog_id);InterfaceTools::check('sites[]',$details->blogname.' (#'.$site->blog_id.')',false,(string)$site->blog_id);}
            echo '<p class="description">'.esc_html__('Up to 100 websites are listed; a single preview supports at most 50 selected websites.','tabulamea').'</p>';InterfaceTools::check('preserve_custom',__('Preserve settings that differ from installation defaults','tabulamea'),true);submit_button(__('Preview selected websites','tabulamea'),'secondary','submit',false);echo '</form></section>';
        }
        echo '<section class="tbm-card"><h2>'.esc_html__('Reset one settings section','tabulamea').'</h2><p>'.esc_html__('Only the chosen section is reset after preview. Rankings, snapshots, collected statistics and the retention period are preserved. Resetting Access also clears personal access rules.','tabulamea').'</p>';InterfaceTools::form('reset_preview');InterfaceTools::select('section',__('Settings section','tabulamea'),self::labels());submit_button(__('Preview reset','tabulamea'),'secondary','submit',false);echo '</form></section>';
    }
}
