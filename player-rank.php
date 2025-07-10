<?php
/*
Plugin Name: Player Rank FRMG
Description: Displays WAGR Rank, University Rank, and Chart via shortcodes.
Version: 1.0
Author: Younes Idmoussa
*/

if (!defined('ABSPATH')) exit;

// =========================
// API URL builders
// =========================
function get_wagr_api_url($id) {
    return "https://worldgolfranking2021api.wagr.com/api/wagr/playerprofile/getPlayerRankingGraphData?playerId={$id}&minYear=2025&maxYear=2025";
}

function get_uni_api_url($id) {
    return "https://scoreboard.clippd.com/api/search/players?playerId={$id}";
}

// =========================
// 1. [wagr_rank wagr_api="30949"]
// =========================
function shortcode_wagr_rank($atts) {
    $atts = shortcode_atts(['wagr_api' => ''], $atts);
    if (!$atts['wagr_api']) return 'No WAGR ID provided.';

    $api_url = get_wagr_api_url($atts['wagr_api']);
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) return 'Failed to fetch WAGR data.';

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $latest_rank = 'N/A';
    $latest_week = 0;

    if (is_array($data)) {
        foreach ($data as $entry) {
            if (isset($entry['week'], $entry['rank']) && $entry['week'] > $latest_week) {
                $latest_rank = $entry['rank'];
                $latest_week = $entry['week'];
            }
        }
    }

    return $latest_rank;
}
add_shortcode('wagr_rank', 'shortcode_wagr_rank');

// =========================
// 2. [uni_rank uni_api="13388"]
// =========================
function shortcode_uni_rank($atts) {
    $atts = shortcode_atts(['uni_api' => ''], $atts);
    if (!$atts['uni_api']) return 'No University ID provided.';

    $api_url = get_uni_api_url($atts['uni_api']);
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) return 'Failed to fetch university data.';

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $uni_rank = isset($data['results'][0]['rank']) ? $data['results'][0]['rank'] : 'N/A';

    return $uni_rank;
}
add_shortcode('uni_rank', 'shortcode_uni_rank');

// =========================
// 3. [wagr_chart wagr_api="30949"]
// =========================
function shortcode_wagr_chart($atts) {
    $atts = shortcode_atts(['wagr_api' => ''], $atts);
    if (!$atts['wagr_api']) return 'No WAGR ID provided.';

    $api_url = get_wagr_api_url($atts['wagr_api']);
    $response = wp_remote_get($api_url);
    $data = (!is_wp_error($response)) ? json_decode(wp_remote_retrieve_body($response), true) : [];

    $weeks = [];
    $ranks = [];

    if (is_array($data)) {
        foreach ($data as $entry) {
            if (isset($entry['week'], $entry['rank'])) {
                $week_num = intval(substr($entry['week'], 4));
                $year = substr($entry['week'], 0, 4);
                $date = new DateTime("{$year}-01-01");
                $date->modify('+' . ($week_num - 1) . ' weeks');
                $weeks[] = $date->format('j M');
                $ranks[] = $entry['rank'];
            }
        }
    }

    ob_start();
    ?>

    <div class="player-rank-container">
        <div style="width: 100%; height: 400px;">
            <canvas id="wagrChart-<?php echo esc_attr($atts['wagr_api']); ?>"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx<?php echo esc_js($atts['wagr_api']); ?> = document.getElementById('wagrChart-<?php echo esc_js($atts['wagr_api']); ?>').getContext('2d');
    new Chart(ctx<?php echo esc_js($atts['wagr_api']); ?>, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($weeks); ?>,
            datasets: [{
                label: 'Classement WAGR par semaine (2025)',
                data: <?php echo json_encode($ranks); ?>,
                fill: true,
                backgroundColor: 'rgba(104,199,109,0.1)',
                borderColor: '#4eb85f',
                tension: 0.2,
                pointRadius: 4,
                pointBackgroundColor: '#4eb85f',
                pointBorderColor: '#fff',
				pointHoverBackgroundColor: '#fff',
				pointHoverBorderColor: '#4eb85f'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    reverse: true,
                    title: { display: true, text: 'Classement' }
                },
                x: {
                    title: { display: true, text: 'Semaine' }
                }
            }
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('wagr_chart', 'shortcode_wagr_chart');
