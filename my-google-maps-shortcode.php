<?php
/**
 * Plugin Name: My google Maps Shortcode
 * Plugin URI:  https://mediauganda.com/
 * Description: Allows users add flexible google maps to pages.
 * Author:      MediaUganda
 * Author URI:  https://mediauganda.com
 * Version:     0.1.0
 * License:     0.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: new-map
 *
 * @package New_Map
 */

function my_goog_map_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'address' 	=> false,
            'width' 	=>  '100%',
            'height' 	=>  '400px'
        ),
        $atts
    );
    $address = $atts['address'];

    if( $address ):

    //Prints scripts in document head that are in the $handles queue
        wp_print_scripts( 'google-maps-api' );
    
        $coordinates = my_goog_map_get_coordinates( $address );
    
        if( ! is_array( $coordinates ) )
            return;

    // generate a unique ID for this map
        $map_id = uniqid( 'my_goog_map_' ); 
    
        ob_start(); 
?>

<script type="text/javascript">

    var map_<?php echo $map_id; ?>;
    function my_goog_run_map_<?php echo $map_id ; ?>(){
        var location = new google.maps.LatLng("<?php echo $coordinates['lat']; ?>", "<?php echo $coordinates['lng']; ?>");
        var map_options = {
            zoom: 15,
            center: location,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        }

        map_<?php echo $map_id ; ?> = new google.maps.Map(document.getElementById("<?php echo $map_id ; ?>"), map_options);
        var marker = new google.maps.Marker({
            position: location,
            map: map_<?php echo $map_id ; ?>
        });
    }
    my_goog_run_map_<?php echo $map_id ; ?>();
</script>

<?php
endif;
return ob_get_clean();
}

add_shortcode( 'my_goog_map', 'my_goog_map_shortcode' );

// Loads Google Map API

function my_goog_map_load_scripts() {
    wp_register_script( 'google-maps-api', 'http://maps.google.com/maps/api/js?sensor=false' );
    }
    add_action( 'wp_enqueue_scripts', 'my_goog_map_load_scripts' );
    
// Retrieve coordinates for an address
// Coordinates are cached using transients and a hash of the address
//Transients are a way of caching data for a set amount of time in WordPress

function my_goog_map_get_coordinates( $address, $force_refresh = false ) {

    $address_hash = md5( $address );
    
    $coordinates = get_transient( $address_hash );
    
    if ($force_refresh || $coordinates === false) {
        $args       = array( 'address' => urlencode( $address ), 'sensor' => 'false' );
        $url        = add_query_arg( $args, 'http://maps.googleapis.com/maps/api/geocode/json' );
        $response 	= wp_remote_get( $url );
    
    if( is_wp_error( $response ) )
        return;
    
        $data = wp_remote_retrieve_body( $response );
    
    if( is_wp_error( $data ) )
        return;
    
    if ( $response['response']['code'] == 200 ) {
    
        $data = json_decode( $data );
    
    if ( $data->status === 'OK' ) {
    
            $coordinates = $data-> results[0]->geometry->location;
    
            $cache_value['lat'] 	= $coordinates->lat;
            $cache_value['lng'] 	= $coordinates->lng;
            $cache_value['address'] = (string) $data->results[0]->formatted_address;
    
            // cache coordinates for 3 months
            set_transient($address_hash, $cache_value, 3600*24*30*3);
            $data = $cache_value;
    
 //Return__ Unique identifier for retrieving translated strings.
    } elseif ( $data->status === 'ZERO_RESULTS' ) {
            return __( 'No location found for the entered address.', 'my_goog_map' );
    } elseif( $data->status === 'INVALID_REQUEST' ) {
            return __( 'Invalid request. Did you enter an address?', 'my_goog_map' );
    } else {
        return __( 'Something went wrong while retrieving your map, please ensure you have entered the short code correctly.', 'my_goog_map' );
    }
    
    } else {
        return __( 'Unable to contact Google API service.', 'my_goog_map' );
    }
    
    } else {
    // return cached results
    $data = $coordinates;
    }
    
    return $data;
    }