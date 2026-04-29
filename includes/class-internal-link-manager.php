<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_SEO_GEO_Internal_Link_Manager {
	private $allowed_link_types = array( 'product_hub', 'product_category', 'product_detail', 'spare_parts', 'solution', 'safety', 'article', 'contact', 'company', 'media', 'custom' );
	public function get_table_name() { global $wpdb; return $wpdb->prefix . 'ai_seo_internal_links'; }
	public function get_active_links() {
		global $wpdb; $t=$this->get_table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status=%s ORDER BY priority DESC, id DESC", 'active' ), ARRAY_A );
	}
	public function get_library_prompt_payload() {
		$rows=$this->get_active_links(); $out=array();
		foreach($rows as $r){ $out[]=array('anchor_text'=>(string)$r['anchor_text'],'target_url'=>(string)$r['target_url'],'link_type'=>(string)$r['link_type'],'notes'=>(string)$r['recommended_context']); }
		return $out;
	}
	public function save_link( $data ) {
		global $wpdb; $t=$this->get_table_name();
		$link_type = sanitize_text_field( $data['link_type'] ?? 'custom' );
		if ( ! in_array( $link_type, $this->allowed_link_types, true ) ) { $link_type = 'custom'; }
		$priority = intval( $data['priority'] ?? 50 );
		if ( ! in_array( $priority, array( 100, 50, 10 ), true ) ) { $priority = 50; }
		$status = sanitize_text_field( $data['status'] ?? 'active' );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) { $status = 'active'; }
		$url = esc_url_raw( $data['target_url'] ?? '' );
		$row=array(
			'anchor_text'=>sanitize_text_field($data['anchor_text']??''),
			'target_url'=>$url,
			'target_post_id'=>absint( url_to_postid( $url ) ),
			'link_type'=>$link_type,
			'priority'=>$priority,
			'status'=>$status,
			'related_keywords'=>sanitize_textarea_field($data['related_keywords']??''),
			'recommended_context'=>sanitize_textarea_field($data['recommended_context']??''),
			'notes'=>sanitize_textarea_field($data['notes']??''),
			'updated_at'=>current_time('mysql'),
		);
		$id=absint($data['id']??0);
		if($id>0){ return false!==$wpdb->update($t,$row,array('id'=>$id)); }
		$row['created_at']=current_time('mysql');
		return false!==$wpdb->insert($t,$row);
	}

	public function validate_suggestions( $suggestions, $post_id=0 ) {
		$library=$this->get_active_links(); $allow=array(); foreach($library as $r){$allow[(string)$r['target_url']]=$r;}
		$out=array(); $seen=array(); $max=5;
		foreach((array)$suggestions as $s){ if(count($out)>=$max){break;} $url=esc_url_raw($s['target_url']??''); $anchor=sanitize_text_field($s['anchor']??''); $valid=true; $errors=array();
			if(''===$url||''===$anchor){$valid=false;$errors[]='empty_anchor_or_url';}
			if(!isset($allow[$url])){$valid=false;$errors[]='url_not_in_library';}
			if(isset($seen[$url])){$valid=false;$errors[]='duplicate_url';}
			if($post_id>0 && get_permalink($post_id)===$url){$valid=false;$errors[]='self_link';}
			$seen[$url]=1;
			$out[]=array('anchor'=>$anchor,'target_url'=>$url,'target_post_id'=>absint($s['target_post_id']??0),'placement_suggestion'=>sanitize_text_field($s['placement_suggestion']??''),'reason'=>sanitize_textarea_field($s['reason']??''),'confidence'=>intval($s['confidence']??0),'link_type'=>sanitize_text_field($allow[$url]['link_type']??''),'valid'=>$valid,'errors'=>$errors);
		}
		return $out;
	}
}
