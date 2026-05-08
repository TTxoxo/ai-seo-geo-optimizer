<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_SEO_GEO_Internal_Link_Manager {
	private $allowed_link_types = array( 'product_hub', 'product_category', 'product_detail', 'spare_parts', 'solution', 'safety', 'article', 'contact', 'company', 'media', 'custom' );

	public function get_table_name() { global $wpdb; return $wpdb->prefix . 'ai_seo_internal_links'; }

	public function ensure_table_columns() {
		global $wpdb; $t=$this->get_table_name();
		$cols=$wpdb->get_col("SHOW COLUMNS FROM {$t}",0);
		if(!in_array('related_keywords',$cols,true)){ $wpdb->query("ALTER TABLE {$t} ADD related_keywords TEXT NULL"); }
		if(!in_array('recommended_context',$cols,true)){ $wpdb->query("ALTER TABLE {$t} ADD recommended_context TEXT NULL"); }
	}

	public function get_active_links() { global $wpdb; $t=$this->get_table_name(); return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status=%s ORDER BY priority DESC, id DESC", 'active' ), ARRAY_A ); }
	public function get_all_links() { global $wpdb; $t=$this->get_table_name(); return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC", ARRAY_A ); }
	public function get_link_by_id( $id ) { global $wpdb; $t=$this->get_table_name(); return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d LIMIT 1", absint($id) ), ARRAY_A ); }

	public function get_library_prompt_payload() { $rows=$this->get_active_links(); $out=array(); foreach($rows as $r){ $out[]=array('anchor_text'=>(string)$r['anchor_text'],'target_url'=>(string)$r['target_url'],'link_type'=>(string)$r['link_type'],'notes'=>(string)$r['recommended_context']); } return $out; }

	public function save_link( $data ) {
		global $wpdb; $t=$this->get_table_name(); $this->ensure_table_columns();
		$link_type=sanitize_text_field($data['link_type']??'custom'); if(!in_array($link_type,$this->allowed_link_types,true)) return array('success'=>false,'message'=>'invalid_link_type');
		$priority=intval($data['priority']??50); if(!in_array($priority,array(100,50,10),true)) return array('success'=>false,'message'=>'invalid_priority');
		$status=sanitize_text_field($data['status']??'active'); if(!in_array($status,array('active','inactive'),true)) return array('success'=>false,'message'=>'invalid_status');
		$url_raw=(string)($data['target_url']??'');
		$url = $this->normalize_target_url( $url_raw );
		$full = ( 0 === strpos( $url, 'http' ) ) ? $url : home_url( $url );
		$row=array('anchor_text'=>sanitize_text_field($data['anchor_text']??''),'target_url'=>esc_url_raw($url),'target_post_id'=>absint(url_to_postid($full)),'link_type'=>$link_type,'priority'=>$priority,'status'=>$status,'related_keywords'=>sanitize_textarea_field($data['related_keywords']??''),'recommended_context'=>sanitize_textarea_field($data['recommended_context']??''),'notes'=>sanitize_textarea_field($data['notes']??''),'updated_at'=>current_time('mysql'));
		if(''===trim($row['anchor_text'])||''===trim($row['target_url'])) return array('success'=>false,'message'=>'required_fields_missing');
		$id=absint($data['id']??0);
		if($id>0){
			$exists = $this->get_link_by_id( $id );
			if ( empty( $exists ) ) { return array( 'success' => false, 'message' => 'link_not_found' ); }
			$ok=false!==$wpdb->update($t,$row,array('id'=>$id)); return array('success'=>$ok,'id'=>$id);
		}
		$row['created_at']=current_time('mysql'); $ok=false!==$wpdb->insert($t,$row); return array('success'=>$ok,'id'=>(int)$wpdb->insert_id);
	}

	private function normalize_target_url( $url ) { $url=trim((string)$url); if(''===$url){return '';} if(0===strpos($url,'http')){return $url;} return '/' . ltrim($url,'/'); }

	public function validate_suggestions( $suggestions, $post_id=0 ) { $library=$this->get_active_links(); $allow=array(); foreach($library as $r){$allow[(string)$r['target_url']]=$r;} $out=array(); $seen=array(); foreach((array)$suggestions as $s){ if(count($out)>=5) break; $url=esc_url_raw($s['target_url']??''); $anchor=sanitize_text_field($s['anchor']??''); $valid=true; $errors=array(); if(''===$url||''===$anchor){$valid=false;$errors[]='empty_anchor_or_url';} if(!isset($allow[$url])){$valid=false;$errors[]='url_not_in_library';} if(isset($seen[$url])){$valid=false;$errors[]='duplicate_url';} if($post_id>0 && get_permalink($post_id)===$url){$valid=false;$errors[]='self_link';} $seen[$url]=1; $out[]=array('anchor'=>$anchor,'target_url'=>$url,'target_post_id'=>absint($s['target_post_id']??0),'placement_suggestion'=>sanitize_text_field($s['placement_suggestion']??''),'reason'=>sanitize_textarea_field($s['reason']??''),'confidence'=>intval($s['confidence']??0),'link_type'=>sanitize_text_field($allow[$url]['link_type']??''),'valid'=>$valid,'errors'=>$errors); } return $out; }
}
