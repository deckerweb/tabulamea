<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Administrator-only privacy explanations and explicitly previewed, bounded data maintenance. */
final class DataCare {
    /** Register protected data-care commands. @return void */
    public static function boot(): void {add_action('admin_post_tbm_data_care',[self::class,'action']);}
    /** Return cached approximate storage and retained date boundaries. @return array Coarse storage facts. */
    public static function summary(): array {
        $cached=get_transient('tbm_storage_summary');if(is_array($cached)){return $cached;}global $wpdb;$bytes=0;foreach(['site_stats','post_stats','referrer_stats','dimensions','import_ledger','receipts','rate','search_stats'] as $suffix){$row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$wpdb->prefix.'tbm_'.$suffix));$bytes+=(int)($row->Data_length??0)+(int)($row->Index_length??0);}$range=(array)$wpdb->get_row("SELECT MIN(date) first,MAX(date) last FROM {$wpdb->prefix}tbm_site_stats",ARRAY_A);$data=['bytes'=>$bytes,'first'=>$range['first']??'','last'=>$range['last']??''];set_transient('tbm_storage_summary',$data,HOUR_IN_SECONDS);return $data;
    }
    /** Generate a factual configuration-dependent privacy text, not a legal guarantee. @return string Text to review. */
    public static function privacy_text(): string {
        $s=Plugin::settings();$lines=[__('This website uses TabulaMea to count pageviews without cookies, persistent browser storage, visitor profiles or unique-person counts.','tabulamea'),__('Recorded data consists of local daily totals, content identifiers, query-free page paths and external referring domains. Do Not Track and Global Privacy Control are respected.','tabulamea'),__('A random per-document delivery token is held only in memory. Its unlinked hash is retained temporarily on the server to prevent duplicate retries; it expires after ten minutes and is removed during cleanup. No address or browser identity is stored with it.','tabulamea')];
        if(!$s['measurement']){$lines[]=__('Collection is currently paused.','tabulamea');}
        if($s['technology']){$lines[]=__('Coarse device, browser and operating-system categories are retained as separate daily totals, without raw browser headers.','tabulamea');}
        if($s['country']){$lines[]=__('Country categories are derived locally from the connection address without storing the address. Categories are aggregated independently.','tabulamea');}
        if($s['search_enabled']){$lines[]=__('Search-result views and views without results are counted separately. Free search strings are not retained; only explicitly approved non-personal topics can receive aggregate counts.','tabulamea');}
        $lines[]=$s['core_retention']?sprintf(
/* translators: %d: configured core-statistics retention in months. */
__('Core statistics are retained for %d months.','tabulamea'),$s['core_retention']):__('Core statistics have no automatic age limit.','tabulamea');$lines[]=sprintf(
/* translators: %d: configured optional-statistics retention in months. */
__('Optional category statistics are retained for %d months.','tabulamea'),$s['retention']);if($s['geo_download']){$lines[]=__('The server is permitted to download the country database from DB-IP; visitor addresses are not sent to that service.','tabulamea');}$lines[]=__('Automatic deletion runs in small scheduled batches. Delayed WordPress scheduled tasks can retain older data for longer.','tabulamea');$lines[]=__('Hosting access logs and other installed software are outside this description. Review this text for your actual website and legal requirements before publishing it.','tabulamea');return implode("\n\n",$lines);
    }
    /** Show privacy facts and a copyable draft only to site administrators. @return void */
    public static function privacy(): void {
        if(!current_user_can('manage_options')){return;}
        echo '<details class="tbm-card"><summary>'.esc_html__('Text suggestion for your privacy policy','tabulamea').'</summary><p>'.esc_html__('This text describes the current TabulaMea configuration. Review and adapt it before adding it to your WordPress privacy policy page. Nothing is changed automatically.','tabulamea').'</p>';
        $id=(int)get_option('wp_page_for_privacy_policy');$page=$id?get_post($id):null;
        if($page){echo '<p class="tbm-privacy-links">';if(current_user_can('edit_post',$id)){echo '<a target="_blank" rel="noopener noreferrer" href="'.esc_url(get_edit_post_link($id,'raw')).'">'.esc_html__('Edit privacy policy page','tabulamea').' · '.esc_html__('opens in a new tab','tabulamea').'</a> ';}if($page->post_status==='publish'){echo '<a target="_blank" rel="noopener noreferrer" href="'.esc_url(get_permalink($id)).'">'.esc_html__('View privacy policy page','tabulamea').' · '.esc_html__('opens in a new tab','tabulamea').'</a>';}echo '</p>';}else{echo '<p>'.esc_html__('No WordPress privacy policy page is assigned.','tabulamea').' <a href="'.esc_url(admin_url('options-privacy.php')).'">'.esc_html__('Settings → Privacy','tabulamea').'</a></p>';}
        echo '<p class="tbm-attention">'.esc_html__('Non-binding text suggestion without warranty of accuracy, completeness or currency. Errors excepted. Not legal advice. Review and adapt it to your website before publication. It describes TabulaMea only and does not replace a complete privacy policy.','tabulamea').'</p>';
        InterfaceTools::copy(self::privacy_text(),__('Configuration-dependent privacy text','tabulamea'),14,__('Copy text suggestion','tabulamea'));echo '</details>';
    }
    /** Count affected rows in only plugin-owned dated aggregates.
     * @param string $cutoff Exclusive cutoff date.
     * @return array Counts by fixed table suffix.
     */
    public static function affected(string $cutoff): array {global $wpdb;$counts=[];foreach(['site_stats','post_stats','referrer_stats','dimensions','search_stats'] as $suffix){$counts[$suffix]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tbm_{$suffix} WHERE date<%s",$cutoff));}return $counts;}
    /** Render a non-destructive size overview and a two-stage cleanup workflow. @return void */
    public static function panel(): void {
        if(!current_user_can('manage_options')){return;}$summary=self::summary();echo '<section class="tbm-card"><h2>'.esc_html__('Data care','tabulamea').'</h2><p>'.esc_html(sprintf(
/* translators: %1$s: formatted storage size; %2$s: first retained date; %3$s: last retained date. */
__('Approximate storage: %1$s · Retained core history: %2$s to %3$s','tabulamea'),size_format($summary['bytes']),$summary['first']?:'—',$summary['last']?:'—')).'</p><p>'.esc_html__('Storage is an approximate database estimate, refreshed hourly. Cleanup removes dated TabulaMea aggregates only. Definitions, snapshots and import receipts remain; other plugins are never changed.','tabulamea').'</p>';
        $stage=get_transient('tbm_cleanup_'.get_current_user_id());if(is_array($stage)){echo '<h3>'.esc_html__('Review deletion','tabulamea').'</h3><p>'.esc_html(sprintf(
/* translators: %s: exclusive deletion cutoff date. */
__('Delete aggregate records before %s. This cannot be undone without your backup.','tabulamea'),$stage['cutoff'])).'</p><ul>';$labels=['site_stats'=>__('Daily pageviews','tabulamea'),'post_stats'=>__('Public content totals','tabulamea'),'referrer_stats'=>__('Referrer domains','tabulamea'),'dimensions'=>__('Additional statistics','tabulamea'),'search_stats'=>__('Internal search','tabulamea')];foreach(self::affected($stage['cutoff']) as $key=>$count){echo '<li>'.esc_html($labels[$key]).': '.esc_html(number_format_i18n($count)).'</li>';}echo '</ul>';self::form('apply');echo '<p><label><input type="checkbox" name="confirm" value="1" required> '.esc_html__('I reviewed the date and have a backup. Delete the next batch.','tabulamea').'</label></p>';submit_button(__('Delete next batch','tabulamea'),'secondary','submit',false);echo '</form><p class="description">'.esc_html__('Each action removes at most 1,000 rows per aggregate table. Continue until no rows remain; reports may be incomplete while cleanup is in progress.','tabulamea').'</p>';}
        self::form('preview');InterfaceTools::input('cutoff',__('Delete records before this date','tabulamea'),wp_date('Y-m-d'),'date');submit_button(__('Preview affected data','tabulamea'),'secondary','submit',false);echo '</form></section>';
    }
    /** Open a protected form with one fixed operation.
     * @param string $operation Preview or apply.
     * @return void
     */
    private static function form(string $operation): void {echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tbm_data_care"><input type="hidden" name="operation" value="'.esc_attr($operation).'">';wp_nonce_field('tbm_data_care_'.$operation);}
    /** Preview then delete bounded historical batches after explicit confirmation. @return void */
    public static function action(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}$op=InterfaceTools::text($_POST,'operation');check_admin_referer('tbm_data_care_'.$op);$key='tbm_cleanup_'.get_current_user_id();
        if($op==='preview'){$cutoff=InterfaceTools::text($_POST,'cutoff');$range=Rankings::dates(['period'=>'custom','start'=>'2000-01-01','end'=>$cutoff]);if(!$range||$cutoff>wp_date('Y-m-d')){wp_die(esc_html__('Choose a valid historical cutoff.','tabulamea'),'',400);}try{InterfaceTools::temporary($key,['cutoff'=>$cutoff],15*MINUTE_IN_SECONDS);}catch(\Throwable $error){wp_die(esc_html__('The action could not be completed. Check the input or create a new preview if it expired or the target settings changed.','tabulamea'),'',503);}}
        elseif($op==='apply'&&!empty($_POST['confirm'])){$stage=get_transient($key);if(!is_array($stage)){wp_die(esc_html__('Create a fresh preview first.','tabulamea'),'',409);}global $wpdb;foreach(array_keys(self::affected($stage['cutoff'])) as $suffix){if($wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}tbm_{$suffix} WHERE date<%s LIMIT 1000",$stage['cutoff']))===false){wp_die(esc_html__('Cleanup stopped after a storage error. Review the remaining data.','tabulamea'),'',503);}}update_option('tbm_revision',microtime(true),false);delete_transient('tbm_storage_summary');if(array_sum(self::affected($stage['cutoff']))===0){delete_transient($key);}}
        else{wp_die(esc_html__('Unknown action.','tabulamea'),'',400);}wp_safe_redirect(InterfaceTools::settings_url('data'));exit;
    }
}
