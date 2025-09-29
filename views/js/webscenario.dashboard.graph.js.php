<?php
header('Content-Type: application/javascript');


$trends = [];

if (isset($data['data']) && isset($data['data']['response_trends']) && is_array($data['data']['response_trends'])) {
    $trends = $data['data']['response_trends'];
}


if (empty($trends)) {
    $trends = [
        'Exemplo' => [
            'data' => array_map(function($i) {
                return ['clock' => date('H:i', strtotime("-{$i} hours")), 'value' => rand(100, 500) / 1000];
            }, range(0, 23))
        ]
    ];
}


$series = [];
$allTimePoints = [];


$current_time = date('H:i');
if (is_array($trends)) {
    foreach ($trends as $scenario => $data) {
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $point) {
                if (isset($point['clock']) && $point['clock'] <= $current_time) {
                    $allTimePoints[] = $point['clock'];
                }
            }
        }
    }
}


$timePoints = array_unique($allTimePoints);
sort($timePoints);


if (is_array($trends)) {
    foreach ($trends as $scenario => $data) {
        if (!isset($data['data']) || !is_array($data['data'])) {
            continue;
        }

        $valuesByTime = [];
        foreach ($data['data'] as $point) {
            if (isset($point['clock'], $point['value']) && $point['clock'] <= $current_time) {
                $valuesByTime[$point['clock']] = (float)$point['value'];
            }
        }

        $seriesData = [];
        foreach ($timePoints as $time) {
            $seriesData[] = isset($valuesByTime[$time]) ? $valuesByTime[$time] : null;
        }

        $series[] = [
            'name' => $scenario,
            'type' => 'line',
            'smooth' => true,
            'symbol' => 'circle',
            'symbolSize' => 6,
            'data' => $seriesData
        ];
    }
}


?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartDom = document.getElementById('webscenario-metrics-graph');
    if (!chartDom) return;

    const myChart = echarts.init(chartDom);
    
    const option = {
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: <?php echo json_encode(array_keys($trends)); ?>,
            bottom: 0
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '10%',
            top: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: <?php echo json_encode($timePoints); ?>,
            axisLine: {
                lineStyle: {
                    color: '#E3E3E3'
                }
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: '{value}s'
            },
            splitLine: {
                lineStyle: {
                    type: 'dashed',
                    color: '#E3E3E3'
                }
            }
        },
        series: <?php echo json_encode($series); ?>,
        color: ['#FF4444', '#44B4D6', '#44D6A1']
    };

    myChart.setOption(option);

    window.addEventListener('resize', () => {
        myChart.resize();
    });
});
</script>