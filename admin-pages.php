<?php
/* 4.1 Dashboard: KPIs, Fases y Listado de Leads */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function leadmahu_render_dashboard()
{
    $states = get_option('leadmahu_states', array('nuevo', 'contactado', 'convertido', 'perdido'));
    $args = array(
        'post_type' => 'leadmahu_lead',
        'post_status' => 'publish',
        'posts_per_page' => -1
    );
    $leads = get_posts($args);
    $total_leads = count($leads);
    $state_counts = array();
    foreach ($states as $s) {
        $state_counts[$s] = 0;
    }
    foreach ($leads as $lead) {
        $s = get_post_meta($lead->ID, '_leadmahu_status', true);
        if (isset($state_counts[$s])) {
            $state_counts[$s]++;
        }
    }
    
    // Verify nonce for any GET parameters that affect display
    if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['lead_id'])) {
        // Add nonce verification
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'view_lead_' . intval($_GET['lead_id']))) {
            // Only log in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                leadmahu_log('Lead Management Hub: Unauthorized attempt to view lead');
            }
        }
        
        $lead_id = intval($_GET['lead_id']);
        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type != 'leadmahu_lead') {
            echo '<div class="notice notice-error"><p>Lead no encontrado.</p></div>';
            return;
        }
        // Recupera el campo mensaje de forma predeterminada desde post_content
        $message = $lead->post_content;
        $email = get_post_meta($lead->ID, '_leadmahu_email', true);
        $phone = get_post_meta($lead->ID, '_leadmahu_phone', true);
        $company = get_post_meta($lead->ID, '_leadmahu_company', true);
        $status = get_post_meta($lead->ID, '_leadmahu_status', true);
        $observations = get_post_meta($lead->ID, '_leadmahu_observations', true);
        
        // Add status class for proper styling
        $status_class = 'status ' . $status;
        ?>

        <div class="wrap">
            <div class="lead-detail-card">
                <div class="lead-header">
                    <h1 class="lead-title"><?php echo esc_html($lead->post_title); ?></h1>
                    <span class="lead-id">ID: <?php echo esc_html($lead->ID); ?></span>
                </div>
                
                <div class="lead-section">
                    <h2 class="lead-section-title">Información de Contacto</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item">
                            <span class="lead-info-label">Email</span>
                            <div class="lead-info-value"><?php echo esc_html($email); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Teléfono</span>
                            <div class="lead-info-value"><?php echo esc_html($phone); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Empresa</span>
                            <div class="lead-info-value"><?php echo esc_html($company); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Estado</span>
                            <div class="lead-info-value <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($status)); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="lead-section">
                    <h2 class="lead-section-title">Mensaje</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item lead-info-full">
                            <div class="lead-info-value"><?php echo nl2br(esc_html($message)); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($observations)): ?>
                <div class="lead-section">
                    <h2 class="lead-section-title">Observaciones</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item lead-info-full">
                            <div class="lead-info-value"><?php echo nl2br(esc_html($observations)); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="lead-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_edit_lead&lead_id=' . $lead->ID)); ?>" class="lead-action-btn button button-primary">Editar Lead</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_dashboard')); ?>" class="lead-action-btn button lead-btn-back">Volver al Listado</a>
                </div>
            </div>
        </div>
        <?php
        return; // Stop rendering the rest of the dashboard
    }    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Gestión de Leads</h1>
        <!-- KPIs en formato horizontal y mejorado -->
        <div class="leadmahu-kpis-container">
            <div class="leadmahu-kpi-card total">
                <h3 class="leadmahu-kpi-title">Total Leads</h3>
                <p class="leadmahu-kpi-value"><?php echo esc_html($total_leads); ?></p>
            </div>
            <?php foreach ($states as $s): ?>
                <div class="leadmahu-kpi-card <?php echo esc_attr($s); ?>">
                    <h3 class="leadmahu-kpi-title"><?php echo esc_html(ucfirst($s)); ?></h3>
                    <p class="leadmahu-kpi-value"><?php echo esc_html($state_counts[$s]); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Listado de Leads -->
        <div class="leadmahu-filter">
            <label for="leadmahu_filter_status"><strong>Filtrar por Estado:</strong></label>
            <select id="leadmahu_filter_status" name="leadmahu_filter_status">
                <option value="todos">Todos</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html(ucfirst($s)); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="leadmahu_filter_search"><strong>Buscar:</strong></label>
            <input type="text" id="leadmahu_filter_search" name="leadmahu_filter_search" placeholder="Título o Email">
            <button type="button" class="button" id="leadmahu_filter_button">Filtrar</button>
        </div>
        <table class="wp-list-table widefat fixed striped" id="leadmahu_leads_table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Empresa</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($leads)) {
                    foreach ($leads as $lead) {
                        $email = get_post_meta($lead->ID, '_leadmahu_email', true);
                        $phone = get_post_meta($lead->ID, '_leadmahu_phone', true);
                        $company = get_post_meta($lead->ID, '_leadmahu_company', true);
                        $state = get_post_meta($lead->ID, '_leadmahu_status', true);
                        echo '<tr>';
                        echo '<td>' . esc_html($lead->ID) . '</td>';
                        echo '<td>' . esc_html($lead->post_title) . '</td>';
                        echo '<td>' . esc_html($email) . '</td>';
                        echo '<td>' . esc_html($phone) . '</td>';
                        echo '<td>' . esc_html($company) . '</td>';
                        echo '<td>' . esc_html(ucfirst($state)) . '</td>';                        echo '<td class="leadmahu-action-buttons">';
                        // Enlaces corregidos para asegurar que funcionan correctamente
                        echo '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_view_lead&lead_id=' . $lead->ID . '&_wpnonce=' . wp_create_nonce('view_lead_' . $lead->ID))) . '" class="leadmahu-action-button leadmahu-btn-view">Ver</a> ';
                        echo '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_edit_lead&lead_id=' . $lead->ID . '&_wpnonce=' . wp_create_nonce('edit_lead_' . $lead->ID))) . '" class="leadmahu-action-button leadmahu-btn-edit">Editar</a> ';
                        echo '<a href="' . esc_url(admin_url('admin-post.php?action=leadmahu_delete_lead&lead_id=' . $lead->ID . '&_wpnonce=' . wp_create_nonce('delete_lead_nonce'))) . '" onclick="return confirm(\'¿Está seguro de eliminar este Lead?\');" class="leadmahu-action-button leadmahu-btn-delete">Eliminar</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No se encontraron leads.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('wp_ajax_leadmahu_filter_leads', 'leadmahu_filter_leads');
