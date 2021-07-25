<?php
/*
* Plugin Name: Anwesenheitsliste
* Description: Exportiert alle Besucher:innen der vergangenen 3 Wochen als PDF.
* Version: 1.2.1
* Author: Jonas Pfannkuche
*/

if (!defined('ABSPATH')) {
    exit;
}

include('anwesenheitsliste-helper.php');
include_once('corona.php');

if (isset($_POST['generate_pdf'])) {
    generate_pdf();
}

add_action('admin_menu', 'anwesenheitsliste_create_admin_menu');
function anwesenheitsliste_create_admin_menu()
{
    $hook = add_submenu_page(
        'tools.php',
        'Anwesenheitsliste',
        'Anwesenheitsliste',
        'manage_options',
        'anwesenheitsliste',
        'anwesenheitsliste_create_admin_page'
    );
}

function cron_activation()
{
    if (!wp_next_scheduled('delete_old_entries')) {
        wp_schedule_event(time(), 'daily', 'delete_old_entries');
    }
}

add_action('wp', 'cron_activation');

function cron_deactivation()
{
    $timestamp = wp_next_scheduled('delete_old_entries');
    wp_unschedule_event($timestamp, 'delete_old_entries');
}

register_deactivation_hook(__FILE__, 'cron_deactivation');

function cleanup_entries() {
    global $wpdb;
    $wpdb->query('DELETE FROM ' . $wpdb->base_prefix . 'corona_anwesenheitsliste WHERE aktiv <> 1 AND TIMESTAMPDIFF(DAY, von, CURRENT_TIMESTAMP()) > 21');
}

add_action ('delete_old_entries', 'cleanup_entries');

function load_vuescripts()
{
    wp_register_script('anwesenheitsliste_vuejs', plugin_dir_url(__FILE__) . '/js/vue.js');
    wp_register_script('anwesenheitsliste_vuecookiesjs', plugin_dir_url(__FILE__) . '/js/vue-cookies.js', 'anwesenheitsliste_vuejs', false);
    wp_register_script('anwesenheitsliste_vueappjs', plugin_dir_url(__FILE__) . '/js/app.js', ['anwesenheitsliste_vuejs', 'anwesenheitsliste_vuecookiesjs'], false, true);
}

add_action('wp_enqueue_scripts', 'load_vuescripts');

function shortcode_anwesenheitsliste(): string
{
    wp_enqueue_script('anwesenheitsliste_vuejs');
    wp_enqueue_script('anwesenheitsliste_vuecookiesjs');
    wp_enqueue_script('anwesenheitsliste_vueappjs');

    $user = wp_get_current_user();
    if (array_intersect(['administrator'], $user->roles)) {
        // @TODO
        return "<div id='app'></div>";
    }


    return "<div id='app'></div>";
}

add_shortcode('anwesenheitsliste', 'shortcode_anwesenheitsliste');

function generate_pdf()
{
    global $wpdb;
    $result = $wpdb->get_results('SELECT CONCAT(nachname, \', \', vorname) as name, telefon, CONCAT(straße,\' \', nummer, \'\, \', plz, \' \', ort) as adresse,  DATE_FORMAT(von, \'%d.%m.%Y %T\')  as ankunft, COALESCE(DATE_FORMAT(bis, \'%d.%m.%Y %T\'), \'Noch da\') as abfahrt, aktiv FROM ' . $wpdb->base_prefix . 'corona_anwesenheitsliste', ARRAY_N);

    if (isset($result)) {
        $pdf = new PDF();
        $pdf->AliasNbPages("{nb}");
        $pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
        $pdf->SetFont('DejaVu','',10);

        output_pdf($result, $pdf);
    }
}

function output_pdf($data, $pdf, $page = 1)
{
    if (isset($data)) {
        $headers = array("Name", "Telefon", "Adresse", "Von", "Bis");
        $nb_headers = count($headers);
        $rows = $data;
        $nb_rows = count($rows);

        $pdf->AddPage("L", "A3");

        $template = $pdf->LoadTemplate();
        if ($template <= 0) {
            die ("  ** Error couldn't load template file");
        }
        $pdf->IncludeTemplate($template);

        $pdf->ApplyTextProp("FOOTRNB2", "$page / {nb}");

        $pcol = $pdf->GetColls("COLSWDTH", "");
        $ptxp = $pdf->ApplyTextProp("ROW0COL0", "");

        for ($ii = 0; $ii < $nb_headers; $ii++) {
            $header = $headers[$ii];
            $pdf->SetX($pdf->GetX() + 1);
            $pdf->Cell($pcol [$ii], $ptxp ['iy'], $header, 1, 0, "C", true);
        }
        $pdf->SetFillColor(240, 240, 240);

        $ptxp = $pdf->ApplyTextProp("ROW1COL0", "");
        $py = $ptxp ['py'];
        $page_break = false;

        for ($jj = 0; $jj < $nb_rows; $jj++) {
            $pdf->SetXY($ptxp ['px'], $py);
            $row = $rows[$jj];
            for ($ii = 0; $ii < $nb_headers; $ii++) {
                $value = trim($row[$ii]);
                $pdf->SetX($pdf->GetX() + 1);
                $pdf->Cell($pcol [$ii], $ptxp ['iy'], $value, "", 0, "L", $jj & 1);
            }
            $py += $ptxp ['iy'];

            if ($pdf->checkPageBreak()) {
                $page_break = true;
                break;
            }
        }

        if ($page_break) {
            $remaining_data = array_slice($rows, -(count($rows) - $jj - 1));
            output_pdf($remaining_data, $pdf, ++$page);
        }

        $pdf->Output('D', 'Anwesenheitsliste (Stand ' . date('d.m.Y H_i') . ').pdf');
    }

    exit;
}


function anwesenheitsliste_create_admin_page()
{
    ?>
    <div class="wrap">
        <h1>Anwesenheitsliste - PDF Export</h1>
        <p>Klick auf den Button, um ein PDF herunterzuladen, das alle Anwesenden der letzten 3 Wochen samt ihrer
            persönlichen Daten beinhaltet.</p>
        <h3>DENK DARAN:</h3>
        <p><strong>Datenschutz!!! </strong>Diese Daten müssen spätestens nach 4 Wochen nach Erfassung gelöscht/ zerstört
            werden.<br>
            Keine Sorge das passiert auf dem Server automatisch. Du kannst also gar nicht versehentlich "alte" Daten
            herunterladen.<br>
            Aber sobald du auf den Button klickst, bist du für die Löschung von deinem Gerät und Zerstörung - wenn du
            die Liste ausgedruckt hast - zuständig!!!
        </p>
        <form method="post" id="export_pdf_form">
            <button class="button button-primary" type="submit" name="generate_pdf" value="generate">
                Exportieren
            </button>
        </form>
    </div>
    <?php
}
