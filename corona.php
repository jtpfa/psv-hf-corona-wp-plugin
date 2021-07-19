<?php
add_action('rest_api_init', 'sign_in');
add_action('rest_api_init', 'sign_out');

function sign_in()
{
    register_rest_route('corona', 'in', array(
        'methods' => 'POST',
        'callback' => 'sign_user_in',
    ));
}

function sign_out()
{
    register_rest_route('corona', 'out', array(
        'methods' => 'POST',
        'callback' => 'sign_user_out',
    ));
}

function get_list()
{
    register_rest_route('corona', 'list', array(
        'methods' => 'GET',
        'callback' => 'get_list_of_users',
        'permission_callback' => function () {
            return current_user_can( 'export' );
        }
    ));
}

function sign_user_in($request)
{
    $new_entry = prepare_data($request, 'in', 'Ein oder mehrere Felder sind nicht richtig ausgefüllt.');

    if ($new_entry instanceof WP_Error) {
        return $new_entry;
    }

    global $wpdb;
    $result = $wpdb->insert($wpdb->base_prefix . 'corona_anwesenheitsliste', $new_entry);
    if (!$result) {
        return rest_ensure_response(new WP_Error(500, 'Du konntest nicht eingetragen werden.', ''));
    }

    return rest_ensure_response('');
}

function sign_user_out($request)
{
    $entry = prepare_data($request, 'out', 'Die Austragung hat nicht funktioniert.');

    if ($entry instanceof WP_Error) {
        return rest_ensure_response(new WP_Error(500,'Die Austragung funktioniert gerade nicht.'));
    }

    setlocale(LC_TIME, 'de_DE');
    date_default_timezone_set('Europe/Berlin');

    $update = array(
        'bis' => (new Datetime('now'))->format('Y-m-d H:i:s'),
        'aktiv' => false,
    );

    global $wpdb;
    $result = $wpdb->update($wpdb->base_prefix . 'corona_anwesenheitsliste', $update, $entry);
    if ($result === 0 || !$result) {
        return rest_ensure_response(new WP_Error(500, 'Die Austragung hat leider nicht funktioniert.'));
    }

    return rest_ensure_response('');
}

function prepare_data($request, $type, $message)
{
    $params = $request->get_params();
    setlocale(LC_TIME, 'de_DE');
    date_default_timezone_set('Europe/Berlin');
    $data = array(
        'vorname' => sanitize_text_field($params['firstname']),
        'nachname' => sanitize_text_field($params['name']),
        'telefon' => preg_replace(array('/ /','/\//'), array('',''), sanitize_text_field($params['tel'])),
        'straße' => sanitize_text_field($params['street']),
        'nummer' => sanitize_text_field($params['number']),
        'plz' => sanitize_text_field($params['zip']),
        'ort' => sanitize_text_field($params['city']),
        'aktiv' => true,
    );
    if ($type === 'in') {
        $data = array_merge($data, array(
            'von' => (new Datetime('now'))->format('Y-m-d H:i:s'),
        ));
    }

    foreach ($data as $el) {
        if (is_null($el)) {
            return rest_ensure_response(new WP_Error(500, $message, ''));
        }
    }

    return $data;
}
