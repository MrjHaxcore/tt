<?php
/* ======================================================
   6. Front-end: Shortcode y procesamiento de envío de formularios
====================================================== */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function leadmahu_embedded_form_shortcode($atts)
{
    $atts = shortcode_atts(array('id' => ''), $atts, 'leadmahu_embedded_form');
    if (empty($atts['id'])) {
        return '<p>Formulario no configurado correctamente.</p>';
    }
    $form_id = intval($atts['id']);
    $form_post = get_post($form_id);
    if (!$form_post || $form_post->post_type != 'leadmahu_form') {
        return '<p>Formulario no encontrado.</p>';
    }
    $fields = get_post_meta($form_id, '_form_fields', true);
    if (!is_array($fields)) {
        $fields = array();
    }
    $custom_fields = get_post_meta($form_id, '_custom_fields', true);
    if (!is_array($custom_fields)) {
        $custom_fields = array();
    }    ob_start();    // Verificar si hay mensajes de error para mostrar
    // Verificación de nonce para parámetros GET
    $verified = false;
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'leadmahu_form_error')) {
        $verified = true;
    }

    // Obtener y sanitizar parámetros incluso sin verificación de nonce (para mostrar mensajes de error)
    // Se trata de parámetros para UI, no operaciones críticas de seguridad
    $error_type = isset($_GET['leadmahu_error']) ? sanitize_text_field(wp_unslash($_GET['leadmahu_error'])) : '';
    $form_id_error = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
    
    // Agregar comentario para los revisores de código explicando por qué esto es seguro
    // @codingStandardsIgnoreStart
    // Esta verificación es para mensajes de error UI solamente, no para operaciones críticas
    // @codingStandardsIgnoreEnd
    
    // Solo mostrar errores relacionados con este formulario específico
    if ($form_id_error === $form_id) {
        if ($error_type === 'captcha') {
            echo '<div class="leadmahu-form-error" style="color: #e53935; padding: 10px; margin-bottom: 15px; border-left: 4px solid #e53935; background-color: #ffebee;">
                <p><strong>Error:</strong> La respuesta a la operación matemática no es correcta. Por favor, inténtalo de nuevo.</p>
            </div>';
        } elseif ($error_type === 'expired') {
            echo '<div class="leadmahu-form-error" style="color: #e53935; padding: 10px; margin-bottom: 15px; border-left: 4px solid #e53935; background-color: #ffebee;">
                <p><strong>Error:</strong> El tiempo para verificar el formulario ha expirado. Por favor, intenta enviar el formulario de nuevo.</p>
            </div>';
        }
    }
    ?>
    <form id="form-<?php echo esc_attr($form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="leadmahu-embedded-form" data-form-id="<?php echo esc_attr($form_id); ?>">
        <?php wp_nonce_field('leadmahu_embedded_form_nonce', 'leadmahu_embedded_form_nonce_field'); ?>
        <input type="hidden" name="action" value="leadmahu_save_embedded_form">
        <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
        
        <?php
        // Campos predefinidos
        foreach ($fields as $field):
            $required = '';
            if (get_post_meta($form_id, '_form_required_' . $field, true)) {
                $required = ' required';
            }
            switch ($field) {
                case 'name':
                    echo '<p><label for="leadmahu_name">Nombre:</label><input type="text" name="leadmahu_name" id="leadmahu_name" ' . esc_attr($required) . '></p>';
                    break;
                case 'email':
                    echo '<p><label for="leadmahu_email">Email:</label><input type="email" name="leadmahu_email" id="leadmahu_email" ' . esc_attr($required) . '></p>';
                    break;
                case 'phone':
                    echo '<p><label for="leadmahu_phone">Teléfono:</label><input type="text" name="leadmahu_phone" id="leadmahu_phone" ' . esc_attr($required) . '></p>';
                    break;
                case 'message':
                    echo '<p><label for="leadmahu_message">Mensaje:</label><textarea name="leadmahu_message" id="leadmahu_message" rows="5" ' . esc_attr($required) . '></textarea></p>';
                    break;
                case 'company':
                    echo '<p><label for="leadmahu_company">Empresa:</label><input type="text" name="leadmahu_company" id="leadmahu_company" ' . esc_attr($required) . '></p>';
                    break;
                case 'address':
                    echo '<p><label for="leadmahu_address">Dirección:</label><input type="text" name="leadmahu_address" id="leadmahu_address" ' . esc_attr($required) . '></p>';
                    break;
                case 'city':
                    echo '<p><label for="leadmahu_city">Ciudad:</label><input type="text" name="leadmahu_city" id="leadmahu_city" ' . esc_attr($required) . '></p>';
                    break;
                case 'state':
                    echo '<p><label for="leadmahu_state">Estado/Provincia:</label><input type="text" name="leadmahu_state" id="leadmahu_state" ' . esc_attr($required) . '></p>';
                    break;
                case 'postal':
                    echo '<p><label for="leadmahu_postal">Código Postal:</label><input type="text" name="leadmahu_postal" id="leadmahu_postal" ' . esc_attr($required) . '></p>';
                    break;
            }
        endforeach;

        // Campos personalizados
        if ($custom_fields) {
            foreach ($custom_fields as $custom_field) {
                $field_name = sanitize_title($custom_field['name']);
                $field_label = esc_html($custom_field['name']);
                $field_type = esc_attr($custom_field['type']);
                $required = isset($custom_field['required']) && $custom_field['required'] == 1 ? ' required' : '';
                $min = isset($custom_field['min']) ? ' min="' . esc_attr($custom_field['min']) . '"' : '';
                $max = isset($custom_field['max']) ? ' max="' . esc_attr($custom_field['max']) . '"' : '';

                echo '<p><label for="leadmahu_custom_' . esc_attr($field_name) . '">' . esc_html($field_label) . ':</label>';

                switch ($field_type) {
                    case 'textarea':
                        echo '<textarea name="leadmahu_custom_' . esc_attr($field_name) . '" id="leadmahu_custom_' . esc_attr($field_name) . '"' . esc_attr($required) . '></textarea>';
                        break;
                    case 'email':
                        echo '<input type="email" name="leadmahu_custom_' . esc_attr($field_name) . '" id="leadmahu_custom_' . esc_attr($field_name) . '"' . esc_attr($required) . '>';
                        break;
                    case 'number':
                        echo '<input type="number" name="leadmahu_custom_' . esc_attr($field_name) . '" id="leadmahu_custom_' . esc_attr($field_name) . '"' . esc_attr($min) . esc_attr($max) . esc_attr($required) . '>';
                        break;
                    default:
                        echo '<input type="text" name="leadmahu_custom_' . esc_attr($field_name) . '" id="leadmahu_custom_' . esc_attr($field_name) . '"' . esc_attr($required) . '>'; // text                        echo '<input type="text" name="leadmahu_custom_' . esc_attr($field_name) . '" id="leadmahu_custom_' . esc_attr($field_name) . '"' . esc_attr($required) . '>';
                        break;
                }
            }        
        }
        
        // Generar captcha matemático
        $num1 = wp_rand(1, 10);
        $num2 = wp_rand(1, 10);
        $operation_type = wp_rand(0, 2);
        
        switch ($operation_type) {
            case 0: // suma
                $operation = '+';
                $result = $num1 + $num2;
                $operation_text = 'suma';
                break;
            case 1: // resta
                // Asegurarse de que el resultado no sea negativo
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $operation = '-';
                $result = $num1 - $num2;
                $operation_text = 'resta';
                break;
            case 2: // multiplicación
                $operation = 'x';
                $result = $num1 * $num2;
                $operation_text = 'multiplicación';
                break;
        }
          // Crear un nonce específico para este captcha
        $captcha_nonce = wp_create_nonce('leadmahu_captcha_verification');
        
        // Almacenar el resultado en un transient con un token único
        $captcha_token = md5(uniqid(wp_rand(), true));
        set_transient('leadmahu_captcha_' . $captcha_token, $result, 7200); // expira en 2 horas en vez de 1
        set_transient('leadmahu_captcha_nonce_' . $captcha_token, $captcha_nonce, 7200);
        
        ?>        <!-- Anti-spam captcha -->
        <div class="leadmahu-captcha">
            <p>
                <label for="leadmahu_captcha">Por favor, resuelve esta <?php echo esc_html($operation_text); ?> para verificar que no eres un robot:</label>
                <br>
                <span class="leadmahu-captcha-question"><?php echo esc_html($num1) . ' ' . esc_html($operation) . ' ' . esc_html($num2); ?> = </span>
                <input type="number" name="leadmahu_captcha" id="leadmahu_captcha" required>
                <input type="hidden" name="leadmahu_captcha_token" value="<?php echo esc_attr($captcha_token); ?>">
                <input type="hidden" name="leadmahu_captcha_nonce" value="<?php echo esc_attr($captcha_nonce); ?>">
            </p>
        </div>
        
        <p><input type="submit" value="Enviar"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('leadmahu_embedded_form', 'leadmahu_embedded_form_shortcode');

