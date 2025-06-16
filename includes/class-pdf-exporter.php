<?php
/**
 * PDF Exporter for User Activity Logger
 *
 * @package User_Activity_Logger
 */

if (!defined('ABSPATH')) {
    exit;
}

class UAL_PDF_Exporter {
    private $db;

    public function __construct() {
        $this->db = new UAL_Database();

        add_action('admin_post_ual_export_pdf', array($this, 'handle_export'));
        add_action('admin_post_nopriv_ual_export_pdf', array($this, 'handle_export'));
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission.', 'user-activity-logger'));
        }

        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if (!$user_id) {
            wp_die(__('Invalid user.', 'user-activity-logger'));
        }

        $this->generate_pdf($user_id);
        exit;
    }

    private function generate_pdf($user_id) {
        require_once UAL_PLUGIN_DIR . 'includes/lib/fpdf.php';

        $user = get_userdata($user_id);
        if (!$user) {
            wp_die(__('User not found.', 'user-activity-logger'));
        }

        $settings = get_option('ual_settings', array());
        $company = isset($settings['company']) ? $settings['company'] : array();

        $training_name = isset($company['training_name']) ? $company['training_name'] : '';
        $training_date = $this->get_training_date($user->user_email);

        $sessions = $this->db->get_user_sessions($user_id, 9999, 0);
        $total_duration = 0;
        foreach ($sessions as $session) {
            if ($session->session_duration) {
                $total_duration += $session->session_duration;
            }
        }

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);

        if (!empty($company['company_name'])) {
            $pdf->Cell(0, 10, $company['company_name'], 0, 1, 'C');
        }
        if (!empty($company['representative'])) {
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 6, $company['representative'], 0, 1, 'C');
        }
        if (!empty($company['address'])) {
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 6, $company['address'], 0, 1, 'C');
        }
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, sprintf(__('Activity report for %s', 'user-activity-logger'), $user->display_name), 0, 1);
        if ($training_name) {
            $pdf->Cell(0, 6, sprintf(__('Training: %s', 'user-activity-logger'), $training_name), 0, 1);
        }
        if ($training_date) {
            $pdf->Cell(0, 6, sprintf(__('Start date: %s', 'user-activity-logger'), $training_date), 0, 1);
        }
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 6, __('Total connection time', 'user-activity-logger'), 0, 0);
        $pdf->Cell(0, 6, $this->format_duration($total_duration), 0, 1);
        $pdf->Ln(5);

        foreach ($sessions as $session) {
            $login = date_i18n('Y-m-d H:i', strtotime($session->login_time));
            $logout = $session->logout_time ? date_i18n('Y-m-d H:i', strtotime($session->logout_time)) : __('Active', 'user-activity-logger');
            $duration = $session->session_duration ? $this->format_duration($session->session_duration) : __('Active', 'user-activity-logger');

            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, sprintf(__('Session starting %s', 'user-activity-logger'), $login), 0, 1);
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(0, 6, sprintf(__('IP: %s', 'user-activity-logger'), $session->ip_address), 0, 1);
            $pdf->Cell(0, 6, sprintf(__('Duration: %s', 'user-activity-logger'), $duration), 0, 1);
            $pdf->Cell(0, 6, sprintf(__('Logout: %s', 'user-activity-logger'), $logout), 0, 1);
            $pdf->Ln(3);

            $activities = $this->db->get_session_activities($session->id);
            foreach ($activities as $activity) {
                $formatted = user_activity_logger()->activity_logger->get_formatted_activity($activity);
                $pdf->Cell(5);
                $pdf->MultiCell(0, 5, $formatted['time_formatted'] . ' - ' . $formatted['summary']);
            }
            $pdf->Ln(4);
        }

        $filename = 'user-activity-' . $user->user_nicename . '.pdf';
        $pdf->Output('D', $filename);
    }

    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
    }

    private function get_training_date($email) {
        if (!class_exists('\\FluentCrm\\App\\Models\\Subscriber')) {
            return '';
        }
        $subscriber = \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
        if (!$subscriber) {
            return '';
        }
        $meta = $subscriber->getMeta('date_demarrage_formation');
        return $meta ? $meta : '';
    }
}