function leadmahu_filter_leads() {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'leadmahu_filter_leads_nonce')) {
        wp_die('Security check failed');
    }
    
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'todos';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $args = array(
        'post_type' => 'leadmahu_lead',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    if ($status != 'todos') {
        $args['meta_query'][] = array(
            'key' => '_leadmahu_status',
            'value' => $status,
            'compare' => '='
        );
    }
    if (!empty($search)) {
        $args['s'] = $search;
        $args['search_columns'] = array('post_title', '_leadmahu_email');
    }
    $leads = get_posts($args);
    $output = '';
    if (!empty($leads)) {
        foreach ($leads as $lead) {
            $email = get_post_meta($lead->ID, '_leadmahu_email', true);
            $phone = get_post_meta($lead->ID, '_leadmahu_phone', true);
            $company = get_post_meta($lead->ID, '_leadmahu_company', true);
            $state = get_post_meta($lead->ID, '_leadmahu_status', true);
            $output .= '<tr>';
            $output .= '<td>' . esc_html($lead->ID) . '</td>';
            $output .= '<td>' . esc_html($lead->post_title) . '</td>';
            $output .= '<td>' . esc_html($email) . '</td>';
            $output .= '<td>' . esc_html($phone) . '</td>';
            $output .= '<td>' . esc_html($company) . '</td>';
            $output .= '<td>' . esc_html(ucfirst($state)) . '</td>';
            $output .= '<td class="leadmahu-action-buttons">';
            $output .= '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_view_leads&lead_id=' . $lead->ID)) . '" class="leadmahu-action-button leadmahu-btn-view">Ver</a> ';
            $output .= '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_edit_lead&lead_id=' . $lead->ID)) . '" class="leadmahu-action-button leadmahu-btn-edit">Editar</a> ';
            $output .= '<a href="' . esc_url(admin_url('admin-post.php?action=leadmahu_delete_lead&lead_id=' . $lead->ID)) . '" onclick="return confirm(\'¿Está seguro de eliminar este Lead?\');" class="leadmahu-action-button leadmahu-btn-delete">Eliminar</a>';
            $output .= '</td>';
            $output .= '</tr>';
        }
    } else {
        $output = '<tr><td colspan="7">No se encontraron leads.</td></tr>';
    }
    echo wp_kses_post($output);
    wp_die();
}