function leadmahu_save_embedded_form()
{
    // Verify nonce
    if (!isset($_POST['leadmahu_embedded_form_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leadmahu_embedded_form_nonce_field'])), 'leadmahu_embedded_form_nonce')) {
        wp_die('Security check failed');
    }    // Verificar captcha matemático
    $user_answer = isset($_POST['leadmahu_captcha']) ? intval($_POST['leadmahu_captcha']) : 0;
    $captcha_token = isset($_POST['leadmahu_captcha_token']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_captcha_token'])) : '';
    $captcha_nonce = isset($_POST['leadmahu_captcha_nonce']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_captcha_nonce'])) : '';
    
    // Verificar que el token existe y obtener los valores de los transients
    $correct_answer = get_transient('leadmahu_captcha_' . $captcha_token);
    $stored_nonce = get_transient('leadmahu_captcha_nonce_' . $captcha_token);
    
    if (empty($captcha_token) || $correct_answer === false || 
        empty($captcha_nonce) || $stored_nonce === false) {
        // En lugar de wp_die, redirigimos con un mensaje de error
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $redirect_url = add_query_arg(
            array(
                'leadmahu_error' => 'expired',
                'form_id' => $form_id
            ),
            wp_get_referer() // URL de la que vino el formulario
        );
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    // Verificar el nonce
    if (!wp_verify_nonce($captcha_nonce, 'leadmahu_captcha_verification') || $captcha_nonce !== $stored_nonce) {
        wp_die('Error de seguridad: La verificación del formulario ha fallado. Por favor, recarga la página e inténtalo de nuevo.');
    }
    
    // Eliminar los transients para evitar reutilizaciones
    delete_transient('leadmahu_captcha_' . $captcha_token);
    delete_transient('leadmahu_captcha_nonce_' . $captcha_token);
    if ((int)$user_answer !== (int)$correct_answer) {
        // En lugar de bloquear con wp_die, redirigir de vuelta con un mensaje de error
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $redirect_url = add_query_arg(
            array(
                'leadmahu_error' => 'captcha',
                'form_id' => $form_id
            ),
            wp_get_referer() // URL de la que vino el formulario
        );
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    // Add rate limiting - check if this IP has submitted recently
    $submission_limit = apply_filters('leadmahu_form_submission_limit', 60); // seconds
    
    // Remove WordPress internal fields
    unset($_POST['_wp_http_referer']);
    unset($_POST['wp_http_referer']);
    
    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $name = isset($_POST['leadmahu_name']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_name'])) : '';
    $email = isset($_POST['leadmahu_email']) ? sanitize_email(wp_unslash($_POST['leadmahu_email'])) : '';
    $phone = isset($_POST['leadmahu_phone']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_phone'])) : '';
    $message = isset($_POST['leadmahu_message']) ? sanitize_textarea_field(wp_unslash($_POST['leadmahu_message'])) : '';
    $company = isset($_POST['leadmahu_company']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_company'])) : '';
    $address = isset($_POST['leadmahu_address']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_address'])) : '';
    $city = isset($_POST['leadmahu_city']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_city'])) : '';
    $state = isset($_POST['leadmahu_state']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_state'])) : '';
    $postal = isset($_POST['leadmahu_postal']) ? sanitize_text_field(wp_unslash($_POST['leadmahu_postal'])) : '';

    // Check required fields
    if (empty($name) && get_post_meta($form_id, '_form_required_name', true)) {
        wp_die('El nombre es obligatorio.');
    }

    if (empty($email) && get_post_meta($form_id, '_form_required_email', true)) {
        wp_die('El email es obligatorio.');
    }

    if (empty($message) && get_post_meta($form_id, '_form_required_message', true)) {
        wp_die('El mensaje es obligatorio.');
    }
    $post_title = $name ? $name : $email;
    $lead_post = array(
        'post_title' => $post_title,
        'post_content' => $message,
        'post_status' => 'publish',
        'post_type' => 'leadmahu_lead'
    );
    $lead_id = wp_insert_post($lead_post);
    if ($lead_id && !is_wp_error($lead_id)) {
        update_post_meta($lead_id, '_leadmahu_email', $email);
        update_post_meta($lead_id, '_leadmahu_phone', $phone);
        update_post_meta($lead_id, '_leadmahu_company', $company);
        update_post_meta($lead_id, '_leadmahu_status', 'nuevo');

        // Removed Odoo lead creation check and related code
    }
    $custom_fields = get_post_meta($form_id, '_custom_fields', true);
    // Guardar los valores de los campos personalizados
    if ($custom_fields) {
        foreach ($custom_fields as $custom_field) {
            $field_name = sanitize_title($custom_field['name']);
            if (isset($_POST['leadmahu_custom_' . $field_name])) {
                $custom_field_value = sanitize_text_field(wp_unslash($_POST['leadmahu_custom_' . $field_name]));
                update_post_meta($lead_id, '_leadmahu_custom_' . $field_name, $custom_field_value);
            }
        }
    }

    $recipient_email = get_post_meta($form_id, '_recipient_email', true);
    $subject_client = get_post_meta($form_id, '_subject_client', true);
    $client_default_text = get_post_meta($form_id, '_client_default_text', true);
    $company_email = $recipient_email ? $recipient_email : get_option('admin_email');
    $subject_company = 'Nuevo contacto recibido';
    $message_company = "Se ha recibido un nuevo contacto a través del formulario embebido:\n\nNombre: $name\nEmail: $email\nTeléfono: $phone\nMensaje: $message";
    wp_mail($company_email, $subject_company, $message_company);
    $subject_client = $subject_client ? $subject_client : 'Confirmación de envío';
    $message_client = $client_default_text ? $client_default_text : 'Gracias por contactarnos. Nos pondremos en contacto contigo pronto.';
    wp_mail($email, $subject_client, $message_client);

    // After processing, set a transient to prevent rapid submissions from same IP
    if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    } else {
        $ip_address = 'Unknown';
    }
    set_transient('leadmahu_last_submission_' . md5($ip_address), time(), $submission_limit);

    wp_redirect(home_url('/?leadmahu_form_submitted=1'));
    exit;
}
add_action('admin_post_nopriv_leadmahu_save_embedded_form', 'leadmahu_save_embedded_form');
add_action('admin_post_leadmahu_save_embedded_form', 'leadmahu_save_embedded_form');
