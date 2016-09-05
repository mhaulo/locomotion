<?php
/*
* Plugin Name: Locomotion
* Description: Display data from cyclist and pedestrian counters in beautiful graphs and charts
* Version: 0.1
* Author: Mika Haulo
* Author URI: https://github.com/mhaulo
*/


function locomotion_graph(){
	$station_id = 101;
	$max_days = 7;

	$station_data_uri = "http://www.oulunliikenne.fi/public_traffic_api/eco_traffic/eco_counter_daydata.php?measurementPointId=" . $station_id . "&daysFromHistory=" . $max_days;
	
	$json = json_decode( file_get_contents( $station_data_uri ) );
	$day_results = $json->{"ecoCounterDayResults"};
	
	
	$station_values = array();
	$station_labels = array();
	$station_temp = array();
	
	foreach ( $day_results as $day ) {
		array_push( $station_values, $day->{"value"} );
		array_push( $station_labels, "'" . $day->{"weekday"}  . " " . $day->{"date"} . "'");
		
		$weather_api_root = "https://api.forecast.io/forecast/";
		$weather_api_key = get_option('forecastio_api_key');
		$oulu_lat = "65.012089";
		$oulu_lon = "25.465077";
		$date = DateTime::createFromFormat("d.m.Y", $day->{"date"});
					
		$datestring = strtotime($date->format( "Y-m-d" ) ); 			
		$weather_api_uri = 	"https://api.forecast.io/forecast/" . $weather_api_key . "/" . $oulu_lat . "," . $oulu_lon . "," . $datestring;
		
		$weather_json = json_decode( file_get_contents ( $weather_api_uri ) );
		
		$temp_min = (float) $weather_json->{"daily"}->{"data"}[0]->{"temperatureMin"};
		$temp_max = (float) $weather_json->{"daily"}->{"data"}[0]->{"temperatureMax"};
		$temp_avg = ($temp_min + $temp_max) / 2;
		$temp_celcius = round( ($temp_avg - 32) / 1.8 );
		
		//$temp_celcius = 0;
		
		array_push( $station_temp, $temp_celcius );
	}
	
	$station['labels'] = $station_labels;
	$station['values'] = $station_values;
	$station['temperature'] = $station_temp;
	$id = $station_id;
	$name = "";
	
	$value_string = implode(",", $station["values"]);
	$value_string = str_replace("Dataa ei saatavilla", "0", $value_string);
	
	$temperature_string = implode(",", $station["temperature"]);
	$temperature_string = str_replace("Dataa ei saatavilla", "0", $temperature_string);
	
?>
	<h2>ECO-Counter -mittauspisteen vuorokausiliikenne Oulussa</h2>	
	
	<div class="row">
		<div class="col-xs-12">
			<h3><?php echo $name; ?></h3>
			<canvas id="locomotion_<?php echo $id; ?>" width="400" height="200"></canvas>
		</div>
	</div>
		
	<script>

	var ctx_<?php echo $id; ?> = jQuery("#locomotion_<?php echo $id; ?>");
	var locomotionLineChart_<?php echo $id; ?> = new Chart(ctx_<?php echo $id; ?>, {
		type: 'line',
		data: {
			labels: [<?php echo implode(",", $station["labels"]); ?>],
			datasets: [
				{
					label: "Pyöräilijöiden lukumäärä",
					fill: false,
					lineTension: 0.1,
					backgroundColor: "rgba(75,192,192,0.4)",
					borderColor: "rgba(75,192,192,1)",
					borderCapStyle: 'butt',
					borderDash: [],
					borderDashOffset: 0.0,
					borderJoinStyle: 'miter',
					pointBorderColor: "rgba(75,192,192,1)",
					pointBackgroundColor: "#fff",
					pointBorderWidth: 1,
					pointHoverRadius: 5,
					pointHoverBackgroundColor: "rgba(75,192,192,1)",
					pointHoverBorderColor: "rgba(220,220,220,1)",
					pointHoverBorderWidth: 2,
					pointRadius: 1,
					pointHitRadius: 10,
					data: [<?php echo $value_string; ?>],
					spanGaps: false
				},
				{
					label: "Lämpötila",
					data: [<?php echo $temperature_string; ?>]
				}
			]
		
		}
	});
	</script>
</div>
<?php
}



function locomotion_register_scripts() {
	wp_register_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.2.1/Chart.min.js'); 
	wp_enqueue_script('chartjs'); 

	wp_register_script( 'locomotion',  plugin_dir_url( __FILE__ ) . 'locomotion.js', array('jquery') );
	wp_enqueue_script('locomotion'); 
	
	wp_register_style( 'chartjs', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'); 
	wp_enqueue_style('chartjs');
	
	wp_register_style( 'locomotion',  plugin_dir_url( __FILE__ ) . 'locomotion.css', array('bootstrap') );
	wp_enqueue_style('locomotion'); 
}
add_action('wp_enqueue_scripts','locomotion_register_scripts');

add_shortcode('locomotion', 'locomotion_graph');


add_action('admin_menu', 'locomotion_create_menu');

function locomotion_create_menu() {
	add_menu_page('Locomotion Settings', 'Locomotion', 'administrator', __FILE__, 'locomotion_settings_page' , plugins_url('locomotion.png', __FILE__) );
	
	add_action("admin_init", "display_locomotion_settings_fields");
}



function display_forecastio_element()
{
	?>
    	<input type="text" name="forecastio_api_key" id="forecastio_api_key" value="<?php echo get_option('forecastio_api_key'); ?>" />
    <?php
}

function display_ecostation_list()
{
    	$station_list_uri = "http://www.oulunliikenne.fi/public_traffic_api/eco_traffic/eco_counters.php";
		$station_list = json_decode( file_get_contents( $station_list_uri ) );
		
		echo "<ul>";
		
		foreach ( $station_list->{"ecostation"} as $s) {
			echo "<li><b>" . $s->{"id"} . ": </b>" . $s->{"name"} .  "</li>";
		}
		echo "</ul>";
}

function display_locomotion_settings_fields()
{
	add_settings_section("section", "All Settings", null, "locomotion-options");
	
	add_settings_field("forecastio_api_key", "Forecast.io API key", "display_forecastio_element", "locomotion-options", "section");
   
    register_setting("section", "forecastio_api_key");
}


function locomotion_settings_page() {
?>
<div class="wrap">
	<h1>Locomotion settings</h1>
	<form method="post" action="options.php">
		<?php
			settings_fields("section");
			do_settings_sections("locomotion-options");      
			submit_button(); 
			
			display_ecostation_list();
		?>          
	</form>
</div>

<?php 
} 
?>