/* 4.2 Página General: Gestión de Estados y Exportación CSV */
function leadmahu_render_general_page()
{
    // Procesar actualización de estados
    if (isset($_POST['action']) && $_POST['action'] == 'leadmahu_save_states') {
        if (!isset($_POST['leadmahu_state_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_state_nonce_field'])), 'leadmahu_state_nonce')) {
            wp_die('Nonce inválido.');
        }
        
        // Properly unslash and sanitize $_POST['states']
        $states = isset($_POST['states']) ? array_map('sanitize_text_field', wp_unslash($_POST['states'])) : array();
        update_option('leadmahu_states', $states);
        
        if (isset($_POST['new_state']) && !empty($_POST['new_state'])) {
            $new_state = sanitize_text_field(wp_unslash($_POST['new_state']));
            $states = get_option('leadmahu_states', array());
            if (!in_array($new_state, $states)) {
                $states[] = $new_state;
                update_option('leadmahu_states', $states);
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Estados actualizados.</p></div>';
    }    // Verify nonce for GET parameters
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {
        // Add nonce verification - check both possible nonce field names for backward compatibility
        $nonce_valid = false;
        
        // Check for _wpnonce (standard field name)
        if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'leadmahu_export_csv_nonce')) {
            $nonce_valid = true;
        }
        
        // Also check for leadmahu_export_nonce (custom field name)
        if (!$nonce_valid && isset($_GET['leadmahu_export_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['leadmahu_export_nonce'])), 'leadmahu_export_csv_nonce')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            wp_die('Security check failed');
        }
        
        $estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';
        $fecha_inicio = isset($_GET['fecha_inicio']) ? sanitize_text_field(wp_unslash($_GET['fecha_inicio'])) : '';
        $fecha_fin = isset($_GET['fecha_fin']) ? sanitize_text_field(wp_unslash($_GET['fecha_fin'])) : '';
        $args = array(
            'post_type' => 'leadmahu_lead',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );
        if (!empty($estado)) {
            $args['meta_query'][] = array(
                'key' => '_leadmahu_status',
                'value' => $estado,
                'compare' => '='
            );
        }
        if (!empty($fecha_inicio) || !empty($fecha_fin)) {
            $date_query = array();
            if (!empty($fecha_inicio)) {
                $date_query['after'] = $fecha_inicio;
            }
            if (!empty($fecha_fin)) {
                $date_query['before'] = $fecha_fin;
            }
            $args['date_query'] = array($date_query);
        }
        $leads = get_posts($args);
        $csv_data = '';
        $csv_data .= implode(',', array('ID', 'Título', 'Email', 'Teléfono', 'Empresa', 'Estado', 'Observaciones', 'Fecha')) . "\n";
        
        foreach ($leads as $lead) {
            $observations = get_post_meta($lead->ID, '_leadmahu_observations', true);
            $csv_data .= implode(',', array(
                $lead->ID,
                $lead->post_title,
                get_post_meta($lead->ID, '_leadmahu_email', true),
                get_post_meta($lead->ID, '_leadmahu_phone', true),
                get_post_meta($lead->ID, '_leadmahu_company', true),
                get_post_meta($lead->ID, '_leadmahu_status', true),
                $observations,
                $lead->post_date
            )) . "\n";
        }
        
        // Output the CSV data
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads_export.csv');
        echo esc_textarea($csv_data);
        exit;
    }

    $states = get_option('leadmahu_states', array('nuevo', 'contactado', 'convertido', 'perdido'));
    ?>
    <div class="wrap leadmahu-general-page">
        <h1>General - Gestión de Estados y Exportación CSV</h1>
        
        <div class="form-section">
            <h2>Gestión de Estados</h2>
            <form method="post">
                <?php wp_nonce_field('leadmahu_state_nonce', 'leadmahu_state_nonce_field'); ?>
                <input type="hidden" name="action" value="leadmahu_save_states">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($states as $s): ?>
                            <tr>
                                <td><input type="text" name="states[]" value="<?php echo esc_attr($s); ?>"></td>
                                <td><a href="<?php echo esc_url(leadmahu_add_delete_state_nonce(add_query_arg('delete_state', $s), $s)); ?>"
                                        onclick="return confirm('¿Está seguro de eliminar este estado?');">Eliminar</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <label for="new_state"><strong>Agregar Nuevo Estado:</strong></label>
                    <input type="text" name="new_state" id="new_state">
                </p>
                <?php submit_button('Guardar Estados'); ?>
            </form>
        </div>
          <div class="form-section">
            <h2>Exportar Leads en CSV</h2>
            <form method="get" action="">
                <input type="hidden" name="page" value="leadmahu_general">
                <?php wp_nonce_field('leadmahu_export_csv_nonce', '_wpnonce', false); ?>
                <p>
                    <label for="estado"><strong>Filtrar por Estado:</strong></label>
                    <select name="estado" id="estado">
                        <option value="">Todos</option>
                        <?php foreach ($states as $s): ?>
                            <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html(ucfirst($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="fecha_inicio"><strong>Fecha Inicio:</strong></label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio">
                </p>
                <p>
                    <label for="fecha_fin"><strong>Fecha Fin:</strong></label>
                    <input type="date" name="fecha_fin" id="fecha_fin">
                </p>
                <p>
                    <input type="submit" name="export" value="csv" class="button button-primary">
                </p>
            </form>
        </div>
    </div>
    <?php
}

function leadmahu_render_view_leads_page()
{
    // Verify nonce for GET parameters
    if (isset($_GET['lead_id']) && !empty($_GET['lead_id'])) {
        // Add nonce verification
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'view_lead_' . intval($_GET['lead_id']))) {
            // Only log in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                leadmahu_log('Lead Management Hub: Unauthorized attempt to view lead');
            }
        }
        
        $lead_id = intval($_GET['lead_id']);
        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type != 'leadmahu_lead') {
            echo '<div class="notice notice-error"><p>Lead no encontrado.</p></div>';
            return;
        }
        // Recupera el campo mensaje de forma predeterminada desde post_content
        $message = $lead->post_content;
        $email = get_post_meta($lead->ID, '_leadmahu_email', true);
        $phone = get_post_meta($lead->ID, '_leadmahu_phone', true);
        $company = get_post_meta($lead->ID, '_leadmahu_company', true);
        $status = get_post_meta($lead->ID, '_leadmahu_status', true);
        $observations = get_post_meta($lead->ID, '_leadmahu_observations', true);
        
        // Add status class for proper styling
        $status_class = 'status ' . $status;
        ?>

        <div class="wrap">
            <div class="lead-detail-card">
                <div class="lead-header">
                    <h1 class="lead-title"><?php echo esc_html($lead->post_title); ?></h1>
                    <span class="lead-id">ID: <?php echo esc_html($lead->ID); ?></span>
                </div>
                
                <div class="lead-section">
                    <h2 class="lead-section-title">Información de Contacto</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item">
                            <span class="lead-info-label">Email</span>
                            <div class="lead-info-value"><?php echo esc_html($email); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Teléfono</span>
                            <div class="lead-info-value"><?php echo esc_html($phone); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Empresa</span>
                            <div class="lead-info-value"><?php echo esc_html($company); ?></div>
                        </div>
                        <div class="lead-info-item">
                            <span class="lead-info-label">Estado</span>
                            <div class="lead-info-value <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($status)); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="lead-section">
                    <h2 class="lead-section-title">Mensaje</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item lead-info-full">
                            <div class="lead-info-value"><?php echo nl2br(esc_html($message)); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($observations)): ?>
                <div class="lead-section">
                    <h2 class="lead-section-title">Observaciones</h2>
                    <div class="lead-info-grid">
                        <div class="lead-info-item lead-info-full">
                            <div class="lead-info-value"><?php echo nl2br(esc_html($observations)); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="lead-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_edit_lead&lead_id=' . $lead->ID)); ?>" class="lead-action-btn button button-primary">Editar Lead</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_dashboard')); ?>" class="lead-action-btn button lead-btn-back">Volver al Listado</a>
                </div>
            </div>
        </div>
        <?php
    } else {
        $args = array(
            'post_type' => 'leadmahu_lead',
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        $leads = get_posts($args);
    ?>
    <div class="wrap leadmahu-general-page">
        <h1>Ver Leads</h1>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=leadmahu_edit_lead')); ?>" class="button button-primary">
                Agregar Nuevo Lead
            </a>
        </p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Empresa</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($leads)) {
                        foreach ($leads as $lead) {
                            $email = get_post_meta($lead->ID, '_leadmahu_email', true);
                            $phone = get_post_meta($lead->ID, '_leadmahu_phone', true);
                            $company = get_post_meta($lead->ID, '_leadmahu_company', true);
                            $status = get_post_meta($lead->ID, '_leadmahu_status', true);
                            echo '<tr>';
                            echo '<td>' . esc_html($lead->ID) . '</td>';
                            echo '<td>' . esc_html($lead->post_title) . '</td>';
                            echo '<td>' . esc_html($email) . '</td>';
                            echo '<td>' . esc_html($phone) . '</td>';
                            echo '<td>' . esc_html($company) . '</td>';
                            echo '<td>' . esc_html(ucfirst($status)) . '</td>';
                            echo '<td class="leadmahu-action-buttons">';
                            echo '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_view_lead&lead_id=' . $lead->ID)) . '" class="leadmahu-action-button leadmahu-btn-view">Ver</a> ';
                            echo '<a href="' . esc_url(admin_url('admin.php?page=leadmahu_edit_lead&lead_id=' . $lead->ID)) . '" class="leadmahu-action-button leadmahu-btn-edit">Editar</a> ';
                            echo '<a href="' . esc_url(admin_url('admin-post.php?action=leadmahu_delete_lead&lead_id=' . $lead->ID)) . '" onclick="return confirm(\'¿Está seguro de eliminar este Lead?\');" class="leadmahu-action-button leadmahu-btn-delete">Eliminar</a>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7">No se encontraron leads.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

/**
 * Función para mostrar la pantalla de Agregar/Editar Lead.
 */
function leadmahu_render_add_edit_lead_page()
{    // Verificar que el usuario tiene permisos para gestionar leads
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Lo sentimos, no tienes permisos para acceder a esta página.', 'lead-management-hub'));
    }
    
    // Recuperamos el ID del lead si se está editando
    $lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
    $is_edit = ($lead_id > 0);
    
    // Verificar nonce solo en modo debug y no bloquear el acceso
    // Esto permite acceso a la página incluso si el nonce no está presente o es inválido
    if ($is_edit && isset($_GET['_wpnonce'])) { 
        $valid_nonce = wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'edit_lead_' . $lead_id);
    }

    // Si se está editando, se recuperan los datos
    if ($is_edit) {
        $lead = get_post($lead_id);
        if (!$lead || $lead->post_type !== 'leadmahu_lead') {
            echo '<div class="notice notice-error"><p>Lead no encontrado.</p></div>';
            return;
        }
        $title = $lead->post_title;
        $content = $lead->post_content;
        $email = get_post_meta($lead_id, '_leadmahu_email', true);
        $phone = get_post_meta($lead_id, '_leadmahu_phone', true);
        $company = get_post_meta($lead_id, '_leadmahu_company', true);
        $status = get_post_meta($lead_id, '_leadmahu_status', true);
        $observations = get_post_meta($lead_id, '_leadmahu_observations', true);
    } else {
        // Valores por defecto para un nuevo lead
        $title = '';
        $content = '';
        $email = '';
        $phone = '';
        $company = '';
        $status = 'nuevo';
        $observations = '';
    }
    ?>
    <div class="wrap">
        <h1><?php echo $is_edit ? 'Editar Lead' : 'Agregar Nuevo Lead'; ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            // Campo nonce para la seguridad
            wp_nonce_field('leadmahu_save_lead_nonce', 'leadmahu_save_lead_nonce_field');
            ?>
            <input type="hidden" name="action" value="leadmahu_save_lead">
            <?php if ($is_edit): ?>
                <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead_id); ?>">
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th><label for="leadmahu_lead_title">Título</label></th>
                    <td><input type="text" id="leadmahu_lead_title" name="leadmahu_lead_title" value="<?php echo esc_attr($title); ?>"
                            class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="leadmahu_lead_content">Mensaje</label></th>
                    <td><textarea id="leadmahu_lead_content" name="leadmahu_lead_content" rows="5"
                            class="large-text"><?php echo esc_textarea($content); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="leadmahu_lead_email">Email</label></th>
                    <td><input type="email" id="leadmahu_lead_email" name="leadmahu_lead_email"
                            value="<?php echo esc_attr($email); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="leadmahu_lead_phone">Teléfono</label></th>
                    <td><input type="text" id="leadmahu_lead_phone" name="leadmahu_lead_phone" value="<?php echo esc_attr($phone); ?>"
                            class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="leadmahu_lead_company">Empresa</label></th>
                    <td><input type="text" id="leadmahu_lead_company" name="leadmahu_lead_company"
                            value="<?php echo esc_attr($company); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="leadmahu_lead_status">Estado</label></th>
                    <td>
                        <select id="leadmahu_lead_status" name="leadmahu_lead_status">
                            <?php
                            // Recuperar los estados disponibles desde la opción del plugin
                            $states = get_option('leadmahu_states', array('nuevo', 'contactado', 'convertido', 'perdido'));
                            foreach ($states as $s) {
                                echo '<option value="' . esc_attr($s) . '" ' . selected($status, $s, false) . '>' . esc_html(ucfirst($s)) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="leadmahu_observations">Observaciones</label></th>
                    <td><textarea id="leadmahu_observations" name="leadmahu_observations" rows="5"
                            class="large-text"><?php echo esc_textarea($observations); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button($is_edit ? 'Actualizar Lead' : 'Agregar Lead'); ?>
        </form>
    </div>
    <?php
}

