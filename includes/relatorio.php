<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class ReportTable extends WP_List_Table {
    function __construct() {
        parent::__construct( array(
            'singular'  => __('objeto', 'consulta'),     //singular name of the listed records
            'plural'    => __('objetos', 'consulta'),    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
        
    }
    
    function column_default($item, $column_name) {
        return $item->$column_name;
    }
    
    function column_post_title($item) {
        $permalink = get_post_permalink($item->ID);
        return "<a href='{$permalink}'>{$item->post_title}</a>";
    }

    function column_user_created($item) {
        return ($item->meta_value == true) ? 'Sim' : 'Não';
    }
    
    function column_type($item) {
        $termsString = '';
        $terms = wp_get_post_terms($item->ID, 'object_type');
        
        foreach ($terms as $term) {
            $permalink = get_term_link($term);
            $termsString .= "<a href='{$permalink}'>{$term->name}</a><br />";
        }
        
        return $termsString;
    }
    
    function get_columns() {
        $columns = array(
            'post_title' => __('Título', 'consulta'),
            'type' => __('Tipo de objeto', 'consulta'),
            'comments_count'    => __('Número de comentários'),
            'evaluation_count'  => __('Número de votos'),
            'user_created' => __('Criado por usuário?', 'consulta'),
        );
        
        return $columns;
    }
    
    function get_sortable_columns() {
        $sortable_columns = array(
            'post_title' => array('post_title', true), //true means its already sorted
            'comments_count' => array('comments_count', true),
            'evaluation_count' => array('evaluation_count', true),
        );
        
        return $sortable_columns;
    }
    
    function prepare_items() {
        global $wpdb;
        
        $per_page = -1;
        $this->total_votes = 0;
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        $data = $wpdb->get_results("SELECT * FROM $wpdb->posts p, $wpdb->postmeta pm WHERE p.ID = pm.post_id AND p.post_type = 'object' AND p.post_status = 'publish' AND pm.meta_key = '_user_created'");
        
        foreach ($data as $item) {
            $item->evaluation_count = count_votes($item->ID);
            $this->total_votes += $item->evaluation_count;
            
            $item->comments_count = wp_count_comments($item->ID)->approved;
        }
        
        function usort_reorder($a,$b){
            $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'post_title'; //If no sort, default to title
            $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
            $result = strcmp($a->$orderby, $b->$orderby); //Determine sort order
            return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
        }
        usort($data, 'usort_reorder');
        
        $current_page = $this->get_pagenum();
        
        $total_items = count($data);
        
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);
        
        $this->items = $data;
        
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
        
        return $this->items;
    }
    
}

add_action('admin_menu', 'relatorio_menu');
function relatorio_menu() {
    add_submenu_page('theme_options', 'Relatório', 'Relatório', 'manage_options', 'relatorio', 'relatorio_page_callback_function');
}

function relatorio_page_callback_function() {
    global $wpdb;

    $reportTable = new ReportTable;
    
    $totalObjects = $wpdb->get_var("SELECT count(*) FROM $wpdb->posts p, $wpdb->postmeta pm WHERE p.ID = pm.post_id AND p.post_status = 'publish' AND p.post_type = 'object' AND pm.meta_key = '_user_created' AND pm.meta_value != 1");
    
    if (get_theme_option('allow_suggested')) {
        $totalSuggestedObjects = $wpdb->get_var("SELECT count(*) FROM $wpdb->posts p, $wpdb->postmeta pm WHERE p.ID = pm.post_id AND p.post_status = 'publish' AND p.post_type = 'object' AND pm.meta_key = '_user_created' AND pm.meta_value = 1");
    }
    
    $totalComments = wp_count_comments()->approved;
    
    ?>
    <div class="wrap span-20">
        <h2><?php _e('Relatório', 'consulta'); ?></h2>

        <p><?php _e('A tabela abaixo lista todos os objetos desta consulta com o número de comentários e o resultado da avaliação quantitativa.', 'consulta'); ?></p>
        
        <?php if ($reportTable->prepare_items()) : ?>
            <p><?php printf(__('Objetos criados pela equipe: %d', 'consulta'), $totalObjects); ?></p>
        
            <?php if (get_theme_option('allow_suggested')) : ?>
                <p><?php printf(__('Objetos criados pelos usuários: %d', 'consulta'), $totalSuggestedObjects); ?></p>
            <?php endif; ?>
            
            <p><?php printf(__('Total de comentários: %d', 'consulta'), $totalComments); ?></p>
            
            <p><?php printf(__('Total de votos: %d', 'consulta'), $reportTable->total_votes); ?></p>
            
            <?php $reportTable->display(); ?>
        <?php else : ?>
            <p><?php _e('Nenhum objeto criado até o momento.', 'consulta'); ?></p>
        <?php endif; ?>
         
    </div>
    <?php 
}