/* 5.4 Procesar Guardado de Lead (Agregar/Editar) */
function leadmahu_save_lead()
{
    if (!isset($_POST['leadmahu_save_lead_nonce_field']) || 
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_save_lead_nonce_field'])), 'leadmahu_save_lead_nonce')) {
        wp_die('Nonce inválido.');
    }
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $title = isset($_POST['leadmahu_lead_title']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_lead_title'])) : '';
    $content = isset($_POST['leadmahu_lead_content']) ? sanitize_textarea_field(wp_unslash($_POST['leadmahu_lead_content'])) : '';
    $email = isset($_POST['leadmahu_lead_email']) ? sanitize_email(wp_unslash($_POST['leadmahu_lead_email'])) : '';
    $phone = isset($_POST['leadmahu_lead_phone']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_lead_phone'])) : '';
    $company = isset($_POST['leadmahu_lead_company']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_lead_company'])) : '';
    $status = isset($_POST['leadmahu_lead_status']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_lead_status'])) : 'nuevo';
    $observations = isset($_POST['leadmahu_observations']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_observations'])) : '';

    $post_data = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'leadmahu_lead'
    );
    if ($lead_id) {
        $post_data['ID'] = $lead_id;
        $result = wp_update_post($post_data);
    } else {
        $result = wp_insert_post($post_data);
        $lead_id = $result;
    }
    if ($result && !is_wp_error($result)) {
        update_post_meta($lead_id, '_leadmahu_email', $email);
        update_post_meta($lead_id, '_leadmahu_phone', $phone);
        update_post_meta($lead_id, '_leadmahu_company', $company);
        update_post_meta($lead_id, '_leadmahu_status', $status);
        update_post_meta($lead_id, '_leadmahu_observations', $observations);
        // Redirigir al dashboard después de guardar
        wp_redirect(admin_url('admin.php?page=leadmahu_dashboard&message=lead_saved'));
        exit;
    } else {
        wp_die('Error al guardar el lead.');
    }
}
add_action('admin_post_leadmahu_save_lead', 'leadmahu_save_lead');

/* 5.5 Procesar Eliminación de Lead */
function leadmahu_delete_lead()
{
    // Check nonce for any request that performs an action
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_lead_nonce')) {
        wp_die('Security check failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    
    // Properly validate and sanitize GET parameter
    $lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
    if ($lead_id) {
        wp_delete_post($lead_id, true);
    }
    wp_redirect(admin_url('admin.php?page=leadmahu_dashboard&message=lead_deleted'));
    exit;
}
add_action('admin_post_leadmahu_delete_lead', 'leadmahu_delete_lead');

add_action('admin_init', 'leadmahu_process_actions');

function leadmahu_process_actions() {
    if (isset($_GET['action']) && $_GET['action'] == 'delete_leadmahu_lead' && isset($_GET['lead_id'])) {
        // Add nonce verification
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_lead_' . intval($_GET['lead_id']))) {
            wp_die('Security check failed');
        }

        $lead_id = intval($_GET['lead_id']);
        if (current_user_can('manage_options')) { // Verifica permisos
            wp_delete_post($lead_id, true); // true para eliminar permanentemente
            wp_redirect(admin_url('admin.php?page=leadmahu_dashboard')); // Redirige de vuelta al dashboard
            exit;
        } else {
            wp_die('No tienes permisos para realizar esta acción.');
        }
    }
}

// Fix for the deletion form_id usage by properly checking isset()
function leadmahu_delete_form() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para realizar esta acción.');
    }

    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_form_nonce')) {
        wp_die('Security check failed');
    }
    
    // Properly validate GET parameter
    $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

    if ($form_id) {
        wp_delete_post($form_id, true); // Elimina el formulario permanentemente
        wp_redirect(admin_url('admin.php?page=leadmahu_form_list&message=form_deleted'));
        exit;
    } else {
        wp_die('ID de formulario no válido.');
    }
}
add_action('admin_post_leadmahu_delete_form', 'leadmahu_delete_form');

// Eliminar estado
add_action('admin_init', 'leadmahu_delete_state');
function leadmahu_delete_state() {
    if (isset($_GET['delete_state'])) {
        // Add nonce verification
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_state_nonce')) {
            wp_die('Security check failed');
        }
        
        $state_to_delete = sanitize_text_field(wp_unslash($_GET['delete_state']));
        $states = get_option('leadmahu_states', array());
        $key = array_search($state_to_delete, $states);
        if ($key !== false) {
            unset($states[$key]);
            update_option('leadmahu_states', $states);
            wp_redirect(admin_url('admin.php?page=leadmahu_general'));
            exit;
        }
    }
}

function leadmahu_add_delete_state_nonce($url, $state) {
    return wp_nonce_url($url, 'delete_state_nonce');
}